<?php
/**
 * Internationalization command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\I18n\I18nException;
use SmartLicenseServer\I18n\Locale;
use SmartLicenseServer\I18n\Po\PoDocument;
use SmartLicenseServer\I18n\Tools\CatalogCompiler;
use SmartLicenseServer\I18n\Tools\PoMerger;
use SmartLicenseServer\I18n\Tools\StringExtractor;
use SmartLicenseServer\I18n\TranslatorInterface;

/**
 * Manage translations.
 *
 * Usage:
 *   smliser i18n make-pot              Extract strings into languages/smliser.pot.
 *   smliser i18n init <locale>         Start a translation, e.g. fr_FR.
 *   smliser i18n update [locale]       Merge the template into translations.
 *   smliser i18n compile [locale]      Build .l10n.php/.mo files from .po files.
 *   smliser i18n status                Show translation progress.
 *   smliser i18n help
 */
class I18nCommand extends AbstractCommand {

	/**
	 * Constructor.
	 */
	public function __construct(
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		parent::__construct( $io, $output, $script_name );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function name() : string {
		return 'i18n';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description() : string {
		return 'Extract, create, update and compile translations.';
	}

	/**
	 * {@inheritdoc}
	 */
	public function help() : string {
		$prefix = $this->command_prefix();

		return implode( PHP_EOL, [
			'Subcommands:',
			'  make-pot                Scan the source code and write the translation template (.pot).',
			'  init <locale>           Create a translation file for a language from the template.',
			'  update [locale]         Add new strings to translation files and drop removed ones.',
			'  compile [locale]        Compile .po files into .l10n.php, .mo and JavaScript catalogs.',
			'  status                  Show how complete each translation is.',
			'  help                    Show this help message.',
			'',
			'OPTIONS',
			'   --domain=<domain>      Text domain (default: smliser). Extensions use their own.',
			'   --source=<dir>         Directory to scan for make-pot; repeatable as a comma list.',
			'   --dir=<dir>            Directory holding the .pot/.po files (default: <app>/languages).',
			'',
			'Workflow:',
			"  {$prefix} make-pot",
			"  {$prefix} init fr_FR          # then translate languages/smliser-fr_FR.po (Poedit, Weblate, ...)",
			"  {$prefix} compile fr_FR",
			'',
			'Sites can drop their own .po/.mo/.l10n.php files into <storage>/languages; they override bundled ones.',
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_subcommands() : array {
		return [
			'make-pot' => [ $this, 'handle_make_pot' ],
			'init'     => [ $this, 'handle_init' ],
			'update'   => [ $this, 'handle_update' ],
			'compile'  => [ $this, 'handle_compile' ],
			'status'   => [ $this, 'handle_status' ],
			'help'     => [ $this, 'handle_help' ],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function run( CommandInput $input ) : int {
		return $this->handle_status( $input );
	}

	/*
	|--------------------------------------------
	| SUBCOMMAND HANDLERS
	|--------------------------------------------
	*/

	/**
	 * Extract translatable strings into the template.
	 */
	public function handle_make_pot( CommandInput $input ) : int {
		$domain  = $this->domain( $input );
		$root    = rtrim( \SMLISER_RUNTIME_DIR, '/\\' );
		$sources = $this->list_option( $input, 'source' ) ?: [
			$root . '/src',
			$root . '/templates',
			$root . '/themes/default',
			$root . '/assets/js',
		];

		$template = new PoDocument( [
			'Project-Id-Version'        => sprintf( '%s %s', \SMLISER_APP_NAME, \SMLISER_VER ),
			'Report-Msgid-Bugs-To'      => 'https://github.com/callismartltd/smliser/issues',
			'POT-Creation-Date'         => gmdate( 'Y-m-d H:iO' ),
			'MIME-Version'              => '1.0',
			'Content-Type'              => 'text/plain; charset=UTF-8',
			'Content-Transfer-Encoding' => '8bit',
			'Plural-Forms'              => 'nplurals=INTEGER; plural=EXPRESSION;',
			'X-Domain'                  => $domain,
		] );

		$this->start_timer();
		$extractor = new StringExtractor( $domain, $root );
		$extractor->extract( $sources, $template );

		$path = $this->dir( $input ) . '/' . $domain . '.pot';

		try {
			$template->save( $path );
		} catch ( I18nException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		foreach ( $extractor->warnings() as $warning ) {
			$this->output->warning( $warning );
		}

		$this->output->success( sprintf(
			'Extracted %d strings into %s in %s.',
			count( $template->entries ),
			$path,
			$this->elapsed_formatted()
		) );

		return 0;
	}

	/**
	 * Create a translation from the template.
	 */
	public function handle_init( CommandInput $input ) : int {
		$locale = Locale::try_parse( (string) $input->get_argument( 0, '' ) );

		if ( null === $locale ) {
			$this->output->error( sprintf( 'Usage: %s <locale>, e.g. fr_FR or pt_BR.', $this->command_prefix( 'init' ) ) );
			return 1;
		}

		$domain = $this->domain( $input );
		$path   = sprintf( '%s/%s-%s.po', $this->dir( $input ), $domain, $locale->code() );

		if ( is_file( $path ) ) {
			$this->output->error( sprintf( '%s already exists; use `%s` to refresh it.', $path, $this->command_prefix( 'update' ) ) );
			return 1;
		}

		$template = $this->template( $input );

		if ( null === $template ) {
			return 1;
		}

		( new PoMerger() )->init( $template, $locale, \SMLISER_APP_NAME )->save( $path );

		$this->output->success( sprintf( 'Created %s (%s, %d strings).', $path, $locale->native_name(), count( $template->entries ) ) );
		$this->output->writeln( sprintf( 'Translate it with any PO editor, then run `%s %s`.', $this->command_prefix( 'compile' ), $locale->code() ) );

		return 0;
	}

	/**
	 * Merge the template into translations.
	 */
	public function handle_update( CommandInput $input ) : int {
		$template = $this->template( $input );

		if ( null === $template ) {
			return 1;
		}

		$merger = new PoMerger();

		foreach ( $this->po_files( $input ) as $path ) {
			$merged = $merger->merge( $template, PoDocument::from_file( $path ) );
			$merged->save( $path );

			$stats = PoMerger::stats( $merged );
			$this->output->writeln( sprintf( '%s: %d/%d translated (%s%%).', basename( $path ), $stats['translated'], $stats['total'], $stats['percent'] ) );
		}

		return 0;
	}

	/**
	 * Compile translations.
	 */
	public function handle_compile( CommandInput $input ) : int {
		$compiler = new CatalogCompiler();
		$status   = 0;
		$files    = $this->po_files( $input );

		if ( [] === $files ) {
			$this->output->warning( 'No translation files to compile.' );
			return 0;
		}

		foreach ( $files as $path ) {
			try {
				$written = $compiler->compile( $path );
				$this->output->success( sprintf( '%s → %s', basename( $path ), implode( ', ', array_map( 'basename', $written ) ) ) );
			} catch ( I18nException $e ) {
				$this->output->error( $e->getMessage() );
				$status = 1;
			}
		}

		return $status;
	}

	/**
	 * Show translation progress.
	 */
	public function handle_status( CommandInput $input ) : int {
		$rows = [];

		foreach ( $this->po_files( $input, false ) as $path ) {
			$document = PoDocument::from_file( $path );
			$stats    = PoMerger::stats( $document );
			$code     = (string) preg_replace( '/^.+-([A-Za-z0-9_]+)\.po$/', '$1', basename( $path ) );
			$compiled = is_file( substr( $path, 0, -3 ) . '.l10n.php' ) && filemtime( substr( $path, 0, -3 ) . '.l10n.php' ) >= filemtime( $path );

			$rows[] = [
				$code,
				Locale::try_parse( $code )?->native_name() ?? $code,
				sprintf( '%d/%d', $stats['translated'], $stats['total'] ),
				$stats['percent'] . '%',
				(string) $stats['fuzzy'],
				$compiled ? 'yes' : 'no',
			];
		}

		if ( [] === $rows ) {
			$this->output->info( sprintf( 'No translations yet. Start one with `%s fr_FR`.', $this->command_prefix( 'init' ) ) );
			return 0;
		}

		$this->output->table( [ 'Locale', 'Language', 'Translated', 'Progress', 'Fuzzy', 'Compiled' ], $rows );

		return 0;
	}

	/*
	|--------------------------------------------
	| HELPERS
	|--------------------------------------------
	*/

	/**
	 * The text domain option.
	 */
	private function domain( CommandInput $input ) : string {
		$domain = (string) $input->get_option( 'domain', TranslatorInterface::DEFAULT_DOMAIN );

		return preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $domain ) ? $domain : TranslatorInterface::DEFAULT_DOMAIN;
	}

	/**
	 * The languages directory.
	 */
	private function dir( CommandInput $input ) : string {
		$dir = $input->get_option( 'dir' );

		return rtrim( is_string( $dir ) && '' !== $dir ? $dir : \SMLISER_RUNTIME_DIR . 'languages', '/\\' );
	}

	/**
	 * A comma-separated option as a list.
	 *
	 * @return array<int, string>
	 */
	private function list_option( CommandInput $input, string $name ) : array {
		$value = $input->get_option( $name );

		if ( ! is_string( $value ) || '' === $value ) {
			return [];
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}

	/**
	 * Load the template, telling the user how to create it when missing.
	 */
	private function template( CommandInput $input ) : ?PoDocument {
		$path = $this->dir( $input ) . '/' . $this->domain( $input ) . '.pot';

		if ( ! is_file( $path ) ) {
			$this->output->error( sprintf( '%s not found. Run `%s` first.', $path, $this->command_prefix( 'make-pot' ) ) );
			return null;
		}

		return PoDocument::from_file( $path );
	}

	/**
	 * Translation files to act on: the one for the locale argument, or all.
	 *
	 * @return array<int, string>
	 */
	private function po_files( CommandInput $input, bool $use_argument = true ) : array {
		$dir    = $this->dir( $input );
		$domain = $this->domain( $input );
		$locale = $use_argument ? Locale::try_parse( (string) $input->get_argument( 0, '' ) ) : null;

		if ( null !== $locale ) {
			$path = sprintf( '%s/%s-%s.po', $dir, $domain, $locale->code() );

			if ( ! is_file( $path ) ) {
				$this->output->error( sprintf( '%s not found.', $path ) );
				return [];
			}

			return [ $path ];
		}

		$files = glob( sprintf( '%s/%s-*.po', $dir, $domain ) ) ?: [];

		// `smliser-js-*.po` would belong to another domain.
		return array_values( array_filter( $files, static fn ( string $f ) : bool => (bool) preg_match( '/^' . preg_quote( $domain, '/' ) . '-[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)*\.po$/', basename( $f ) ) ) );
	}
}
