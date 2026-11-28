<?php
/**
 * Email preferences class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Email
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Email;

use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\SettingsAPI\UserSettings;
use Throwable;

/**
 * Which emails a user may turn off, and whether a recipient turned one off.
 *
 * Emails fall in two kinds:
 *
 * - Optional (non-essential): reminders, activity and update notices,
 *   announcements. Each belongs to a category a user can turn off on the
 *   client dashboard (Account → Email notifications).
 * - Mandatory: everything else. Security and account emails (password
 *   reset and change, email verification and codes, two-factor and passkey
 *   notices), purchases and payments, licenses being issued, renewed or
 *   suspended, organization invitations, and the emails sent to
 *   administrators. They have no category and are always sent.
 *
 * The preference is enforced in one place, EmailTemplate::to_message(), so
 * no caller can forget it: an optional email to an address whose account
 * turned its category off is not built. The address must be the account's
 * verified email address, so nobody can silence the emails of an address
 * they have not proved they own.
 *
 * Extensions put their own templates in a category by overriding
 * EmailTemplate::preference_category().
 */
final class EmailPreferences {

	/** License expiry reminders and expiry notices. */
	public const LICENSE_REMINDERS = 'license_reminders';

	/** A license being activated on, or removed from, a site. */
	public const LICENSE_ACTIVITY = 'license_activity';

	/** New versions of apps the user holds a license for. */
	public const APP_UPDATES = 'app_updates';

	/** Confirmations of what the user did to their own hosted apps. */
	public const APP_ACTIVITY = 'app_activity';

	/** Announcements and bulk messages from the site. */
	public const ANNOUNCEMENTS = 'announcements';

	/**
	 * Optional built-in templates, template key => category.
	 *
	 * A template not listed here is mandatory.
	 *
	 * @var array<string, string>
	 */
	public const OPTIONAL_TEMPLATES = [
		'license_expiry_reminder'      => self::LICENSE_REMINDERS,
		'license_expired'              => self::LICENSE_REMINDERS,
		'license_activated'            => self::LICENSE_ACTIVITY,
		'license_deactivated'          => self::LICENSE_ACTIVITY,
		'new_app_version_notification' => self::APP_UPDATES,
		'app_published'                => self::APP_ACTIVITY,
		'app_updated'                  => self::APP_ACTIVITY,
		'bulk_message'                 => self::ANNOUNCEMENTS,
	];

	/**
	 * Finds the categories an address turned off; null for the default lookup.
	 *
	 * @var (\Closure(string): string[])|null
	 */
	private static ?\Closure $resolver = null;

	/**
	 * The categories a user can turn off, with their labels.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function categories() : array {
		return [
			self::LICENSE_REMINDERS => [
				'label'       => smliser__( 'License expiry reminders' ),
				'description' => smliser__( 'Reminders before a license expires, and a notice when it has expired.' ),
			],
			self::LICENSE_ACTIVITY  => [
				'label'       => smliser__( 'License activity' ),
				'description' => smliser__( 'A notice each time a license is activated on a site or removed from one.' ),
			],
			self::APP_UPDATES       => [
				'label'       => smliser__( 'New app versions' ),
				'description' => smliser__( 'A notice when an app you hold a license for has a new version.' ),
			],
			self::APP_ACTIVITY      => [
				'label'       => smliser__( 'Your app activity' ),
				'description' => smliser__( 'Confirmations when one of your hosted apps is published or a new version is uploaded.' ),
			],
			self::ANNOUNCEMENTS     => [
				'label'       => smliser__( 'Announcements' ),
				'description' => smliser__( 'News and messages the site sends to its customers.' ),
			],
		];
	}

	/**
	 * The category of a built-in template; null when the template is mandatory.
	 *
	 * @param string $template_key EmailTemplate::template_key().
	 * @return string|null
	 */
	public static function category_of( string $template_key ) : ?string {
		return self::OPTIONAL_TEMPLATES[ $template_key ] ?? null;
	}

