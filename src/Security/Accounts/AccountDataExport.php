<?php
/**
 * Account data export class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Accounts
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Accounts;

use SmartLicenseServer\Email\EmailPreferences;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\LicenseActivation;
use SmartLicenseServer\Monetization\PricingTier;
use SmartLicenseServer\Monetization\PurchaseRequest;
use SmartLicenseServer\Monetization\PurchaseRequestNote;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\AuthenticationFactorManager;
use SmartLicenseServer\Security\Authentication\PasskeyStore;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\SettingsAPI\UserSettings;

/**
 * "Download my data": everything the application keeps about a user, as a
 * document they can save.
 *
 * Holds the profile, preferences, sign-in methods (described, never their
 * keys), the licenses linked to the user, their purchases, organization
 * memberships and the metadata of the apps they host themselves.
 *
 * Never holds a secret. Each part is built from chosen fields, and the
 * whole document then goes through redact(), which drops any field whose
 * name says it is a secret (passwords and their hashes, TOTP secrets,
 * passkey credentials, site secrets, download and CSRF tokens, reset keys,
 * code digests), however deep. The license key is the one exception: it is
 * what the user bought, and the licenses page shows it to them already.
 */
class AccountDataExport {

	/**
	 * Format name and version of the document.
	 */
	public const FORMAT  = 'smliser-account-export';
	public const VERSION = 1;

	/**
	 * Rows read per page from paged sources.
	 */
	private const PAGE_SIZE = 100;

	/**
	 * Most pages read from one source, so an export always ends.
	 */
	private const MAX_PAGES = 100;

	/**
	 * Parts of a field name that mark it as a secret.
	 *
	 * @var string[]
	 */
	public const SECRET_MARKERS = [
		'password',
		'passwd',
		'pwd',
		'secret',
		'token',
		'csrf',
		'nonce',
		'credential',
		'private_key',
		'public_key',
		'api_key',
		'reset_key',
		'hash',
		'totp',
		'otp_code',
		'recovery_code',
		'seed',
		'receipt_file',
	];

	/**
	 * Field names kept although they contain a marker.
	 *
	 * @var string[]
	 */
	public const ALLOWED_FIELDS = [
		'license_key',
		'recovery_codes_remaining',
	];

	/**
	 * @param AuthenticationFactorManager $factors  Which second factors are on.
	 * @param PasskeyStore                $passkeys Passkey names and dates.
	 */
	public function __construct(
		protected AuthenticationFactorManager $factors,
		protected PasskeyStore $passkeys
	) {}

	/**
	 * The export document of a user.
	 *
	 * @param User $user
	 * @return array<string, mixed>
	 */
	public function build( User $user ) : array {
		return static::redact( [
			'format'              => self::FORMAT,
			'version'             => self::VERSION,
			'generated_at'        => gmdate( 'c' ),
			'profile'             => $this->profile( $user ),
			'settings'            => $this->settings( $user ),
			'email_notifications' => $this->email_notifications( $user ),
			'security'            => $this->security( $user ),
			'licenses'            => $this->licenses( $user ),
			'purchases'           => $this->purchases( $user ),
			'organizations'       => $this->organizations( $user ),
			'apps'                => $this->apps( $user ),
		] );
	}

	/**
	 * The document as pretty JSON.
	 *
	 * @param User $user
	 * @return string
	 * @throws \JsonException
	 */
	public function to_json( User $user ) : string {
		return json_encode( $this->build( $user ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
	}

	/**
	 * The file name to save the document as.
	 *
	 * @param User $user
	 * @return string
	 */
	public static function filename( User $user ) : string {
		return sprintf( 'account-data-%d-%s.json', $user->get_id(), gmdate( 'Y-m-d' ) );
	}

	/**
	 * Drop every field whose name marks it as a secret, at any depth.
	 *
	 * @param array<mixed> $data
	 * @return array<mixed>
	 */
	public static function redact( array $data ) : array {
		$clean = [];

		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && static::is_secret_field( $key ) ) {
				continue;
			}

			$clean[ $key ] = is_array( $value ) ? static::redact( $value ) : $value;
		}

		return $clean;
	}

