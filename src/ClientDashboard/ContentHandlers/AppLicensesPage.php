<?php
/**
 * App licenses page class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\ClientDashboard\AppOwnerScope;
use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\LicenseActivation;
use SmartLicenseServer\Monetization\PricingTier;

/**
 * App licenses: the licenses issued for the current owner's apps.
 *
 * The current owner is the user, or the organization they act for (see
 * AppOwnerScope). The page shows while the user acts for an owner and
 * their role there holds monetization.license.update or
 * monetization.license.uninstall_domain. The list filters by app and
 * status, and searches license keys and licensees; one license shows its
 * activated sites and its activity.
 *
 * What the owner may change is what protects their app, not the sale:
 *   freeing an activated site      monetization.license.uninstall_domain
 *   reactivating a deactivated
 *   license, suspending and
 *   unsuspending one               monetization.license.update
 * Keys, licensees, tiers, dates and site limits are issued by the store
 * and edited in the admin dashboard only.
 *
 * URLs: app-licenses (the list; ?app=type/slug, ?status=, ?q=, ?paged=),
 * app-licenses?license=<ID> (one license; ?history= pages its activity).
 * The actions are AppLicenseActions'.
 */
final class AppLicensesPage implements ClientPageInterface {

	/**
	 * Licenses per page of the list.
	 */
	private const PER_PAGE = 20;

	/**
	 * Activity entries per page of a license.
	 */
	private const HISTORY_PER_PAGE = 20;

	/**
	 * @param AppOwnerScope       $scope      The current owner's apps and licenses.
	 * @param URLManager          $urlmanager URL API.
	 * @param RepositoryAnalytics $analytics  The license activity log.
	 */
	public function __construct(
		private AppOwnerScope $scope,
		private URLManager $urlmanager,
		private RepositoryAnalytics $analytics
	) {}

	public function get_menu_key() : string {
		return 'app-licenses';
	}

