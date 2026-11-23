<?php
/**
 * Translator class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n;

use SmartLicenseServer\I18n\Loaders\CatalogLoaderInterface;
use SmartLicenseServer\I18n\Loaders\MoFileLoader;
use SmartLicenseServer\I18n\Loaders\PhpFileLoader;
use SmartLicenseServer\I18n\Loaders\PoFileLoader;
use Throwable;

/**
 * The standalone translator.
 *
 * Catalogs are loaded lazily, the first time a domain is used in a locale,
 * from the directories registered for that domain. For each directory
 * (newest first) and each locale in the fallback chain (`pt_BR` → `pt`) the
 * first existing file wins, in this order of formats: `.l10n.php`, `.mo`,
 * `.po`. Catalogs found in several places are merged, higher priority
 * first, so a partial override still falls back to the bundled strings.
 *
 * Untranslated strings are returned unchanged (English).
 */
class Translator implements TranslatorInterface {

	/**
	 * The active locale.
	 */
	private Locale $locale;

	/**
	 * Translation directories per domain, highest priority first.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $directories = [];

	/**
	 * Loaded catalogs: [locale][domain] => Catalog.
	 *
	 * @var array<string, array<string, Catalog>>
	 */
	private array $catalogs = [];

	/**
	 * Loaders in order of preference.
	 *
	 * @var array<int, CatalogLoaderInterface>
	 */
	private array $loaders;

	/**
	 * Listener for strings without a translation.
	 *
	 * @var (callable(string, string, string): void)|null
	 */
	private $missing_listener = null;

