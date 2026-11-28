<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\I18n;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\I18n\Catalog;
use SmartLicenseServer\I18n\I18n;
use SmartLicenseServer\I18n\Loaders\MoFileLoader;
use SmartLicenseServer\I18n\Loaders\PhpFileLoader;
use SmartLicenseServer\I18n\Po\PoDocument;
use SmartLicenseServer\I18n\Tools\CatalogCompiler;
use SmartLicenseServer\I18n\Translator;

final class TranslatorTest extends TestCase {

	private string $tmp;

	protected function setUp() : void {
		$this->tmp = sys_get_temp_dir() . '/smliser-i18n-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->tmp );
	}

	protected function tearDown() : void {
		foreach ( glob( $this->tmp . '/*' ) ?: [] as $file ) {
			unlink( $file );
		}
		rmdir( $this->tmp );
		I18n::set_translator( null );
	}

	private function translator( string $locale = 'fr_FR' ) : Translator {
		$translator = new Translator( $locale );
		$translator->add_directory( 'smliser', __DIR__ . '/fixtures' );
		return $translator;
	}

	public function test_source_locale_returns_text_unchanged() : void {
		$translator = $this->translator( 'en_US' );
		$this->assertSame( 'Save changes', $translator->translate( 'Save changes' ) );
		$this->assertSame( '%d licenses', $translator->translate_plural( '%d license', '%d licenses', 2 ) );
		$this->assertSame( '%d license', $translator->translate_plural( '%d license', '%d licenses', 1 ) );
	}

	public function test_translates_from_po() : void {
		$t = $this->translator();

		$this->assertSame( 'Enregistrer les modifications', $t->translate( 'Save changes' ) );
		$this->assertSame( 'Publier', $t->translate( 'Post', 'smliser', 'verb' ) );
		$this->assertSame( 'Article', $t->translate( 'Post', 'smliser', 'noun' ) );
		$this->assertSame( "Ligne un\nLigne \"deux\"", $t->translate( "Line one\nLine \"two\"" ) );
	}

	public function test_plural_forms_follow_the_language() : void {
		$t = $this->translator();

		// French uses the singular for 0 and 1.
		$this->assertSame( '%d licence', $t->translate_plural( '%d license', '%d licenses', 0 ) );
		$this->assertSame( '%d licence', $t->translate_plural( '%d license', '%d licenses', 1 ) );
		$this->assertSame( '%d licences', $t->translate_plural( '%d license', '%d licenses', 5 ) );
	}

	public function test_fuzzy_and_empty_entries_fall_back_to_source() : void {
		$t = $this->translator();
		$this->assertSame( 'Delete', $t->translate( 'Delete' ) );
		$this->assertSame( 'Untranslated', $t->translate( 'Untranslated' ) );
		$this->assertSame( 'Unknown', $t->translate( 'Unknown' ) );
	}

	public function test_regional_catalog_falls_back_to_language_catalog() : void {
		// fr_CA has no file: uses smliser-fr.po.
		$t = $this->translator( 'fr_CA' );
		$this->assertSame( 'Sauvegarder', $t->translate( 'Save changes' ) );
	}

	public function test_override_directory_wins_and_falls_back() : void {
		file_put_contents( $this->tmp . '/smliser-fr_FR.po', "msgid \"Save changes\"\nmsgstr \"Sauver\"\n" );

		$t = $this->translator();
		$t->add_directory( 'smliser', $this->tmp );

		$this->assertSame( 'Sauver', $t->translate( 'Save changes' ) );
		$this->assertSame( 'Bonjour, %s.', $t->translate( 'Hello, %s.' ), 'bundled string still used' );
	}

	public function test_domains_are_isolated() : void {
		$t = $this->translator();
		$this->assertSame( 'Save changes', $t->translate( 'Save changes', 'my-extension' ) );
	}

	public function test_with_locale_restores_previous_locale() : void {
		$t      = $this->translator( 'en_US' );
		$inside = $t->with_locale( 'fr_FR', fn () => $t->translate( 'Save changes' ) );

		$this->assertSame( 'Enregistrer les modifications', $inside );
		$this->assertSame( 'en_US', $t->get_locale()->code() );
	}

