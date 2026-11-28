<?php
/**
 * My licenses page class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\ClientDashboard\LicenseHolderPolicy;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\LicenseActivation;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\Monetization\PricingTier;
use SmartLicenseServer\Security\Context\Guard;

/**
 * My licenses: the licenses linked to the signed-in user (a personal page).
 *
 * Licenses are linked when issued to the account, or to the account's
 * email address once it is verified (LicenseHolderPolicy). Each has its key
 * and service ID, with copy buttons, its activated sites and its license
 * document.
 *
 * URLs: licenses (the list; ?status=, ?app= and ?search= filter it, ?paged=
 * pages it), licenses?license=<ID> (one license; ?history= pages its
 * activation history). A license that is not the user's is not found (404),
 * whether it exists or not.
 *
 * Sites are read-only here: a license holder never frees a site, and
 * cannot reactivate a license. They may deactivate it (LicenseActions).
 */
final class LicensesPage implements ClientPageInterface {
	use PersonalPageTrait;

	/**
	 * Licenses per page of the list.
	 */
	private const PER_PAGE = 20;

	/**
	 * Entries per page of a license's activation history.
	 */
	private const HISTORY_PER_PAGE = 20;

	/**
	 * Days before its end that a license shows a way to buy again.
	 */
	private const EXPIRING_DAYS = 30;

	/**
	 * Events of the activation history: the site events, without the
	 * verification calls a client makes routinely.
	 */
	private const HISTORY_EVENTS = [ 'activation', 'uninstallation', 'deactivation' ];

	/**
	 * Names of the user's apps, "type/slug" => name; loaded on first use.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $app_names = null;

	/**
	 * @param Guard               $guard      The signed-in principal.
	 * @param URLManager          $urlmanager URL API.
	 * @param RepositoryAnalytics $analytics  License activity logs.
	 */
	public function __construct( Guard $guard, URLManager $urlmanager, private RepositoryAnalytics $analytics ) {
		$this->guard      = $guard;
		$this->urlmanager = $urlmanager;
	}

	public function get_menu_key() : string {
		return 'licenses';
	}

	public function get_menu_data() : array {
		return [
			'title'      => smliser__( 'My licenses' ),
			'slug'       => 'licenses',
			'handler'    => $this,
			'icon'       => 'ti ti-certificate',
			'visibility' => fn () : bool => $this->is_visible(),
		];
	}

	public function get_submenu() : array {
		return [];
	}

	public function index_page_handler() : callable {
		return [ $this, 'licenses' ];
	}

	/*
	|-------
	| PAGES
	|-------
	*/