	/**
	 * Whether a field name marks a secret.
	 *
	 * @param string $field
	 * @return bool
	 */
	public static function is_secret_field( string $field ) : bool {
		$field = strtolower( $field );

		if ( in_array( $field, self::ALLOWED_FIELDS, true ) ) {
			return false;
		}

		foreach ( self::SECRET_MARKERS as $marker ) {
			if ( str_contains( $field, $marker ) ) {
				return true;
			}
		}

		return false;
	}

	/*
	|----------------------------------------------------
	| THE PARTS (protected: environments may add to them)
	|----------------------------------------------------
	*/

	/**
	 * @param User $user
	 * @return array<string, mixed>
	 */
	protected function profile( User $user ) : array {
		return [
			'id'                => $user->get_id(),
			'display_name'      => $user->get_display_name(),
			'email'             => $user->get_email(),
			'email_verified_at' => self::date( $user->get_email_verified_at() ),
			'status'            => $user->get_status(),
			'role'              => (string) ContextServiceProvider::get_principal_role( $user )?->get_label(),
			'created_at'        => self::date( $user->get_created_at() ),
			'updated_at'        => self::date( $user->get_updated_at() ),
		];
	}

	/**
	 * @param User $user
	 * @return array<string, mixed>
	 */
	protected function settings( User $user ) : array {
		$settings = UserSettings::for( $user )->all();

		// Internal bookkeeping, not a preference; email_notifications lists the opt-outs readably.
		unset( $settings[ UserSettings::PWD_RESET_NAME ], $settings[ UserSettings::EMAIL_OPT_OUTS ] );

		return $settings;
	}

	/**
	 * @param User $user
	 * @return array{receive: string[], turned_off: string[]}
	 */
	protected function email_notifications( User $user ) : array {
		$off = EmailPreferences::turned_off( $user );

		return [
			'receive'    => array_values( array_diff( array_keys( EmailPreferences::categories() ), $off ) ),
			'turned_off' => $off,
		];
	}

	/**
	 * Which sign-in methods are set up; never their keys or codes.
	 *
	 * @param User $user
	 * @return array<string, mixed>
	 */
	protected function security( User $user ) : array {
		$status = $this->factors->status( (int) $user->get_id() );

		return [
			'two_factor'               => [
				'email_codes'       => (bool) $status['email'],
				'authenticator_app' => (bool) $status['totp'],
			],
			'recovery_codes_remaining' => (int) $status['recovery_codes'],
			'passkeys'                 => array_map(
				static fn ( array $passkey ) : array => [
					'label'      => (string) $passkey['label'],
					'created_at' => (string) $passkey['created_at'],
					'last_used'  => (string) $passkey['last_used'],
				],
				$this->passkeys->summaries( (int) $user->get_id() )
			),
		];
	}

	/**
	 * @param User $user
	 * @return list<array<string, mixed>>
	 */
	protected function licenses( User $user ) : array {
		$items = self::all_pages( static fn ( int $page ) : array => License::get_for_user( (int) $user->get_id(), $page, self::PAGE_SIZE ) );

		return array_map(
			static fn ( License $license ) : array => [
				'id'               => (int) $license->get_id(),
				'license_key'      => (string) $license->get_license_key(),
				'app'              => [
					'type' => (string) $license->get_app()?->get_type(),
					'slug' => (string) $license->get_app()?->get_slug(),
					'name' => (string) $license->get_app()?->get_name(),
				],
				'tier'             => (string) PricingTier::get_by_id( $license->get_tier_id() )?->get_name(),
				'status'           => (string) $license->get_status(),
				'licensee_name'    => (string) $license->get_licensee_fullname(),
				'licensee_email'   => $license->get_licensee_email(),
				'start_date'       => self::date( $license->get_start_date() ),
				'end_date'         => self::date( $license->get_end_date() ),
				'max_sites'        => $license->get_max_allowed_domains(),
				'sites'            => array_values( array_map(
					static fn ( LicenseActivation $activation ) : array => [
						'host'         => $activation->get_host(),
						'origin'       => $activation->get_origin(),
						'activated_at' => self::date( $activation->get_activated_at() ),
					],
					$license->get_activations()
				) ),
				'created_at'       => self::date( $license->get_created_at() ),
			],
			$items
		);
	}