	/**
	 * Whether an email of a category may be sent to a recipient.
	 *
	 * Mandatory emails (null category) are always accepted. So is an
	 * optional one when the recipient cannot be told apart (several
	 * addresses), or when looking the preference up fails: a preference
	 * must never stop the application from working.
	 *
	 * @param string|null $category  The template's category.
	 * @param string      $recipient The address, as the template gives it.
	 * @return bool
	 */
	public static function accepts( ?string $category, string $recipient ) : bool {
		if ( null === $category || '' === $category ) {
			return true;
		}

		$email = self::normalize_address( $recipient );

		if ( '' === $email ) {
			return true;
		}

		try {
			$turned_off = null !== self::$resolver ? ( self::$resolver )( $email ) : self::lookup( $email );
		} catch ( Throwable ) {
			return true;
		}

		return ! in_array( $category, $turned_off, true );
	}

	/**
	 * The categories a user turned off.
	 *
	 * @param User $user
	 * @return string[] Only categories that exist.
	 */
	public static function turned_off( User $user ) : array {
		$stored = UserSettings::for( $user )->get( UserSettings::EMAIL_OPT_OUTS, [] );

		return self::clean( is_array( $stored ) ? $stored : [] );
	}

	/**
	 * Save which categories a user receives; every other one is turned off.
	 *
	 * @param User     $user
	 * @param string[] $receive Categories to keep receiving.
	 * @return bool Whether the preference was saved.
	 */
	public static function save( User $user, array $receive ) : bool {
		return UserSettings::for( $user )->set( UserSettings::EMAIL_OPT_OUTS, self::opt_outs_for( $receive ) );
	}

	/**
	 * The categories turned off when a user keeps receiving these.
	 *
	 * @param array<mixed> $receive Categories to keep; unknown values are ignored.
	 * @return string[]
	 */
	public static function opt_outs_for( array $receive ) : array {
		$receive = self::clean( $receive );

		return array_values( array_diff( array_keys( self::categories() ), $receive ) );
	}

	/**
	 * Replace how a recipient's turned-off categories are found.
	 *
	 * For tests and for environments that keep users elsewhere; null restores
	 * the default lookup (the account with that verified email address).
	 *
	 * @param (callable(string): string[])|null $resolver Gets a lower-case address.
	 * @return void
	 */
	public static function set_resolver( ?callable $resolver ) : void {
		self::$resolver = null === $resolver ? null : \Closure::fromCallable( $resolver );
	}

	/**
	 * The bare, lower-case address of a single recipient.
	 *
	 * Accepts "name@example.com" and "Name <name@example.com>". Several
	 * addresses give an empty string.
	 *
	 * @param string $recipient
	 * @return string
	 */
	public static function normalize_address( string $recipient ) : string {
		$recipient = trim( $recipient );

		if ( 1 === preg_match( '/<([^<>]+)>\s*$/', $recipient, $match ) ) {
			$recipient = trim( $match[1] );
		}

		if ( '' === $recipient || str_contains( $recipient, ',' ) || false === filter_var( $recipient, FILTER_VALIDATE_EMAIL ) ) {
			return '';
		}

		return strtolower( $recipient );
	}

	/**
	 * The default lookup: the turned-off categories of the account whose
	 * verified email address this is.
	 *
	 * @param string $email Lower-case address.
	 * @return string[]
	 */
	private static function lookup( string $email ) : array {
		$user = User::get_by_email( $email );

		if ( ! $user instanceof User || ! $user->is_email_verified() || strtolower( $user->get_email() ) !== $email ) {
			return [];
		}

		return self::turned_off( $user );
	}

	/**
	 * Keep only known category slugs, once each.
	 *
	 * @param array<mixed> $categories
	 * @return string[]
	 */
	private static function clean( array $categories ) : array {
		$known = array_keys( self::categories() );

		return array_values( array_unique( array_filter(
			array_map( static fn ( $value ) : string => is_string( $value ) ? $value : '', $categories ),
			static fn ( string $value ) : bool => in_array( $value, $known, true )
		) ) );
	}
}
