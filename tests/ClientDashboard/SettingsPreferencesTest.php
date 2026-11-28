<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\ClientDashboard;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\ClientDashboard\ActionHandlers\PreferenceManagement;
use SmartLicenseServer\ClientDashboard\ContentHandlers\SettingsPage;
use SmartLicenseServer\I18n\I18n;
use SmartLicenseServer\I18n\Tools\CatalogCompiler;
use SmartLicenseServer\I18n\Translator;

/**
 * The client dashboard Settings page's choices and the validation of what
 * it saves through json/preferences.
 */
final class SettingsPreferencesTest extends TestCase {

	private string $tmp;

	protected function setUp() : void {
		$this->tmp = sys_get_temp_dir() . '/smliser-settings-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->tmp );
		I18n::set_translator( new Translator( 'en_US' ) );
	}

	protected function tearDown() : void {
		foreach ( glob( $this->tmp . '/*' ) ?: [] as $file ) {
			unlink( $file );
		}
		rmdir( $this->tmp );
		I18n::set_translator( null );
	}

	/**
	 * A translator with French installed (compiled), like a site with one translation.
	 */
	private function with_french() : void {
		copy( dirname( __DIR__ ) . '/I18n/fixtures/smliser-fr_FR.po', $this->tmp . '/smliser-fr_FR.po' );
		( new CatalogCompiler() )->compile( $this->tmp . '/smliser-fr_FR.po' );

		$translator = new Translator( 'en_US' );
		$translator->add_directory( 'smliser', $this->tmp );
		I18n::set_translator( $translator );
	}

	public function test_theme_accepts_the_three_choices() : void {
		$this->assertSame( 'light', PreferenceManagement::normalize_theme( 'light' ) );
		$this->assertSame( 'dark', PreferenceManagement::normalize_theme( ' DARK ' ) );
		$this->assertSame( 'auto', PreferenceManagement::normalize_theme( 'auto' ) );
	}

	public function test_empty_or_system_theme_follows_the_system() : void {
		$this->assertSame( 'auto', PreferenceManagement::normalize_theme( '' ) );
		$this->assertSame( 'auto', PreferenceManagement::normalize_theme( 'system' ) );
	}

	public function test_unknown_theme_is_refused() : void {
		$this->assertNull( PreferenceManagement::normalize_theme( 'purple' ) );
		$this->assertNull( PreferenceManagement::normalize_theme( '<script>' ) );
	}

	public function test_settings_page_offers_every_valid_theme() : void {
		$this->assertSame( PreferenceManagement::THEMES, array_keys( SettingsPage::theme_options() ) );
	}

	public function test_locale_accepts_installed_languages_and_automatic() : void {
		$this->with_french();

		$this->assertSame( '', PreferenceManagement::normalize_locale( '' ), 'Automatic clears the preference' );
		$this->assertSame( 'fr_FR', PreferenceManagement::normalize_locale( 'fr_FR' ) );
		$this->assertSame( 'fr_FR', PreferenceManagement::normalize_locale( 'fr-FR' ) );
		$this->assertNull( PreferenceManagement::normalize_locale( 'de_DE' ), 'Not installed' );
		$this->assertNull( PreferenceManagement::normalize_locale( 'not a locale' ) );
	}

	public function test_language_tab_needs_a_second_language() : void {
		$this->assertFalse( SettingsPage::has_language_tab(), 'Only English: nothing to choose' );

		$this->with_french();

		$this->assertTrue( SettingsPage::has_language_tab() );
	}

	public function test_active_tab_falls_back_to_the_first_shown() : void {
		$this->assertSame( 'theme', SettingsPage::active_tab( 'theme', [ 'language', 'theme' ] ) );
		$this->assertSame( 'language', SettingsPage::active_tab( '', [ 'language', 'theme' ] ) );
		$this->assertSame( 'language', SettingsPage::active_tab( 'bogus', [ 'language', 'theme' ] ) );
		$this->assertSame( 'theme', SettingsPage::active_tab( 'language', [ 'theme' ] ), 'A hidden tab cannot be opened' );
	}
}
