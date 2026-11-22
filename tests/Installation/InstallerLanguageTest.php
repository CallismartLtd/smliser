<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\Installation;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\Environments\Application\Installation\InstallerLanguage;

final class InstallerLanguageTest extends TestCase {

	private function languages() : InstallerLanguage {
		return new InstallerLanguage( [ 'en_US', 'fr_FR', 'pt', 'ar' ] );
	}

	public function test_query_wins_over_stored_choice_and_browser() : void {
		$locale = $this->languages()->resolve( 'fr_FR', 'ar', 'pt-BR,pt;q=0.9' );

		$this->assertSame( 'fr_FR', $locale->code() );
	}

	public function test_stored_choice_wins_over_browser() : void {
		$this->assertSame( 'ar', $this->languages()->resolve( null, 'ar', 'fr-FR' )->code() );
	}

	public function test_invalid_or_missing_query_falls_back() : void {
		$languages = $this->languages();

		$this->assertSame( 'ar', $languages->resolve( '../etc', 'ar' )->code() );
		$this->assertSame( 'ar', $languages->resolve( 'de_DE', 'ar' )->code() );
		$this->assertSame( 'fr_FR', $languages->resolve( null, null, 'fr;q=0.8, de' )->code() );
		$this->assertSame( 'en_US', $languages->resolve( null, null, 'de-DE' )->code() );
		$this->assertSame( 'en_US', $languages->resolve( null, null, null )->code() );
	}

	public function test_browser_language_is_not_a_choice() : void {
		$languages = $this->languages();

		$this->assertNull( $languages->choice( null, null ) );
		$this->assertNull( $languages->choice( 'de_DE', 'nope' ) );
		$this->assertSame( 'fr_FR', $languages->choice( null, 'fr-fr' )?->code() );
		$this->assertSame( 'pt', $languages->choice( 'pt_BR' )?->code() );
	}

	public function test_find_validates_against_installed_locales() : void {
		$languages = $this->languages();

		$this->assertSame( 'fr_FR', $languages->find( ' fr_FR ' )?->code() );
		$this->assertNull( $languages->find( '' ) );
		$this->assertNull( $languages->find( 'fr' ) );
		$this->assertNull( $languages->find( 'es_ES' ) );
	}

	public function test_codes_and_choice_availability() : void {
		$this->assertSame( [ 'en_US', 'fr_FR', 'pt', 'ar' ], ( new InstallerLanguage( [ 'en_US', 'fr-FR', 'pt', 'ar', 'bad/../x', 'fr_FR' ] ) )->codes() );
		$this->assertTrue( $this->languages()->has_choice() );
		$this->assertFalse( ( new InstallerLanguage( [ 'en_US' ] ) )->has_choice() );
	}
}
