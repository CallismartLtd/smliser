<?php
/**
 * WordPress translator bridge class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n;

/**
 * Translator used when the application runs as a WordPress plugin.
 *
 * Delegates to WordPress' own gettext stack, so translations from
 * translate.wordpress.org, `wp-content/languages/plugins/` and Loco
 * Translate all work, and `gettext` filters apply. The plugin's bundled
 * `languages/` directory is registered as a fallback.
 */
class WPTranslator implements TranslatorInterface {

	/**
	 * Registered directories per domain, highest priority first.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $directories = [];

	/**
	 * Whether the locale-change hook is registered.
	 */
	private bool $hooked = false;

	/**
	 * {@inheritdoc}
	 */
	public function translate( string $text, string $domain = self::DEFAULT_DOMAIN, ?string $context = null ) : string {
		if ( null === $context || '' === $context ) {
			return \translate( $text, $domain );
		}

		return \translate_with_gettext_context( $text, $context, $domain );
	}

	/**
	 * {@inheritdoc}
	 */
	public function translate_plural( string $singular, string $plural, int $count, string $domain = self::DEFAULT_DOMAIN, ?string $context = null ) : string {
		if ( null === $context || '' === $context ) {
			return \_n( $singular, $plural, $count, $domain ); // phpcs:ignore WordPress.WP.I18n
		}

		return \_nx( $singular, $plural, $count, $context, $domain ); // phpcs:ignore WordPress.WP.I18n
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_locale() : Locale {
		$code = function_exists( 'determine_locale' ) ? \determine_locale() : \get_locale();

		return Locale::try_parse( $code ) ?? Locale::default();
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_locale( Locale|string $locale ) : void {
		$code = $locale instanceof Locale ? $locale->code() : Locale::parse( $locale )->code();

		if ( $code !== $this->get_locale()->code() ) {
			\switch_to_locale( $code );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function add_directory( string $domain, string $directory ) : void {
		$directory = rtrim( $directory, '/\\' );

		$this->directories[ $domain ] = array_values( array_unique( [ $directory, ...( $this->directories[ $domain ] ?? [] ) ] ) );

		if ( ! $this->hooked ) {
			$this->hooked = true;

			// Reload when WordPress switches locale (switch_to_locale(), user locale).
			\add_action( 'change_locale', [ $this, 'load_all' ] );

			// The user's locale is only known once WordPress has initialized.
			if ( ! \did_action( 'init' ) ) {
				\add_action( 'init', [ $this, 'load_all' ], 0 );
				return;
			}
		}

		if ( \did_action( 'init' ) ) {
			$this->load_domain( $domain );
		}
	}

	/**
	 * Load every registered domain for the current locale.
	 *
	 * @internal Hooked to `init` and `change_locale`.
	 */
	public function load_all() : void {
		foreach ( array_keys( $this->directories ) as $domain ) {
			$this->load_domain( $domain );
		}
	}

	/**
	 * Load a domain's translations for the current locale. Files in
	 * `WP_LANG_DIR/plugins/` win over bundled ones.
	 */
	private function load_domain( string $domain ) : void {
		$locale = $this->get_locale();

		\unload_textdomain( $domain, true );

		$dirs = $this->directories[ $domain ] ?? [];

		if ( defined( 'WP_LANG_DIR' ) ) {
			array_unshift( $dirs, rtrim( (string) \WP_LANG_DIR, '/\\' ) . '/plugins' );
		}

		foreach ( $dirs as $dir ) {
			foreach ( $locale->fallback_chain() as $code ) {
				// WordPress 6.5+ prefers a sibling .l10n.php automatically.
				$mo = sprintf( '%s/%s-%s.mo', $dir, $domain, $code );

				if ( is_file( $mo ) || is_file( substr( $mo, 0, -3 ) . '.l10n.php' ) ) {
					\load_textdomain( $domain, $mo, $locale->code() );
					break;
				}
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function available_locales( string $domain = self::DEFAULT_DOMAIN ) : array {
		$locales = [ Locale::DEFAULT ];

		foreach ( $this->directories[ $domain ] ?? [] as $dir ) {
			foreach ( @scandir( $dir ) ?: [] as $file ) {
				if ( preg_match( '/^' . preg_quote( $domain, '/' ) . '-([A-Za-z0-9_]+)\.(?:mo|l10n\.php)$/', $file, $m ) && Locale::is_valid( $m[1] ) ) {
					$locales[] = Locale::parse( $m[1] )->code();
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
		$translations = \get_translations_for_domain( $domain );
		$headers      = is_object( $translations ) && isset( $translations->headers ) && is_array( $translations->headers ) ? $translations->headers : [];

		return PluralForms::from_header_or_default( $headers['Plural-Forms'] ?? null );
	}

	/**
	 * {@inheritdoc}
	 */
	public function messages( string $domain = self::DEFAULT_DOMAIN ) : array {
		$translations = \get_translations_for_domain( $domain );
		$entries      = is_object( $translations ) && isset( $translations->entries ) && is_array( $translations->entries ) ? $translations->entries : [];
		$messages     = [];

		foreach ( $entries as $entry ) {
			if ( ! is_object( $entry ) || empty( $entry->translations ) ) {
				continue;
			}

			$messages[ Catalog::key( (string) $entry->singular, $entry->context ?? null ) ] = array_map( 'strval', (array) $entry->translations );
		}

		return $messages;
	}
}