	/**
	 * Purchase requests with the notes the buyer was sent; never the
	 * stored receipt's path or the internal notes.
	 *
	 * @param User $user
	 * @return list<array<string, mixed>>
	 */
	protected function purchases( User $user ) : array {
		$items = self::all_pages( static fn ( int $page ) : array => PurchaseRequest::get_for_buyer( $user, $page, self::PAGE_SIZE ) );

		return array_map(
			static fn ( PurchaseRequest $purchase ) : array => [
				'id'                => $purchase->get_id(),
				'tier'              => (string) $purchase->get_tier()?->get_name(),
				'price'             => $purchase->get_price(),
				'buyer_name'        => $purchase->get_buyer_name(),
				'buyer_email'       => $purchase->get_buyer_email(),
				'payment_reference' => $purchase->get_payment_reference(),
				'note'              => $purchase->get_note(),
				'receipt_name'      => $purchase->get_receipt_name(),
				'ip_address'        => $purchase->get_ip_address(),
				'status'            => $purchase->get_status(),
				'review_note'       => $purchase->is_pending() ? '' : $purchase->get_review_note(),
				'reviewed_at'       => self::date( $purchase->get_reviewed_at() ),
				'license_id'        => $purchase->get_license_id(),
				'submitted_at'      => self::date( $purchase->get_created_at() ),
				'messages'          => array_values( array_map(
					static fn ( PurchaseRequestNote $note ) : array => [ 'date' => self::date( $note->get_created_at() ), 'body' => $note->get_body() ],
					array_filter( PurchaseRequestNote::get_for_request( $purchase->get_id() ), static fn ( PurchaseRequestNote $note ) : bool => $note->is_sent_to_buyer() )
				) ),
			],
			$items
		);
	}

	/**
	 * @param User $user
	 * @return list<array<string, mixed>>
	 */
	protected function organizations( User $user ) : array {
		$organizations = ContextServiceProvider::get_user_organizations( $user ) ?? [];
		$rows          = [];

		foreach ( $organizations as $organization ) {
			$rows[] = [
				'id'     => $organization->get_id(),
				'name'   => $organization->get_display_name(),
				'slug'   => $organization->get_slug(),
				'status' => $organization->get_status(),
				'role'   => (string) ContextServiceProvider::get_principal_role( $user, $organization )?->get_label(),
			];
		}

		return $rows;
	}

	/**
	 * Metadata of the apps the user hosts themselves (not their organizations'), of every status.
	 *
	 * @param User $user
	 * @return list<array<string, mixed>>
	 */
	protected function apps( User $user ) : array {
		$owner = ContextServiceProvider::get_owner_for( $user );

		if ( ! $owner ) {
			return [];
		}

		$items = self::all_pages( static fn ( int $page ) : array => HostedApplicationService::get_apps_for_owner( (int) $owner->get_id(), [ 'status' => '', 'page' => $page, 'limit' => self::PAGE_SIZE ] ) );

		return array_map(
			static fn ( AbstractHostedApp $app ) : array => [
				'id'         => $app->get_id(),
				'type'       => $app->get_type(),
				'slug'       => $app->get_slug(),
				'name'       => $app->get_name(),
				'status'     => $app->get_status(),
				'version'    => $app->get_version(),
				'homepage'   => (string) $app->get_homepage()->url(),
				'created_at' => self::date( $app->get_created_at() ),
				'updated_at' => self::date( $app->get_last_updated() ),
			],
			$items
		);
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * Every item of a paged reader.
	 *
	 * @param callable(int): array{items: array, pagination: array{total_pages: int}} $read
	 * @return list<mixed>
	 */
	protected static function all_pages( callable $read ) : array {
		$items = [];

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$result = $read( $page );
			$items  = array_merge( $items, array_values( (array) ( $result['items'] ?? [] ) ) );

			if ( $page >= (int) ( $result['pagination']['total_pages'] ?? 0 ) ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * A date as an ISO 8601 string, or null.
	 *
	 * @param \DateTimeInterface|null $date
	 * @return string|null
	 */
	protected static function date( ?\DateTimeInterface $date ) : ?string {
		return $date?->format( 'c' );
	}
}