	public function test_available_locales_counts_only_compiled_catalogs() : void {
		$this->assertSame( [ 'en_US' ], $this->translator()->available_locales(), '.po files alone do not offer a language' );

		copy( __DIR__ . '/fixtures/smliser-fr_FR.po', $this->tmp . '/smliser-fr_FR.po' );
		( new CatalogCompiler() )->compile( $this->tmp . '/smliser-fr_FR.po' );

		$translator = $this->translator();
		$translator->add_directory( 'smliser', $this->tmp );

		$this->assertSame( [ 'en_US', 'fr_FR' ], $translator->available_locales() );
	}

	public function test_missing_listener() : void {
		$t       = $this->translator();
		$missing = [];
		$t->on_missing( function ( string $text ) use ( &$missing ) : void {
			$missing[] = $text;
		} );

		$t->translate( 'Save changes' );
		$t->translate( 'Nope' );

		$this->assertSame( [ 'Nope' ], $missing );
	}

	public function test_add_catalog() : void {
		$t = $this->translator();
		$t->add_catalog( new Catalog( [ 'Save changes' => [ 'Valider' ] ] ) );

		$this->assertSame( 'Valider', $t->translate( 'Save changes' ) );
		$this->assertSame( 'Bonjour, %s.', $t->translate( 'Hello, %s.' ) );
	}

	public function test_compiled_formats_match_po() : void {
		copy( __DIR__ . '/fixtures/smliser-fr_FR.po', $this->tmp . '/smliser-fr_FR.po' );
		$written = ( new CatalogCompiler() )->compile( $this->tmp . '/smliser-fr_FR.po' );

		$this->assertCount( 2, $written );

		$expected = PoDocument::from_file( $this->tmp . '/smliser-fr_FR.po' )->to_catalog()->entries();
		$php      = ( new PhpFileLoader() )->load( $this->tmp . '/smliser-fr_FR.l10n.php' );
		$mo       = ( new MoFileLoader() )->load( $this->tmp . '/smliser-fr_FR.mo' );

		$this->assertEquals( $expected, $php->entries() );
		$this->assertEquals( $expected, $mo->entries() );
		$this->assertSame( 'nplurals=2; plural=(n > 1);', $mo->header( 'Plural-Forms' ) );
		$this->assertSame( 'Publier', $php->get( 'Post', 'verb' ) );
		$this->assertSame( '%d licences', $mo->get_plural( '%d license', 3 ) );
	}

	public function test_mo_compatible_with_msgfmt_when_available() : void {
		if ( ! is_executable( '/usr/bin/msgfmt' ) ) {
			$this->markTestSkipped( 'msgfmt not installed.' );
		}

		exec( sprintf( '/usr/bin/msgfmt -o %s %s', escapeshellarg( $this->tmp . '/x.mo' ), escapeshellarg( __DIR__ . '/fixtures/smliser-fr_FR.po' ) ) );
		$mo = ( new MoFileLoader() )->load( $this->tmp . '/x.mo' );

		$this->assertSame( 'Article', $mo->get( 'Post', 'noun' ) );
	}

	public function test_global_helpers() : void {
		I18n::set_translator( $this->translator() );

		$this->assertSame( 'Enregistrer les modifications', smliser__( 'Save changes' ) );
		$this->assertSame( 'Enregistrer les modifications', smliser__( 'Save changes', 'default' ) );
		$this->assertSame( 'Article', smliser_x( 'Post', 'noun' ) );
		$this->assertSame( '3 licences', sprintf( smliser_n( '%d license', '%d licenses', 3 ), 3 ) );
		$this->assertSame( 'lang="fr-FR" dir="ltr"', smliser_language_attributes() );
		$this->assertSame( 'Save changes', smliser_with_locale( 'en_US', fn () => smliser__( 'Save changes' ) ) );
		$this->assertSame( 'fr_FR', smliser_get_locale() );
	}

