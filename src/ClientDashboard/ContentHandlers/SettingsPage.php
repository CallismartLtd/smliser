<?php
/**
 * Account settings page.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use SmartLicenseServer\ClientDashboard\ActionHandlers\PreferenceManagement;
use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\I18n\I18n;
use SmartLicenseServer\I18n\WPTranslator;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;
use Throwable;

/**
 * The signed-in user's own preferences, one tab each:
 *
 *  - Language: the language the dashboards, emails and pages are shown in
 *    (UserSettings::LOCALE), or automatic. Offered when more than one
 *    language is installed, and not under WordPress, where each user's
 *    language is set in their WordPress profile.
 *  - Theme: light, dark or the system's (UserSettings::DASHBOARD_THEME_NAME),
 *    the same preference the admin dashboard's theme toggle changes.
 *
 * Both save through PreferenceManagement (json/preferences) with
 * window.smliserPreferences (assets/js/i18n.js).
 */
final class SettingsPage implements ClientPageInterface {

	/**
	 * The tabs, in order; the first shown one opens by default.
	 */
	private const TABS = [ 'language', 'theme' ];

	public function __construct(
		private Guard $guard,
		private URLManager $urls
	) {}

	public function get_menu_key() : string {
		return 'settings';
	}

	public function get_menu_data() : array {
		return [
			'title'      => smliser__( 'Settings' ),
			'slug'       => 'settings',
			'handler'    => $this,
			'icon'       => 'ti ti-settings',
			'visibility' => fn () : bool => $this->guard->get_principal()?->get_actor() instanceof User,
		];
	}

	public function get_submenu() : array {
		return [];
	}

	public function index_page_handler() : callable {
		return [ $this, 'settings' ];
	}

	/**
	 * Whether the user can choose their language here.
	 *
	 * @return bool
	 */
	public static function has_language_tab() : bool {
		return ! I18n::translator() instanceof WPTranslator && count( I18n::language_options() ) > 1;
	}

	/**
	 * The tab to open: the one asked for when it is shown, else the first.
	 *
	 * @param string             $requested
	 * @param array<int, string> $tabs The tabs shown.
	 * @return string
	 */
	public static function active_tab( string $requested, array $tabs ) : string {
		return in_array( $requested, $tabs, true ) ? $requested : (string) ( $tabs[0] ?? 'theme' );
	}

	/**
	 * Theme choices, value => label.
	 *
	 * @return array<string, string>
	 */
	public static function theme_options() : array {
		return [
			'auto'  => smliser_x( 'System', 'color scheme' ),
			'light' => smliser_x( 'Light', 'color scheme' ),
			'dark'  => smliser_x( 'Dark', 'color scheme' ),
		];
	}

	public function settings( Request $request ) : View {
		$user = $this->guard->get_principal()?->get_actor();
		if ( ! $user instanceof User ) {
			return View::make( 'errors.404', [] )->set_status_code( 404 );
		}

		try {
			$settings     = UserSettings::for( $user );
			$saved_locale = $settings->get( UserSettings::LOCALE, '' );
			$saved_theme  = $settings->get( UserSettings::DASHBOARD_THEME_NAME, 'auto' );
		} catch ( Throwable ) {
			$saved_locale = '';
			$saved_theme  = 'auto';
		}

		$language_tab = self::has_language_tab();
		$languages    = $language_tab ? I18n::language_options() : [];
		$tabs         = $language_tab ? self::TABS : [ 'theme' ];
		$saved_locale = is_string( $saved_locale ) ? ( PreferenceManagement::normalize_locale( $saved_locale ) ?? '' ) : '';
		$saved_theme  = is_string( $saved_theme ) ? ( PreferenceManagement::normalize_theme( $saved_theme ) ?? 'auto' ) : 'auto';
		$current      = I18n::locale()->code();

		return View::make( 'client.settings', [
			'tabs'             => $tabs,
			'active_tab'       => self::active_tab( (string) $request->get( 'settings_tab', '' ), $tabs ),
			'languages'        => $languages,
			'locale'           => $saved_locale,
			'current_language' => $languages[ $current ] ?? $current,
			'theme'            => $saved_theme,
			'themes'           => self::theme_options(),
			'actions'          => [
				'preferences' => $this->urls->client_dashboard_url( 'json/preferences' )->url(),
			],
		] )
			->set_title( smliser__( 'Settings' ) )
			->set_header( 'Cache-Control', 'no-store' )
			->add_script( 'dashboard-settings' );
	}
}
