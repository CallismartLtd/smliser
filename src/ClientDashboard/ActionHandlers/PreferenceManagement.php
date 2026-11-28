<?php
/**
 * Client preference management class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\I18n\I18n;
use SmartLicenseServer\I18n\Locale;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;
use Throwable;

/**
 * Client dashboard actions on the signed-in user's preferences
 * (<client dashboard prefix>/json/*, behind ClientAccessMiddleware).
 *
 * Response: { success, data: { message } }
 */
final class PreferenceManagement {

	/**
	 * Preferences a client may set.
	 */
	public const KEYS = [
		UserSettings::DASHBOARD_THEME_NAME,
		UserSettings::DASHBOARD_SIDEBAR_COLLAPSED_NAME,
		UserSettings::LOCALE,
	];

	/**
	 * Dashboard themes a client may choose. 'auto' follows the system.
	 */
	public const THEMES = [ 'auto', 'light', 'dark' ];

	/**
	 * Constructor.
	 *
	 * @param Guard $guard The signed-in principal.
	 */
	public function __construct( private Guard $guard ) {}

	/**
	 * POST <client dashboard prefix>/json/preferences
	 *
	 * Fields: key (one of KEYS), value.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function save( Request $request ) : Response {
		$principal = $this->guard->get_principal();

		if ( null === $principal ) {
			return $this->answer( false, smliser__( 'You must be signed in to change your preferences.' ), 401 );
		}

		$actor = $principal->get_actor();

		if ( ! $actor instanceof User ) {
			return $this->answer( false, smliser__( 'Only user accounts have preferences.' ), 403 );
		}

		$key = (string) $request->get( 'key', '' );

		if ( ! in_array( $key, self::KEYS, true ) ) {
			return $this->answer( false, sprintf(
				/* translators: %s: comma-separated list of preference names. */
				smliser__( 'The preference must be one of: %s.' ),
				implode( ', ', self::KEYS )
			), 400 );
		}

		$value = $request->get( 'value', '' );

		if ( UserSettings::LOCALE === $key ) {
			$value = self::normalize_locale( (string) $value );

			if ( null === $value ) {
				return $this->answer( false, smliser__( 'That language is not available.' ), 400 );
			}
		}

		if ( UserSettings::DASHBOARD_THEME_NAME === $key ) {
			$value = self::normalize_theme( (string) $value );

			if ( null === $value ) {
				return $this->answer( false, smliser__( 'That theme is not available.' ), 400 );
			}
		}

		try {
			$settings = UserSettings::for( $actor );

			// No language: forget the preference, so the cookie, the site language and the browser apply again.
			if ( UserSettings::LOCALE === $key && '' === $value ) {
				$settings->delete( $key );
				$saved = null === $settings->get( $key );
			} else {
				$saved = $settings->set( $key, $value );
			}
		} catch ( Throwable $e ) {
			return $this->answer( false, $e->getMessage(), 500 );
		}

		return $saved
			? $this->answer( true, smliser__( 'Saved.' ), 200 )
			: $this->answer( false, smliser__( 'The preference could not be saved.' ), 500 );
	}

	/**
	 * Validate a language choice: '' (use the site language) or an
	 * installed locale.
	 *
	 * @param string $value
	 * @return string|null The normalized code, '' or null when invalid.
	 */
	public static function normalize_locale( string $value ) : ?string {
		if ( '' === $value ) {
			return '';
		}

		$locale = Locale::try_parse( $value );

		if ( null === $locale || ! in_array( $locale->code(), I18n::translator()->available_locales(), true ) ) {
			return null;
		}

		return $locale->code();
	}

	/**
	 * Validate a dashboard theme choice: one of THEMES, '' meaning 'auto'.
	 *
	 * @param string $value
	 * @return string|null The theme, or null when invalid.
	 */
	public static function normalize_theme( string $value ) : ?string {
		$value = strtolower( trim( $value ) );

		if ( '' === $value || 'system' === $value ) {
			return 'auto';
		}

		return in_array( $value, self::THEMES, true ) ? $value : null;
	}

	/**
	 * A JSON answer.
	 *
	 * @param bool   $success
	 * @param string $message
	 * @param int    $status
	 * @return Response
	 */
	private function answer( bool $success, string $message, int $status ) : Response {
		return Response::json( [ 'success' => $success, 'data' => [ 'message' => $message ] ], $status );
	}
}
