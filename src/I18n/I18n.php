<?php
/**
 * I18n access point class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n;

/**
 * Holds the active translator for the global helper functions.
 *
 * The environment installs the container's translator during bootstrap.
 * Before that (very early errors, unit tests), a source-language translator
 * is used, so the helpers always work and return English.
 */
final class I18n {

	/**
	 * The active translator.
	 */
	private static ?TranslatorInterface $translator = null;

	/**
	 * Text domains, besides the core one, whose JavaScript strings are sent
	 * to the browser.
	 *
	 * @var array<string, true>
	 */
	private static array $script_domains = [];

	/**
	 * Static class.
	 */
	private function __construct() {}

	/**
	 * Register a text domain's translations, for PHP and for JavaScript.
	 *
	 * Extensions and themes call it once when they boot. Both the PHP
	 * catalogs (`{domain}-{locale}.l10n.php`, `.mo`, `.po`) and the
	 * JavaScript catalog (`{domain}-js-{locale}.l10n.php`, written by
	 * `smliser i18n compile`) are read from the directory, and the
	 * JavaScript strings are sent to the browser, so scripts can call
	 * `smliserI18n.__( 'Text', 'my-domain' )`.
	 *
	 * @param string $domain    Text domain, e.g. `acme-invoices`.
	 * @param string $directory Absolute path of the languages directory.
	 */
	public static function add_text_domain( string $domain, string $directory ) : void {
		$translator = self::translator();
		$translator->add_directory( $domain, $directory );
		$translator->add_directory( $domain . '-js', $directory );

		self::add_script_domain( $domain );
	}

	/**
	 * Send a text domain's JavaScript strings to the browser. Its
	 * `{domain}-js` catalog must be registered with the translator
	 * (add_text_domain() does both).
	 *
	 * @param string $domain
	 */
	public static function add_script_domain( string $domain ) : void {
		if ( '' !== $domain && TranslatorInterface::DEFAULT_DOMAIN !== $domain ) {
			self::$script_domains[ $domain ] = true;
		}
	}

	/**
	 * Text domains whose JavaScript strings are sent to the browser, the
	 * core domain first.
	 *
	 * @return array<int, string>
	 */
	public static function script_domains() : array {
		return [ TranslatorInterface::DEFAULT_DOMAIN, ...array_keys( self::$script_domains ) ];
	}

	/**
	 * Install the active translator.
	 */
	public static function set_translator( ?TranslatorInterface $translator ) : void {
		self::$translator = $translator;
	}

	/**
	 * The active translator.
	 */
	public static function translator() : TranslatorInterface {
		return self::$translator ??= new Translator();
	}

	/**
	 * The active locale.
	 */
	public static function locale() : Locale {
		return self::translator()->get_locale();
	}

	/**
	 * Data handed to JavaScript (`smliser_i18n`) so scripts can translate
	 * with the same catalogs and plural rules.
	 *
	 * @param string $domain
	 * @return array{locale: string, lang: string, dir: string, domain: string, plural_forms: string, messages: object}
	 */
	public static function js_data( string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : array {
		$translator = self::translator();
		$locale     = $translator->get_locale();

		return [
			'locale'       => $locale->code(),
			'lang'         => $locale->to_bcp47(),
			'dir'          => $locale->direction(),
			'domain'       => $domain,
			'plural_forms' => $translator->plural_forms( $domain )->header(),
			'messages'     => (object) $translator->messages( $domain ),
		];
	}

	/**
	 * The `smliser_i18n` constant read by assets/js/i18n.js.
	 *
	 * The core domain's strings sit at the top level, as js_data() gives
	 * them. Every other registered script domain that has translations in
	 * the active locale is under `domains`, with its own plural rule.
	 *
	 * @return array{locale: string, lang: string, dir: string, domain: string, plural_forms: string, messages: object, domains: object}
	 */
	public static function script_data() : array {
		$data           = self::js_data( TranslatorInterface::DEFAULT_DOMAIN . '-js' );
		$data['domain'] = TranslatorInterface::DEFAULT_DOMAIN;
		$translator     = self::translator();
		$domains        = [];

		foreach ( array_slice( self::script_domains(), 1 ) as $domain ) {
			try {
				$messages = $translator->messages( $domain . '-js' );

				if ( [] === $messages ) {
					continue;
				}

				$domains[ $domain ] = [
					'plural_forms' => $translator->plural_forms( $domain . '-js' )->header(),
					'messages'     => (object) $messages,
				];
			} catch ( \Throwable $e ) {
				// One broken extension catalog must not take the page's scripts down.
				if ( function_exists( 'smliser_log_error' ) ) {
					smliser_log_error( $e );
				}
			}
		}

		$data['domains'] = (object) $domains;

		return $data;
	}

	/**
	 * Locales offered to users, as code => native name.
	 *
	 * @return array<string, string>
	 */
	public static function language_options( string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : array {
		$options = [];

		foreach ( self::translator()->available_locales( $domain ) as $code ) {
			$options[ $code ] = Locale::parse( $code )->native_name();
		}

		asort( $options, SORT_NATURAL | SORT_FLAG_CASE );

		return $options;
	}
}