	/**
	 * The list, or one license when the URL names it (?license=).
	 *
	 * View data (template client.licenses):
	 *   list<array> $licenses See summary().
	 *   array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
	 *   bool $verified Whether the user's email address is verified (unverified: licenses bought with it are not linked yet).
	 *   array{action: string, status: string, app: string, search: string, active: bool,
	 *       statuses: array<string, string>, apps: array<string, string>, clear_url: string} $filters
	 *       The filter form: the values shown and the choices (value => label).
	 *
	 * @param Request $request
	 * @return View
	 */
	public function licenses( Request $request ) : View {
		if ( $request->hasValue( 'license' ) ) {
			return $this->license( $request );
		}

		$user     = $this->current_user();
		$verified = (bool) $user?->is_email_verified();
		$statuses = License::get_status_labels();
		$apps     = $verified ? $this->app_names() : [];
		$status   = (string) $request->get( 'status', '' );
		$status   = array_key_exists( $status, $statuses ) ? $status : '';
		$app      = (string) $request->get( 'app', '' );
		$app      = array_key_exists( $app, $apps ) ? $app : '';
		$search   = mb_substr( trim( (string) $request->get( 'search', '' ) ), 0, 100 );
		$page     = max( 1, (int) $request->get( 'paged', 1 ) );
		$query    = array_filter( [ 'status' => $status, 'app' => $app, 'search' => $search ], static fn ( string $value ) : bool => '' !== $value );

		$result = $verified
			? License::search_for_user( (int) $user?->get_id(), [
				'status'           => $status,
				'app'              => $app,
				'search'           => $search,
				'search_app_props' => '' === $search ? [] : array_keys( array_filter( $apps, static fn ( string $name ) : bool => false !== mb_stripos( $name, $search ) ) ),
				'page'             => $page,
				'limit'            => self::PER_PAGE,
			] )
			: [ 'items' => [], 'pagination' => [ 'page' => $page, 'limit' => self::PER_PAGE, 'total' => 0, 'total_pages' => 0 ] ];

		$list_url   = fn ( array $args ) : string => $this->urlmanager->client_dashboard_url( 'licenses', array_filter( $args ) )->url();
		$pagination = $result['pagination'];

		return View::make(
			'client.licenses',
			[
				'licenses'   => array_map( [ $this, 'summary' ], $result['items'] ),
				'pagination' => [
					'page'         => (int) $pagination['page'],
					'total_pages'  => (int) $pagination['total_pages'],
					'total'        => (int) $pagination['total'],
					'previous_url' => $pagination['page'] > 1 ? $list_url( $query + [ 'paged' => $pagination['page'] - 1 ] ) : '',
					'next_url'     => $pagination['page'] < $pagination['total_pages'] ? $list_url( $query + [ 'paged' => $pagination['page'] + 1 ] ) : '',
				],
				'verified'   => $verified,
				'filters'    => [
					'action'    => $this->urlmanager->client_dashboard_url( 'licenses' )->url(),
					'status'    => $status,
					'app'       => $app,
					'search'    => $search,
					'active'    => [] !== $query,
					'statuses'  => $statuses,
					'apps'      => $apps,
					'clear_url' => $list_url( [] ),
				],
			]
		);
	}

