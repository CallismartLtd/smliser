<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\I18n;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmartLicenseServer\I18n\Locale;
use SmartLicenseServer\I18n\PluralForms;

final class PluralFormsTest extends TestCase {

	public static function rules() : array {
		return [
			'english'  => [ 'nplurals=2; plural=(n != 1);', [ 0 => 1, 1 => 0, 2 => 1, 11 => 1 ] ],
			'french'   => [ 'nplurals=2; plural=(n > 1);', [ 0 => 0, 1 => 0, 2 => 1 ] ],
			'japanese' => [ 'nplurals=1; plural=0;', [ 0 => 0, 1 => 0, 5 => 0 ] ],
			'russian'  => [
				'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);',
				[ 1 => 0, 21 => 0, 11 => 2, 2 => 1, 4 => 1, 12 => 2, 22 => 1, 5 => 2, 111 => 2, 101 => 0 ],
			],
			'arabic'   => [
				'nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);',
				[ 0 => 0, 1 => 1, 2 => 2, 3 => 3, 10 => 3, 11 => 4, 99 => 4, 100 => 5, 102 => 5 ],
			],
			'czech'    => [ 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;', [ 1 => 0, 3 => 1, 5 => 2 ] ],
			'unary'    => [ 'nplurals=2; plural=!(n == 1);', [ 1 => 0, 3 => 1 ] ],
		];
	}

	#[DataProvider( 'rules' )]
	public function test_evaluates_rules( string $header, array $expected ) : void {
		$rule = PluralForms::from_header( $header );

		foreach ( $expected as $n => $index ) {
			$this->assertSame( $index, $rule->index( $n ), "n={$n}" );
		}
	}

	public function test_rejects_code_injection() : void {
		$this->expectException( InvalidArgumentException::class );
		new PluralForms( 2, 'system("id")' );
	}

	public function test_rejects_unbalanced_expression() : void {
		$this->expectException( InvalidArgumentException::class );
		new PluralForms( 2, '(n != 1' );
	}

	public function test_invalid_header_falls_back_to_english() : void {
		$rule = PluralForms::from_header_or_default( 'garbage' );
		$this->assertSame( 2, $rule->count() );
		$this->assertSame( 0, $rule->index( 1 ) );
	}

	public function test_index_is_clamped_and_division_by_zero_is_safe() : void {
		$this->assertSame( 1, ( new PluralForms( 2, '5' ) )->index( 3 ) );
		$this->assertSame( 0, ( new PluralForms( 2, 'n / 0 + n % 0' ) )->index( 3 ) );
	}

	public function test_header_for_locale() : void {
		$this->assertSame( 'nplurals=2; plural=(n > 1);', PluralForms::header_for_locale( Locale::parse( 'pt_BR' ) ) );
		$this->assertSame( 'nplurals=2; plural=(n != 1);', PluralForms::header_for_locale( Locale::parse( 'pt_PT' ) ) );
		$this->assertSame( 'nplurals=1; plural=0;', PluralForms::header_for_locale( Locale::parse( 'zh_CN' ) ) );
		$this->assertSame( PluralForms::DEFAULT_HEADER, PluralForms::header_for_locale( Locale::parse( 'xx' ) ) );
	}
}