	public function test_context_and_noop_plural_helpers() : void {
		I18n::set_translator( $this->translator() );

		$this->assertSame( 'Article', smliser_esc_attr_x( 'Post', 'noun' ) );
		$this->assertSame( 'Article', esc_attr_x( 'Post', 'noun' ) );
		$this->assertSame( 'Article', esc_html_x( 'Post', 'noun' ) );

		ob_start();
		smliser_ex( 'Post', 'noun' );
		_ex( 'Post', 'noun' );
		$this->assertSame( 'ArticleArticle', ob_get_clean() );

		$nooped = smliser_n_noop( '%d license', '%d licenses' );
		$this->assertSame( '%d license', $nooped['singular'] );
		$this->assertSame( '3 licences', sprintf( smliser_translate_nooped_plural( $nooped, 3 ), 3 ) );
		$this->assertSame( '3 licences', sprintf( translate_nooped_plural( _n_noop( '%d license', '%d licenses' ), 3 ), 3 ) );
		$this->assertSame( 'Untranslated items', smliser_translate_nooped_plural( smliser_nx_noop( 'Untranslated item', 'Untranslated items', 'ctx' ), 2 ) );
		$this->assertSame( 'ctx', _nx_noop( 'a', 'b', 'ctx', 'my-ext' )['context'] );
		$this->assertSame( 'my-ext', _nx_noop( 'a', 'b', 'ctx', 'my-ext' )['domain'] );
	}

	public function test_hosted_app_type_labels_are_translated() : void {
		$translator = new Translator( 'fr_FR' );
		$translator->add_catalog( new Catalog( [
			'WordPress Plugin'                    => [ 'Extension WordPress' ],
			'WordPress Plugins'                   => [ 'Extensions WordPress' ],
			'Upload plugins for WordPress sites.' => [ 'Téléversez des extensions pour les sites WordPress.' ],
		] ) );
		I18n::set_translator( $translator );

		$registry = HostedAppsRegistry::instance( new Container() );

		$this->assertSame( [ 'singular' => 'Extension WordPress', 'plural' => 'Extensions WordPress' ], $registry->get_app_type_labels( 'wpplugin' ) );
		$this->assertSame( 'Extensions WordPress', $registry->get_app_type_label( 'wpplugin', true ) );
		$this->assertSame( 'Téléversez des extensions pour les sites WordPress.', $registry->get_app_type_description( 'wpplugin' ) );
		$this->assertSame( 'Software', $registry->get_app_type_label( 'software' ), 'untranslated labels fall back to English' );
		$this->assertNull( $registry->get_app_type_description( 'not-registered' ) );
		$this->assertSame( 'WordPress Plugin', smliser_with_locale( 'en_US', fn () => $registry->get_app_type_label( 'wpplugin' ) ) );
	}

	public function test_script_data_includes_extension_domains() : void {
		file_put_contents( $this->tmp . '/acme-script-test-fr_FR.po', implode( "\n", [
			'msgid ""',
			'msgstr ""',
			'"Plural-Forms: nplurals=2; plural=(n > 1);\n"',
			'',
			'#: assets/acme.js:3',
			'msgid "Invoice sent."',
			'msgstr "Facture envoyée."',
			'',
			'#: src/Acme.php:9',
			'msgid "Server only."',
			'msgstr "Serveur uniquement."',
			'',
		] ) );
		( new CatalogCompiler() )->compile( $this->tmp . '/acme-script-test-fr_FR.po' );

		I18n::set_translator( $this->translator() );
		I18n::add_text_domain( 'acme-script-test', $this->tmp );
		I18n::add_script_domain( 'acme-script-empty' );

		$this->assertSame( 'Serveur uniquement.', smliser__( 'Server only.', 'acme-script-test' ) );
		$this->assertContains( 'acme-script-test', I18n::script_domains() );
		$this->assertSame( 'smliser', I18n::script_domains()[0] );

		$data    = I18n::script_data();
		$domains = (array) $data['domains'];

		$this->assertSame( 'smliser', $data['domain'] );
		$this->assertArrayHasKey( 'acme-script-test', $domains );
		$this->assertArrayNotHasKey( 'acme-script-empty', $domains, 'domains without translations are left out' );
		$this->assertSame( [ 'Invoice sent.' => [ 'Facture envoyée.' ] ], (array) $domains['acme-script-test']['messages'], 'only strings used by scripts are sent' );
		$this->assertSame( 'nplurals=2; plural=(n > 1);', $domains['acme-script-test']['plural_forms'] );
	}

	public function test_js_data() : void {
		I18n::set_translator( $this->translator() );
		$data = I18n::js_data();

		$this->assertSame( 'fr_FR', $data['locale'] );
		$this->assertSame( 'nplurals=2; plural=(n > 1);', $data['plural_forms'] );
	}
}
