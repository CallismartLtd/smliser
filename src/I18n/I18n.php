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
	 * Static class.
	 */
	private function __construct() {}

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