	/**
	 * Constructor.
	 *
	 * @param Locale|string                            $locale  Initial locale.
	 * @param array<int, CatalogLoaderInterface>|null  $loaders Loaders, most preferred first.
	 */
	public function __construct( Locale|string $locale = Locale::DEFAULT, ?array $loaders = null ) {
		$this->locale  = $locale instanceof Locale ? $locale : ( Locale::try_parse( $locale ) ?? Locale::default() );
		$this->loaders = $loaders ?? [ new PhpFileLoader(), new MoFileLoader(), new PoFileLoader() ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function translate( string $text, string $domain = self::DEFAULT_DOMAIN, ?string $context = null ) : string {
		if ( '' === $text ) {
			return $text;
		}

		$translation = $this->catalog( $domain )->get( $text, $context );

		if ( null === $translation ) {
			$this->report_missing( $text, $domain );
			return $text;
		}

		return $translation;
	}

	/**
	 * {@inheritdoc}
	 */
	public function translate_plural( string $singular, string $plural, int $count, string $domain = self::DEFAULT_DOMAIN, ?string $context = null ) : string {
		$translation = $this->catalog( $domain )->get_plural( $singular, $count, $context );

		if ( null !== $translation ) {
			return $translation;
		}

		$this->report_missing( $singular, $domain );

		return 1 === abs( $count ) ? $singular : $plural;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_locale() : Locale {
		return $this->locale;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_locale( Locale|string $locale ) : void {
		$this->locale = $locale instanceof Locale ? $locale : Locale::parse( $locale );
	}

	/**
	 * Run a callback with a different locale active, then restore the
	 * previous one. Use it to render emails in the recipient's language.
	 *
	 * @template T
	 * @param Locale|string $locale
	 * @param callable(): T $callback
	 * @return T
	 */
	public function with_locale( Locale|string $locale, callable $callback ) : mixed {
		$previous = $this->locale;
		$this->set_locale( $locale );

		try {
			return $callback();
		} finally {
			$this->locale = $previous;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function add_directory( string $domain, string $directory ) : void {
		$directory = rtrim( $directory, '/\\' );

		$existing = $this->directories[ $domain ] ?? [];
		$existing = array_values( array_filter( $existing, static fn ( string $d ) : bool => $d !== $directory ) );

		array_unshift( $existing, $directory );

		$this->directories[ $domain ] = $existing;

		// Drop cached catalogs of this domain so the new directory is seen.
		foreach ( $this->catalogs as $locale => $domains ) {
			unset( $this->catalogs[ $locale ][ $domain ] );
		}
	}

	/**
	 * Directories registered for a domain, highest priority first.
	 *
	 * @return array<int, string>
	 */
	public function directories( string $domain = self::DEFAULT_DOMAIN ) : array {
		return $this->directories[ $domain ] ?? [];
	}

	/**
	 * Add a catalog for a locale programmatically. It takes precedence over
	 * file-based translations.
	 */
	public function add_catalog( Catalog $catalog, string $domain = self::DEFAULT_DOMAIN, Locale|string|null $locale = null ) : void {
		$code = ( null === $locale ) ? $this->locale->code() : ( $locale instanceof Locale ? $locale : Locale::parse( $locale ) )->code();

		$this->catalogs[ $code ][ $domain ] = isset( $this->catalogs[ $code ][ $domain ] )
			? $catalog->merge( $this->catalogs[ $code ][ $domain ] )
			: $catalog->merge( $this->load_catalog( $domain, Locale::parse( $code ) ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function available_locales( string $domain = self::DEFAULT_DOMAIN ) : array {
		$locales = [ Locale::DEFAULT ];

		// Only compiled catalogs make a language available: a .po file may be
		// an untranslated starter, and offering it would show English.
		$compiled = array_filter( $this->loaders, static fn ( CatalogLoaderInterface $l ) : bool => 'po' !== $l->extension() );
		$pattern  = '/^' . preg_quote( $domain, '/' ) . '-([A-Za-z0-9_]+)\.(?:' . implode( '|', array_map( static fn ( CatalogLoaderInterface $l ) : string => preg_quote( $l->extension(), '/' ), $compiled ) ) . ')$/';

		foreach ( $this->directories( $domain ) as $directory ) {
			foreach ( @scandir( $directory ) ?: [] as $file ) {
				if ( preg_match( $pattern, $file, $m ) && null !== ( $locale = Locale::try_parse( $m[1] ) ) ) {
					$locales[] = $locale->code();
				}
			}
		}

		$locales = array_values( array_unique( $locales ) );
		sort( $locales );

		return $locales;
	}

	/**
	 * {@inheritdoc}
	 */
	public function plural_forms( string $domain = self::DEFAULT_DOMAIN ) : PluralForms {
		return $this->catalog( $domain )->plural_forms();
	}

	/**
	 * {@inheritdoc}
	 */
	public function messages( string $domain = self::DEFAULT_DOMAIN ) : array {
		return $this->catalog( $domain )->entries();
	}

	/**
	 * Register a listener for strings that have no translation in the
	 * active locale; useful in development to find gaps.
	 *
	 * @param (callable(string $text, string $domain, string $locale): void)|null $listener
	 */
	public function on_missing( ?callable $listener ) : void {
		$this->missing_listener = $listener;
	}

	/**
	 * The catalog of a domain in the active locale.
	 */
	public function catalog( string $domain = self::DEFAULT_DOMAIN ) : Catalog {
		$code = $this->locale->code();

		return $this->catalogs[ $code ][ $domain ] ??= $this->load_catalog( $domain, $this->locale );
	}

	/**
	 * Load and merge every file of a domain for a locale.
	 */
	protected function load_catalog( string $domain, Locale $locale ) : Catalog {
		$catalog = new Catalog();

		foreach ( $this->directories( $domain ) as $directory ) {
			foreach ( $locale->fallback_chain() as $code ) {
				$found = $this->load_file( $directory, $domain, $code );

				if ( null !== $found ) {
					$catalog = $catalog->merge( $found );
					break;
				}
			}
		}

		return $catalog;
	}

	/**
	 * Load the first file of a domain/locale in a directory.
	 */
	private function load_file( string $directory, string $domain, string $code ) : ?Catalog {
		foreach ( $this->loaders as $loader ) {
			$path = sprintf( '%s/%s-%s.%s', $directory, $domain, $code, $loader->extension() );

			if ( ! is_file( $path ) ) {
				continue;
			}

			try {
				return $loader->load( $path );
			} catch ( Throwable $e ) {
				if ( function_exists( 'smliser_log_error' ) ) {
					smliser_log_error( $e );
				}
			}
		}

		return null;
	}

	/**
	 * Notify the missing-translation listener.
	 */
	private function report_missing( string $text, string $domain ) : void {
		if ( null !== $this->missing_listener && Locale::DEFAULT !== $this->locale->code() ) {
			( $this->missing_listener )( $text, $domain, $this->locale->code() );
		}
	}
}