	/**
	 * One license of the user.
	 *
	 * View data (template client.license):
	 *   array $license See summary(), plus: features (list<string>), start (string),
	 *       created (string), source (string), reference (string), app_url (string),
	 *       can_deactivate (bool).
	 *   list<array{event: string, event_type: string, site: string, date: string}> $history
	 *   array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $history_pagination
	 *   string $list_url The list.
	 *   string $deactivate_url Where the deactivation form posts.
	 *
	 * @param Request $request
	 * @return View
	 */
	private function license( Request $request ) : View {
		$license = LicenseHolderPolicy::find( (int) $request->get( 'license', 0 ), $this->current_user() );

		if ( null === $license ) {
			return View::make(
				'client.not-found',
				[
					'message'    => smliser__( 'This license does not exist, or it is not linked to your account.' ),
					'back_url'   => $this->urlmanager->client_dashboard_url( 'licenses' )->url(),
					'back_label' => smliser__( 'My licenses' ),
				]
			)
				->set_status_code( 404 )
				->set_title( smliser__( 'License not found' ) );
		}

		$app       = $license->get_app();
		$page      = max( 1, (int) $request->get( 'history', 1 ) );
		$logs      = $this->analytics->get_license_logs( (int) $license->get_id(), $page, self::HISTORY_PER_PAGE, self::HISTORY_EVENTS );
		$events    = [
			'activation'     => smliser_x( 'Activated', 'license event' ),
			'uninstallation' => smliser_x( 'Uninstalled', 'license event' ),
			'deactivation'   => smliser_x( 'License deactivated', 'license event' ),
		];
		$history   = array_map(
			fn ( array $log ) : array => [
				'event'      => $events[ $log['event_type'] ] ?? $log['event_type'],
				'event_type' => $log['event_type'],
				'site'       => in_array( $log['website'], [ '', 'N/A', 'unknown' ], true ) ? '' : $this->host( $log['website'] ),
				'date'       => $log['created_at'] > 0 ? ( new DateTimeImmutable( '@' . $log['created_at'] ) )->format( \smliser_datetime_format() ) : '',
			],
			$logs['items']
		);
		$history_url = fn ( int $to ) : string => $this->urlmanager->client_dashboard_url( 'licenses', array_filter( [ 'license' => $license->get_id(), 'history' => $to > 1 ? $to : 0 ] ) )->url();
		$pages       = (int) $logs['pagination']['total_pages'];

		$provider_id = $license->get_provider_id();
		$provider    = '' === $provider_id ? null : MonetizationRegistry::instance()->get_provider( $provider_id );
		$source      = match ( true ) {
			null !== $provider  => $provider::get_name(),
			'' !== $provider_id => $provider_id,
			default             => smliser__( 'Issued directly' ),
		};

		$data = $this->summary( $license ) + [
			'features'       => array_values( array_filter( array_map( 'strval', $license->get_features() ) ) ),
			'start'          => $this->format_date( $license->get_start_date(), smliser__( 'Not set' ) ),
			'created'        => $this->format_date( $license->get_created_at() ),
			'source'         => $source,
			'reference'      => $license->get_external_ref(),
			'app_url'        => $app instanceof AbstractHostedApp && $app->is_active() ? $app->get_url()->url() : '',
			'can_deactivate' => LicenseHolderPolicy::can_deactivate( $license, $this->current_user() ),
		];

		return View::make(
			'client.license',
			[
				'license'            => $data,
				'history'            => $history,
				'history_pagination' => [
					'page'         => $page,
					'total_pages'  => $pages,
					'total'        => (int) $logs['pagination']['total'],
					'previous_url' => $page > 1 ? $history_url( $page - 1 ) : '',
					'next_url'     => $page < $pages ? $history_url( $page + 1 ) : '',
				],
				'list_url'           => $this->urlmanager->client_dashboard_url( 'licenses' )->url(),
				'deactivate_url'     => $this->urlmanager->client_dashboard_url( 'json/licenses/deactivate' )->url(),
			]
		)->set_title( '' !== $data['tier'] ? sprintf( '%s — %s', $data['app'], $data['tier'] ) : $data['app'] );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * A license as the list shows it.
	 *
	 * @param License $license
	 * @return array{id: int, app: string, tier: string, status_key: string, status: string,
	 *     expires: string, sites: string, site_list: list<array{host: string, activated: string}>,
	 *     key: string, partial_key: string, service_id: string, deactivated: bool,
	 *     url: string, document_url: string, app_download_url: string,
	 *     renew: array{url: string, label: string, external: bool}|null}
	 */
	private function summary( License $license ) : array {
		$app    = $license->get_app();
		$status = $license->get_status();
		$sites  = array_values( array_map(
			fn ( LicenseActivation $activation ) : array => [
				'host'      => $activation->get_host(),
				'activated' => $this->format_date( $activation->get_activated_at(), '—' ),
			],
			$license->get_activations()
		) );

		return [
			'id'               => (int) $license->get_id(),
			'app'              => $app?->get_name() ?? smliser__( 'App no longer available' ),
			'tier'             => (string) PricingTier::get_by_id( $license->get_tier_id() )?->get_name(),
			'status_key'       => $status,
			'status'           => License::get_status_label( $status ),
			'expires'          => $this->format_date( $license->get_end_date(), smliser__( 'Never' ) ),
			'sites'            => $this->sites_count( count( $sites ), (int) $license->get_max_allowed_domains( 'raw' ) ),
			'site_list'        => $sites,
			'key'              => (string) $license->get_license_key(),
			'partial_key'      => $license->get_partial_key(),
			'service_id'       => (string) $license->get_service_id(),
			'deactivated'      => $license->is_deactivated(),
			'url'              => $this->urlmanager->client_dashboard_url( 'licenses', [ 'license' => $license->get_id() ] )->url(),
			'document_url'     => $this->urlmanager->client_dashboard_url( sprintf( 'download/license/%d', $license->get_id() ) )->url(),
			'app_download_url' => $app ? $this->urlmanager->client_dashboard_url( sprintf( 'download/%d/%s/%s.zip', $license->get_id(), $app->get_type(), $app->get_slug() ) )->url() : '',
			'renew'            => $this->renewal( $license, $app ),
		];
	}

	/**
	 * Sites in use out of those allowed, e.g. "2 of 5".
	 *
	 * @param int $count Sites the license is activated on.
	 * @param int $max   Sites allowed; -1 for unlimited.
	 * @return string
	 */
	private function sites_count( int $count, int $max ) : string {
		if ( $max < 0 ) {
			/* translators: %d: number of sites the license is activated on; the license allows any number. */
			return sprintf( smliser__( '%d of unlimited' ), $count );
		}

		/* translators: 1: number of sites the license is activated on, 2: number of sites it allows. */
		return sprintf( smliser__( '%1$d of %2$d' ), $count, $max );
	}

	/**
	 * A way to buy the license's plan again, for a license that has expired
	 * or ends soon; null when there is none.
	 *
	 * Buying again goes through the purchase flow the app already sells
	 * with: its tier's checkout (the purchase page for manual payment, the
	 * store's page for a remote provider), or the app's pricing when the
	 * tier is no longer sold. It issues a new license; it does not extend
	 * this one.
	 *
	 * @param License                $license
	 * @param AbstractHostedApp|null $app
	 * @return array{url: string, label: string, external: bool}|null
	 */
	private function renewal( License $license, ?AbstractHostedApp $app ) : ?array {
		$end    = $license->get_end_date();
		$status = $license->get_status();
		$soon   = null !== $end && License::STATUS_ACTIVE === $status
			&& $end <= new DateTimeImmutable( sprintf( '+%d days', self::EXPIRING_DAYS ), new DateTimeZone( 'UTC' ) );

		if ( ( License::STATUS_EXPIRED !== $status && ! $soon ) || ! $app instanceof AbstractHostedApp || ! $app->is_active() ) {
			return null;
		}

		$monetization = Monetization::get_by_app( $app->get_type(), $app->get_id() );

		if ( ! $monetization || ! $monetization->is_enabled() ) {
			return null;
		}

		$tier = $license->get_tier_id() > 0 ? $monetization->get_tier( $license->get_tier_id() ) : null;
		$url  = $tier instanceof PricingTier && $tier->is_active() ? (string) ( $tier->to_public_array()['offer']['checkout_url'] ?? '' ) : '';

		if ( $this->is_http_url( $url ) ) {
			return [ 'url' => $url, 'label' => smliser__( 'Buy again' ), 'external' => ! str_starts_with( $url, $this->urlmanager->url( '/' )->get_origin() ) ];
		}

		return $monetization->is_active() ? [ 'url' => $app->get_url()->url(), 'label' => smliser__( 'See pricing' ), 'external' => false ] : null;
	}

	/**
	 * The names of the apps the user holds licenses for, "type/slug" => name.
	 *
	 * @return array<string, string>
	 */
	private function app_names() : array {
		if ( null === $this->app_names ) {
			$this->app_names = [];

			foreach ( License::get_app_props_for_user( (int) $this->current_user()?->get_id() ) as $prop ) {
				[ $type, $slug ]           = array_pad( explode( '/', $prop, 2 ), 2, '' );
				$app                       = '' !== $type && '' !== $slug ? HostedApplicationService::get_app_by_slug( $type, $slug ) : null;
				$this->app_names[ $prop ]  = $app?->get_name() ?? smliser__( 'App no longer available' );
			}

			asort( $this->app_names, SORT_NATURAL | SORT_FLAG_CASE );
		}

		return $this->app_names;
	}

	/**
	 * The host of a logged site, which may be a URL or a bare host.
	 *
	 * @param string $website
	 * @return string
	 */
	private function host( string $website ) : string {
		$host = parse_url( str_contains( $website, '//' ) ? $website : '//' . $website, PHP_URL_HOST );

		return is_string( $host ) && '' !== $host ? $host : $website;
	}

	/**
	 * Whether a URL is an absolute http(s) URL.
	 *
	 * @param string $url
	 * @return bool
	 */
	private function is_http_url( string $url ) : bool {
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

		return false !== filter_var( $url, FILTER_VALIDATE_URL ) && in_array( $scheme, [ 'http', 'https' ], true );
	}
}
