<?php
/**
 * Translator interface file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n;

/**
 * Translates user-facing text into the active locale.
 *
 * Application code should normally use the global helpers (`smliser__()`,
 * `smliser_n()`, `smliser_x()`, ...) which delegate to the active translator.
 */
interface TranslatorInterface {

	/**
	 * The text domain of the core application.
	 */
	public const DEFAULT_DOMAIN = 'smliser';

	/**
	 * Translate a string.
	 *
	 * @param string      $text    Source (English) text.
	 * @param string      $domain  Text domain.
	 * @param string|null $context Disambiguation context (msgctxt).
	 */
	public function translate( string $text, string $domain = self::DEFAULT_DOMAIN, ?string $context = null ) : string;

	/**
	 * Translate a string with plural forms.
	 *
	 * @param string      $singular Source singular text.
	 * @param string      $plural   Source plural text.
	 * @param int         $count    The number that decides the form.
	 * @param string      $domain   Text domain.
	 * @param string|null $context  Disambiguation context (msgctxt).
	 */
	public function translate_plural( string $singular, string $plural, int $count, string $domain = self::DEFAULT_DOMAIN, ?string $context = null ) : string;

	/**
	 * The active locale.
	 */
	public function get_locale() : Locale;

	/**
	 * Change the active locale.
	 */
	public function set_locale( Locale|string $locale ) : void;

	/**
	 * Register a directory that holds translation files for a text domain.
	 *
	 * Files are looked up as `{dir}/{domain}-{locale}.{ext}`. Directories
	 * registered later take precedence, so a site can override bundled
	 * translations.
	 *
	 * @param string $domain    Text domain, e.g. `smliser` or an extension slug.
	 * @param string $directory Absolute path.
	 */
	public function add_directory( string $domain, string $directory ) : void;

	/**
	 * Locales that have a compiled translation file (.l10n.php or .mo) for a
	 * domain, plus the source locale. Uncompiled .po files do not count.
	 *
	 * @return array<int, string> Locale codes.
	 */
	public function available_locales( string $domain = self::DEFAULT_DOMAIN ) : array;

	/**
	 * The plural rule of the active locale for a domain.
	 */
	public function plural_forms( string $domain = self::DEFAULT_DOMAIN ) : PluralForms;

	/**
	 * All translations of a domain in the active locale, keyed by
	 * Catalog::key(). Used to hand strings to JavaScript.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function messages( string $domain = self::DEFAULT_DOMAIN ) : array;
}
