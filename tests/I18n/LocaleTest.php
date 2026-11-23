<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\I18n;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SmartLicenseServer\I18n\Locale;
use SmartLicenseServer\I18n\LocaleResolver;

final class LocaleTest extends TestCase {

	public function test_normalizes_identifiers() : void {
		$this->assertSame( 'pt_BR', Locale::parse( 'pt-br' )->code() );
		$this->assertSame( 'pt-BR', Locale::parse( 'pt_BR' )->to_bcp47() );
		$this->assertSame( 'en_US', Locale::parse( 'en_US.UTF-8' )->code() );
		$this->assertSame( 'zh_Hans_CN', Locale::parse( 'zh-hans-cn' )->code() );
		$this->assertSame( 'de_DE_formal', Locale::parse( 'de_DE_formal' )->code() );
		$this->assertSame( 'es_419', Locale::parse( 'es-419' )->code() );
	}

	public function test_rejects_invalid_identifiers() : void {
		$this->assertNull( Locale::try_parse( '../../etc/passwd' ) );
		$this->assertNull( Locale::try_parse( 'english' ) );
		$this->assertNull( Locale::try_parse( '' ) );
		$this->expectException( InvalidArgumentException::class );
		Locale::parse( 'fr_FR/../x' );
	}

	public function test_fallback_chain() : void {
		$this->assertSame( [ 'pt_BR', 'pt' ], Locale::parse( 'pt_BR' )->fallback_chain() );
		$this->assertSame( [ 'zh_Hans_CN', 'zh_CN', 'zh_Hans', 'zh' ], Locale::parse( 'zh_Hans_CN' )->fallback_chain() );
		$this->assertSame( [ 'de_DE_formal', 'de_DE', 'de' ], Locale::parse( 'de_DE_formal' )->fallback_chain() );
		$this->assertSame( [ 'fr' ], Locale::parse( 'fr' )->fallback_chain() );
	}

	public function test_direction() : void {
		$this->assertTrue( Locale::parse( 'ar' )->is_rtl() );
		$this->assertTrue( Locale::parse( 'he_IL' )->is_rtl() );
		$this->assertFalse( Locale::parse( 'fr_FR' )->is_rtl() );
		$this->assertFalse( Locale::parse( 'ha_Latn_NG' )->is_rtl() );
	}

	public function test_accept_language_parsing() : void {
		$this->assertSame(
			[ 'fr-CH', 'fr', 'en', 'de' ],
			LocaleResolver::parse_accept_language( 'fr-CH, fr;q=0.9, en;q=0.8, de;q=0.7, *;q=0.5, es;q=0' )
		);
	}

	public function test_resolution_precedence() : void {
		$resolver = new LocaleResolver( [ 'en_US', 'fr_FR', 'de_DE', 'pt' ] );

		$this->assertSame( 'de_DE', $resolver->resolve( 'de_DE', 'fr_FR' )->code(), 'query wins' );
		$this->assertSame( 'fr_FR', $resolver->resolve( 'xx_XX', 'fr_FR', 'de_DE' )->code(), 'unavailable query ignored' );
		$this->assertSame( 'de_DE', $resolver->resolve( null, null, 'de_DE', 'fr_FR' )->code(), 'cookie before site' );
		$this->assertSame( 'fr_FR', $resolver->resolve( null, null, null, 'fr_FR', 'de' )->code(), 'site before browser' );
		$this->assertSame( 'de_DE', $resolver->resolve( null, null, null, '', 'es, de;q=0.8' )->code(), 'browser, loose match' );
		$this->assertSame( 'pt', $resolver->resolve( null, 'pt_BR' )->code(), 'regional falls back to language' );
		$this->assertSame( 'en_US', $resolver->resolve( null, null, null, null, 'ja' )->code(), 'default' );
	}
}
