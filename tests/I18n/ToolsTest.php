<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\I18n;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\I18n\Locale;
use SmartLicenseServer\I18n\Po\PoDocument;
use SmartLicenseServer\I18n\Tools\PoMerger;
use SmartLicenseServer\I18n\Tools\StringExtractor;

final class ToolsTest extends TestCase {

	public function test_extracts_php_calls() : void {
		$code = <<<'PHP'
		<?php
		echo smliser__( 'Plain' );
		echo \smliser__( 'Fully ' . 'qualified' );
		/* translators: %s: name of the user. */
		printf( smliser__( 'Hello, %s.' ), $name );
		echo smliser_x( 'Post', 'verb' );
		echo smliser_n( '%d item', '%d items', $count );
		echo smliser_esc_html__( "Escaped \"quote\"\n" );
		echo __( 'WordPress style', 'smliser' );
		echo __( 'Other domain', 'my-extension' );
		echo smliser__( $variable );
		echo $object->__( 'Method call' );
		echo smliser__( sprintf( 'Nested %s', $x ) );
		PHP;

		$extractor = new StringExtractor( 'smliser' );
		$document  = new PoDocument();
		$extractor->extract_php( $code, 'src/Example.php', $document );

		$this->assertSame(
			[ 'Plain', 'Fully qualified', 'Hello, %s.', "verb\x04Post", '%d item', "Escaped \"quote\"\n", 'WordPress style' ],
			array_keys( $document->entries )
		);

		$hello = $document->entries['Hello, %s.'];
		$this->assertSame( [ 'translators: %s: name of the user.' ], $hello->extracted_comments );
		$this->assertSame( [ 'src/Example.php:5' ], $hello->references );
		$this->assertContains( 'php-format', $hello->flags );
		$this->assertSame( '%d items', $document->entries['%d item']->plural );
		$this->assertCount( 2, $extractor->warnings(), 'variable and nested calls are reported' );
	}

	public function test_extracts_context_and_noop_plural_calls() : void {
		$code = <<<'PHP'
		<?php
		echo smliser_esc_attr_x( 'Title', 'attribute' );
		smliser_ex( 'Open', 'verb' );
		_ex( 'Close', 'verb' );
		echo esc_html_x( 'Draft', 'status' );
		echo esc_attr_x( 'Name', 'field' );
		$a = smliser_n_noop( '%d site', '%d sites' );
		$b = smliser_nx_noop( '%d seat', '%d seats', 'license' );
		$c = _n_noop( '%d file', '%d files', 'smliser' );
		$d = _nx_noop( '%d user', '%d users', 'team', 'smliser' );
		$e = _n_noop( '%d other', '%d others', 'my-extension' );
		PHP;

		$extractor = new StringExtractor( 'smliser' );
		$document  = new PoDocument();
		$extractor->extract_php( $code, 'src/Example.php', $document );

		$this->assertEqualsCanonicalizing(
			[ "attribute\x04Title", "verb\x04Open", "verb\x04Close", "status\x04Draft", "field\x04Name", '%d site', "license\x04%d seat", '%d file', "team\x04%d user" ],
			array_keys( $document->entries )
		);
		$this->assertSame( '%d seats', $document->entries["license\x04%d seat"]->plural );
		$this->assertSame( '%d sites', $document->entries['%d site']->plural );
		$this->assertSame( [], $extractor->warnings() );
	}

	public function test_extracts_js_calls() : void {
		$code = <<<'JS'
		const { __, _n } = smliserI18n;
		toast( __( 'Saved.' ) );
		// translators: %d: number of files.
		label = smliserI18n._n( '%d file', "%d files", count );
		other = smliserI18n._x( 'Open', 'verb' );
		bad = __( `Hi ${name}` );
		function __( text ) { return text; }
		JS;

		$document  = new PoDocument();
		$extractor = new StringExtractor( 'smliser' );
		$extractor->extract_js( $code, 'assets/js/app.js', $document );

		$this->assertSame( [ 'Saved.', '%d file', "verb\x04Open" ], array_keys( $document->entries ) );
		$this->assertSame( [ 'translators: %d: number of files.' ], $document->entries['%d file']->extracted_comments );
		$this->assertContains( 'javascript-format', $document->entries['%d file']->flags );
		$this->assertCount( 1, $extractor->warnings() );
	}

	public function test_po_round_trip() : void {
		$document = PoDocument::from_file( __DIR__ . '/fixtures/smliser-fr_FR.po' );
		$again    = PoDocument::parse( $document->to_string() );

		$this->assertEquals( $document->headers, $again->headers );
		$this->assertEquals( $document->entries, $again->entries );
	}

	public function test_init_and_merge() : void {
		$pot = new PoDocument( [ 'POT-Creation-Date' => '2026-01-01 00:00+0000' ] );
		( new StringExtractor() )->extract_php( "<?php smliser__( 'Save changes' ); smliser__( 'New' ); smliser_n( '%d license', '%d licenses', 1 );", 'a.php', $pot );

		$merger = new PoMerger();
		$po     = $merger->init( $pot, Locale::parse( 'ru_RU' ) );

		$this->assertSame( 'ru_RU', $po->headers['Language'] );
		$this->assertCount( 3, $po->entries['%d license']->translations, 'Russian has three forms' );

		$existing = PoDocument::from_file( __DIR__ . '/fixtures/smliser-fr_FR.po' );
		$merged   = $merger->merge( $pot, $existing );
		$stats    = PoMerger::stats( $merged );

		$this->assertSame( 'Enregistrer les modifications', $merged->entries['Save changes']->translations[0] );
		$this->assertArrayNotHasKey( 'Delete', $merged->entries, 'obsolete strings dropped' );
		$this->assertSame( [ 'total' => 3, 'translated' => 2, 'fuzzy' => 0, 'percent' => 66.7 ], $stats );
	}
}