	public function get_menu_data() : array {
		return [
			'title'      => smliser__( 'App licenses' ),
			'slug'       => 'app-licenses',
			'handler'    => $this,
			'icon'       => 'ti ti-license',
			'visibility' => fn () : bool => $this->scope->can_view_licenses(),
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
	 * View data (template client.app-licenses):
	 *   list<array{id: int, licensee: string, email: string, app: string, tier: string,
	 *       status: string, status_label: string, sites: string, expires: string, url: string}> $licenses
	 *   array<string, string> $apps The owner's apps to filter by, "type/slug" => name.
	 *   array<string, string> $statuses Statuses to filter by, status => label.
	 *   array{app: string, status: string, q: string} $filters The filters applied.
	 *   string $list_url The unfiltered list (the filter form's action).
	 *   array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
	 *
	 * @param Request $request
	 * @return View
	 */
	public function licenses( Request $request ) : View {
		if ( ! $this->scope->can_view_licenses() ) {
			return $this->not_found( smliser__( 'Switch to an account or organization whose role lets you manage the licenses of its apps.' ), false );
		}

		if ( $request->hasValue( 'license' ) ) {
			return $this->license( $request );
		}

		$owned    = $this->scope->owned_apps();
		$statuses = self::status_labels();
		$app      = (string) $request->get( 'app', '' );
		$app      = isset( $owned[ $app ] ) ? $app : '';
		$status   = (string) $request->get( 'status', '' );
		$status   = isset( $statuses[ $status ] ) ? $status : '';
		$search   = trim( (string) $request->get( 'q', '' ) );
		$page     = max( 1, (int) $request->get( 'paged', 1 ) );

		// Only the owner's apps are ever searched; a filter can narrow them, never widen.
		$result = License::search_for_apps(
			'' !== $app ? [ $app ] : array_keys( $owned ),
			[ 'status' => $status, 'search' => $search, 'page' => $page, 'limit' => self::PER_PAGE ]
		);

		$licenses = array_map(
			fn ( License $license ) : array => $this->summary( $license, $owned[ (string) $license->get_app_prop( 'view' ) ] ?? null, $statuses ),
			$result['items']
		);

		$filters    = [ 'app' => $app, 'status' => $status, 'q' => $search ];
		$list_url   = fn ( int $to ) : string => $this->urlmanager->client_dashboard_url( 'app-licenses', array_filter( $filters + [ 'paged' => $to > 1 ? $to : 0 ] ) )->url();
		$pagination = $result['pagination'];

		return View::make(
			'client.app-licenses',
			[
				'licenses'   => $licenses,
				'apps'       => array_map( static fn ( AbstractHostedApp $app ) : string => $app->get_name(), $owned ),
				'statuses'   => $statuses,
				'filters'    => $filters,
				'list_url'   => $this->urlmanager->client_dashboard_url( 'app-licenses' )->url(),
				'pagination' => [
					'page'         => $pagination['page'],
					'total_pages'  => $pagination['total_pages'],
					'total'        => $pagination['total'],
					'previous_url' => $pagination['page'] > 1 ? $list_url( $pagination['page'] - 1 ) : '',
					'next_url'     => $pagination['page'] < $pagination['total_pages'] ? $list_url( $pagination['page'] + 1 ) : '',
				],
			]
		);
	}

	/**
	 * One license.
	 *
	 * View data (template client.app-license):
	 *   array{id: int, licensee: string, email: string, app: string, tier: string, status: string,
	 *       status_label: string, sites: string, expires: string, url: string, partial_key: string,
	 *       starts: string, created: string} $license
	 *   list<array{host: string, origin: string, activated: string}> $sites
	 *   list<array{event: string, website: string, comment: string, ip_address: string, date: string}> $history
	 *   array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $history_pagination
	 *   array{free_site: bool, reactivate: bool, suspend: bool, unsuspend: bool} $can
	 *   array<string, string> $actions Action URLs: free_site, status.
	 *   string $list_url The list.
	 *
	 * @param Request $request
	 * @return View
	 */
	private function license( Request $request ) : View {
		$license = $this->scope->license( (int) $request->get( 'license', 0 ) );

		if ( ! $license instanceof License ) {
			return $this->not_found( $license->get_error_message() );
		}

		$app      = $license->get_app();
		$statuses = self::status_labels();
		$stored   = $license->get_status( 'edit' );
		$status   = $license->get_status();
		$change   = $this->scope->can( AppOwnerScope::CAP_CHANGE_STATUS );

		$sites = array_values( array_map(
			static fn ( LicenseActivation $activation ) : array => [
				'host'      => $activation->get_host(),
				'origin'    => $activation->get_origin(),
				'activated' => $activation->get_activated_at()?->format( \smliser_datetime_format() ) ?? '',
			],
			$license->get_activations()
		) );

		// The license's activity, newest first.
		$logs = array_values( array_filter(
			$this->analytics->get_license_activity_logs(),
			static fn ( $log ) : bool => is_array( $log ) && (int) ( $log['license_id'] ?? 0 ) === (int) $license->get_id()
		) );

		usort( $logs, static fn ( array $a, array $b ) : int => (int) ( $b['created_at'] ?? 0 ) <=> (int) ( $a['created_at'] ?? 0 ) );

		$total       = count( $logs );
		$total_pages = (int) ceil( $total / self::HISTORY_PER_PAGE );
		$page        = min( max( 1, (int) $request->get( 'history', 1 ) ), max( 1, $total_pages ) );
		$events      = self::event_labels();
		$history     = array_map(
			static fn ( array $log ) : array => [
				'event'      => $events[ (string) ( $log['event_type'] ?? '' ) ] ?? (string) ( $log['event_type'] ?? '' ),
				'website'    => (string) ( $log['website'] ?? '' ),
				'comment'    => (string) ( $log['comment'] ?? '' ),
				'ip_address' => (string) ( $log['ip_address'] ?? '' ),
				'date'       => isset( $log['created_at'] ) ? gmdate( \smliser_datetime_format(), (int) $log['created_at'] ) : '',
			],
			array_slice( $logs, ( $page - 1 ) * self::HISTORY_PER_PAGE, self::HISTORY_PER_PAGE )
		);

		$history_url = fn ( int $to ) : string => $this->urlmanager->client_dashboard_url( 'app-licenses', array_filter( [ 'license' => $license->get_id(), 'history' => $to > 1 ? $to : 0 ] ) )->url();

		return View::make(
			'client.app-license',
			[
				'license'            => $this->summary( $license, $app, $statuses ) + [
					'partial_key' => $license->get_partial_key(),
					'starts'      => $license->get_start_date()?->format( \smliser_datetime_format() ) ?? '',
					'created'     => $license->get_created_at()?->format( \smliser_datetime_format() ) ?? '',
				],
				'sites'              => $sites,
				'history'            => $history,
				'history_pagination' => [
					'page'         => $page,
					'total_pages'  => $total_pages,
					'total'        => $total,
					'previous_url' => $page > 1 ? $history_url( $page - 1 ) : '',
					'next_url'     => $page < $total_pages ? $history_url( $page + 1 ) : '',
				],
				'can'                => [
					'free_site'  => $this->scope->can( AppOwnerScope::CAP_FREE_SITE ),
					'reactivate' => $change && License::STATUS_DEACTIVATED === $status,
					'suspend'    => $change && ! in_array( $stored, [ License::STATUS_SUSPENDED, License::STATUS_REVOKED ], true ),
					'unsuspend'  => $change && License::STATUS_SUSPENDED === $stored,
				],
				'actions'            => [
					'free_site' => $this->urlmanager->client_dashboard_url( 'json/app-licenses/free-site' )->url(),
					'status'    => $this->urlmanager->client_dashboard_url( 'json/app-licenses/status' )->url(),
				],
				'list_url'           => $this->urlmanager->client_dashboard_url( 'app-licenses' )->url(),
			]
		)->set_title( sprintf( smliser__( 'License #%d' ), $license->get_id() ) );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * What the list shows of a license.
	 *
	 * @param License                $license
	 * @param AbstractHostedApp|null $app
	 * @param array<string, string>  $statuses
	 * @return array{id: int, licensee: string, email: string, app: string, tier: string,
	 *     status: string, status_label: string, sites: string, expires: string, url: string}
	 */
	private function summary( License $license, ?AbstractHostedApp $app, array $statuses ) : array {
		$status = $license->get_status();
		$max    = (int) $license->get_max_allowed_domains( 'save' );
		$used   = (int) $license->get_total_active_domains();

		return [
			'id'           => (int) $license->get_id(),
			'licensee'     => (string) $license->get_licensee_fullname(),
			'email'        => $license->get_licensee_email(),
			'app'          => $app?->get_name() ?? '',
			'tier'         => $license->get_tier_id() ? (string) PricingTier::get_by_id( $license->get_tier_id() )?->get_name() : '',
			'status'       => $status,
			'status_label' => $statuses[ $status ] ?? $status,
			/* translators: 1: sites the license is activated on, 2: the most it may be activated on. */
			'sites'        => $max < 0 ? sprintf( smliser__( '%d of unlimited' ), $used ) : sprintf( smliser__( '%1$d of %2$d' ), $used, $max ),
			'expires'      => $license->get_end_date()?->format( \smliser_datetime_format() ) ?? smliser__( 'Never' ),
			'url'          => $this->urlmanager->client_dashboard_url( 'app-licenses', [ 'license' => $license->get_id() ] )->url(),
		];
	}

	/**
	 * The license statuses, status => label.
	 *
	 * @return array<string, string>
	 */
	public static function status_labels() : array {
		return [
			License::STATUS_ACTIVE      => smliser_x( 'Active', 'license status' ),
			License::STATUS_LIFETIME    => smliser_x( 'Lifetime', 'license status' ),
			License::STATUS_PENDING     => smliser_x( 'Pending', 'license status' ),
			License::STATUS_EXPIRED     => smliser_x( 'Expired', 'license status' ),
			License::STATUS_DEACTIVATED => smliser_x( 'Deactivated', 'license status' ),
			License::STATUS_SUSPENDED   => smliser_x( 'Suspended', 'license status' ),
			License::STATUS_REVOKED     => smliser_x( 'Revoked', 'license status' ),
			License::STATUS_INACTIVE    => smliser_x( 'Inactive', 'license status' ),
		];
	}

	/**
	 * The license activity events, event type => label.
	 *
	 * @return array<string, string>
	 */
	private static function event_labels() : array {
		return [
			'activation'     => smliser_x( 'Activated', 'license event' ),
			'deactivation'   => smliser_x( 'Deactivated', 'license event' ),
			'uninstallation' => smliser_x( 'Site removed', 'license event' ),
			'verification'   => smliser_x( 'Verified', 'license event' ),
			'status_change'  => smliser_x( 'Status changed', 'license event' ),
		];
	}

	/**
	 * The page for a license that is not the owner's, or a list they may not see.
	 *
	 * @param string $message
	 * @param bool   $to_list Whether to link back to the list; the overview otherwise.
	 * @return View
	 */
	private function not_found( string $message, bool $to_list = true ) : View {
		return View::make(
			'client.not-found',
			[
				'message'    => $message,
				'back_url'   => $this->urlmanager->client_dashboard_url( $to_list ? 'app-licenses' : '' )->url(),
				'back_label' => $to_list ? smliser__( 'App licenses' ) : smliser__( 'Overview' ),
			]
		)
			->set_status_code( 404 )
			->set_title( smliser__( 'License not found' ) );
	}
}
