<?php
/**
 * Internationalization (i18n) helper functions.
 *
 * Wrap every user-facing string in one of these so it can be translated:
 *
 *     smliser__( 'License activated.' );
 *     sprintf( smliser__( 'Hello, %s.' ), $name );
 *     sprintf( smliser_n( '%d license', '%d licenses', $count ), $count );
 *     smliser_x( 'Post', 'verb: publish a message' );
 *     smliser_esc_html_e( 'Save changes' );
 *
 * Rules that keep strings translatable:
 *  - Pass literal strings, never variables: `smliser__( $label )` cannot be
 *    extracted into the translation template.
 *  - Use placeholders, never concatenation: sentence order differs between
 *    languages. Use numbered placeholders (`%1$s ... %2$s`) when a string
 *    has more than one.
 *  - Add a `translators:` comment above strings with placeholders.
 *  - Extensions and themes pass their own text domain.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Utils\functions
 * @since   0.5.0
 */

use SmartLicenseServer\I18n\I18n;
use SmartLicenseServer\I18n\Locale;
use SmartLicenseServer\I18n\Translator;
use SmartLicenseServer\I18n\TranslatorInterface;
use SmartLicenseServer\I18n\WPTranslator;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\SettingsAPI\UserSettings;

/**
 * Normalize WordPress' `default` domain to the application's domain.
 *
 * @param string $domain
 * @return string
 */
function smliser_i18n_domain( string $domain ) : string {
	return ( '' === $domain || 'default' === $domain ) ? TranslatorInterface::DEFAULT_DOMAIN : $domain;
}

/**
 * Translate text.
 *
 * @param string $text   Source (English) text.
 * @param string $domain Text domain.
 * @return string
 */
function smliser__( string $text, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	return I18n::translator()->translate( $text, smliser_i18n_domain( $domain ) );
}

/**
 * Echo translated text (not escaped; prefer smliser_esc_html_e() in HTML).
 *
 * @param string $text
 * @param string $domain
 * @return void
 */
function smliser_e( string $text, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : void {
	echo smliser__( $text, $domain ); // phpcs:ignore
}

/**
 * Translate text that needs a context to be unambiguous, e.g. "Post" as a
 * noun vs a verb.
 *
 * @param string $text
 * @param string $context Explanation for translators; also part of the lookup key.
 * @param string $domain
 * @return string
 */
function smliser_x( string $text, string $context, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	return I18n::translator()->translate( $text, smliser_i18n_domain( $domain ), $context );
}

/**
 * Translate text with plural forms. The count only selects the form; insert
 * it with sprintf().
 *
 * @param string $singular
 * @param string $plural
 * @param int    $count
 * @param string $domain
 * @return string
 */
function smliser_n( string $singular, string $plural, int $count, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	return I18n::translator()->translate_plural( $singular, $plural, $count, smliser_i18n_domain( $domain ) );
}

/**
 * Translate text with plural forms and a context.
 *
 * @param string $singular
 * @param string $plural
 * @param int    $count
 * @param string $context
 * @param string $domain
 * @return string
 */
function smliser_nx( string $singular, string $plural, int $count, string $context, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	return I18n::translator()->translate_plural( $singular, $plural, $count, smliser_i18n_domain( $domain ), $context );
}

/**
 * Translate and escape for HTML text.
 *
 * @param string $text
 * @param string $domain
 * @return string
 */
function smliser_esc_html__( string $text, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	return escHtml( smliser__( $text, $domain ) );
}

/**
 * Translate, escape for HTML text and echo.
 *
 * @param string $text
 * @param string $domain
 * @return void
 */
function smliser_esc_html_e( string $text, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : void {
	echo smliser_esc_html__( $text, $domain ); // phpcs:ignore
}

/**
 * Translate with context and escape for HTML text.
 *
 * @param string $text
 * @param string $context
 * @param string $domain
 * @return string
 */
function smliser_esc_html_x( string $text, string $context, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	return escHtml( smliser_x( $text, $context, $domain ) );
}

/**
 * Translate and escape for an HTML attribute.
 *
 * @param string $text
 * @param string $domain
 * @return string
 */
function smliser_esc_attr__( string $text, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	return escAttr( smliser__( $text, $domain ) );
}

/**
 * Translate, escape for an HTML attribute and echo.
 *
 * @param string $text
 * @param string $domain
 * @return void
 */
function smliser_esc_attr_e( string $text, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : void {
	echo smliser_esc_attr__( $text, $domain ); // phpcs:ignore
}

/**
 * Echo translated text with a context (not escaped; prefer
 * smliser_esc_html_x() in HTML).
 *
 * @param string $text
 * @param string $context
 * @param string $domain
 * @return void
 */
function smliser_ex( string $text, string $context, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : void {
	echo smliser_x( $text, $context, $domain ); // phpcs:ignore
}

/**
 * Translate with context and escape for an HTML attribute.
 *
 * @param string $text
 * @param string $context
 * @param string $domain
 * @return string
 */
function smliser_esc_attr_x( string $text, string $context, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	return escAttr( smliser_x( $text, $context, $domain ) );
}

/**
 * Mark a string for extraction without translating it now, for strings
 * defined before the locale is known (constants, static arrays). Translate
 * later with smliser__( $value ).
 *
 * @param string $text
 * @param string $domain Unused at runtime; read by the extractor.
 * @return string The text unchanged.
 */
function smliser__noop( string $text, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	return $text;
}

/**
 * Mark a plural string for extraction without translating it now. Translate
 * later, once the count is known, with smliser_translate_nooped_plural().
 *
 *     $labels = smliser_n_noop( '%d license', '%d licenses' );
 *     sprintf( smliser_translate_nooped_plural( $labels, $count ), $count );
 *
 * The array has the shape of WordPress' _n_noop(), so either works with
 * either translate function.
 *
 * @param string $singular
 * @param string $plural
 * @param string $domain
 * @return array{0: string, 1: string, singular: string, plural: string, context: null, domain: string}
 */
function smliser_n_noop( string $singular, string $plural, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : array {
	return [
		0          => $singular,
		1          => $plural,
		'singular' => $singular,
		'plural'   => $plural,
		'context'  => null,
		'domain'   => $domain,
	];
}

/**
 * Mark a plural string with a context for extraction without translating it
 * now. See smliser_n_noop().
 *
 * @param string $singular
 * @param string $plural
 * @param string $context
 * @param string $domain
 * @return array{0: string, 1: string, 2: string, singular: string, plural: string, context: string, domain: string}
 */
function smliser_nx_noop( string $singular, string $plural, string $context, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : array {
	return [
		0          => $singular,
		1          => $plural,
		2          => $context,
		'singular' => $singular,
		'plural'   => $plural,
		'context'  => $context,
		'domain'   => $domain,
	];
}

/**
 * Translate a plural string marked with smliser_n_noop() or
 * smliser_nx_noop() (or WordPress' _n_noop() / _nx_noop()).
 *
 * @param array{singular: string, plural: string, context?: string|null, domain?: string|null} $nooped
 * @param int    $count
 * @param string $domain Used when the array names no domain.
 * @return string
 */
function smliser_translate_nooped_plural( array $nooped, int $count, string $domain = TranslatorInterface::DEFAULT_DOMAIN ) : string {
	$singular = (string) ( $nooped['singular'] ?? $nooped[0] ?? '' );
	$plural   = (string) ( $nooped['plural'] ?? $nooped[1] ?? $singular );
	$context  = $nooped['context'] ?? null;
	$domain   = is_string( $nooped['domain'] ?? null ) && '' !== $nooped['domain'] ? $nooped['domain'] : $domain;

	return I18n::translator()->translate_plural( $singular, $plural, $count, smliser_i18n_domain( $domain ), is_string( $context ) ? $context : null );
}

/**
 * The active locale code, e.g. `fr_FR`.
 *
 * @return string
 */
function smliser_get_locale() : string {
	return I18n::locale()->code();
}

/**
 * The active locale as a value object.
 *
 * @return Locale
 */
function smliser_locale() : Locale {
	return I18n::locale();
}

/**
 * Tell whether the active locale is written right to left.
 *
 * @return bool
 */
function smliser_is_rtl() : bool {
	return I18n::locale()->is_rtl();
}

/**
 * The `lang` and `dir` attributes for the <html> element.
 *
 * @return string e.g. `lang="ar" dir="rtl"`.
 */
function smliser_language_attributes() : string {
	$locale = I18n::locale();

	return sprintf( 'lang="%s" dir="%s"', escAttr( $locale->to_bcp47() ), escAttr( $locale->direction() ) );
}

/**
 * The site language chosen by the administrator.
 *
 * @return string|null A locale code, or null for "automatic".
 */
function smliser_site_locale() : ?string {
	if ( I18n::translator() instanceof WPTranslator ) {
		return function_exists( 'get_locale' ) ? \get_locale() : null;
	}

	try {
		$locale = Settings::instance()->get( Settings::SITE_LOCALE, '' );
	} catch ( Throwable ) {
		return null;
	}

	return is_string( $locale ) && Locale::is_valid( $locale ) ? $locale : null;
}

/**
 * The language a user reads in: their preference, else the site language.
 *
 * Use it for anything addressed to that user outside their own request,
 * such as emails and notifications sent from background jobs.
 *
 * @param User $user
 * @return string|null A locale code, or null for the site default.
 */
function smliser_user_locale( User $user ) : ?string {
	try {
		$locale = $user->get_id() ? UserSettings::for( $user )->get( UserSettings::LOCALE ) : null;
	} catch ( Throwable ) {
		$locale = null;
	}

	return is_string( $locale ) && Locale::is_valid( $locale ) ? $locale : smliser_site_locale();
}

/**
 * Run a callback in another locale, then restore the current one.
 *
 * Use it for output addressed to someone else, such as an email rendered
 * in the recipient's language.
 *
 * @template T
 * @param Locale|string|null $locale   Null or invalid runs in the current locale.
 * @param callable(): T      $callback
 * @return T
 */
function smliser_with_locale( Locale|string|null $locale, callable $callback ) : mixed {
	$target = $locale instanceof Locale ? $locale : Locale::try_parse( $locale );
	$i18n   = I18n::translator();

	if ( null === $target || $target->equals( $i18n->get_locale() ) ) {
		return $callback();
	}

	if ( $i18n instanceof Translator ) {
		return $i18n->with_locale( $target, $callback );
	}

	$previous = $i18n->get_locale();
	$i18n->set_locale( $target );

	try {
		return $callback();
	} finally {
		$i18n->set_locale( $previous );
	}
}

/*
|------------------------------------------------------------------
| WordPress-compatible names, for the standalone environment.
|------------------------------------------------------------------
| Under WordPress these are WordPress' own functions. Standalone, they
| route to the application translator so code (and extensions) written
| the WordPress way is translatable too.
*/

if ( ! function_exists( '__' ) ) {
	/**
	 * Retrieves the translation of $text.
	 */
	function __( string $text, string $domain = 'default' ) : string {
		return smliser__( $text, $domain );
	}
}

if ( ! function_exists( '_e' ) ) {
	/**
	 * Displays the translation of $text.
	 */
	function _e( string $text, string $domain = 'default' ) : void {
		smliser_e( $text, $domain );
	}
}

if ( ! function_exists( '_x' ) ) {
	/**
	 * Retrieves translated string with gettext context.
	 */
	function _x( string $text, string $context, string $domain = 'default' ) : string {
		return smliser_x( $text, $context, $domain );
	}
}

if ( ! function_exists( '_ex' ) ) {
	/**
	 * Displays translated string with gettext context.
	 */
	function _ex( string $text, string $context, string $domain = 'default' ) : void {
		smliser_ex( $text, $context, $domain );
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Translates and retrieves the singular or plural form based on the supplied number.
	 */
	function _n( string $single, string $plural, int $number, string $domain = 'default' ) : string {
		return smliser_n( $single, $plural, $number, $domain );
	}
}

if ( ! function_exists( '_nx' ) ) {
	/**
	 * Translates and retrieves the singular or plural form based on the supplied number, with gettext context.
	 */
	function _nx( string $single, string $plural, int $number, string $context, string $domain = 'default' ) : string {
		return smliser_nx( $single, $plural, $number, $context, $domain );
	}
}

if ( ! function_exists( '_n_noop' ) ) {
	/**
	 * Registers plural strings in POT file, but does not translate them.
	 *
	 * @return array{0: string, 1: string, singular: string, plural: string, context: null, domain: string|null}
	 */
	function _n_noop( string $singular, string $plural, ?string $domain = null ) : array {
		$nooped           = smliser_n_noop( $singular, $plural );
		$nooped['domain'] = $domain;

		return $nooped;
	}
}

if ( ! function_exists( '_nx_noop' ) ) {
	/**
	 * Registers plural strings with gettext context in POT file, but does not translate them.
	 *
	 * @return array{0: string, 1: string, 2: string, singular: string, plural: string, context: string, domain: string|null}
	 */
	function _nx_noop( string $singular, string $plural, string $context, ?string $domain = null ) : array {
		$nooped           = smliser_nx_noop( $singular, $plural, $context );
		$nooped['domain'] = $domain;

		return $nooped;
	}
}

if ( ! function_exists( 'translate_nooped_plural' ) ) {
	/**
	 * Translates and returns the singular or plural form of a string that's been registered with _n_noop() or _nx_noop().
	 */
	function translate_nooped_plural( array $nooped_plural, int $count, string $domain = 'default' ) : string {
		return smliser_translate_nooped_plural( $nooped_plural, $count, $domain );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Retrieves the translation of $text and escapes it for safe use in HTML output.
	 */
	function esc_html__( string $text, string $domain = 'default' ) : string {
		return smliser_esc_html__( $text, $domain );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Displays translated text that has been escaped for safe use in HTML output.
	 */
	function esc_html_e( string $text, string $domain = 'default' ) : void {
		smliser_esc_html_e( $text, $domain );
	}
}

if ( ! function_exists( 'esc_html_x' ) ) {
	/**
	 * Translates string with gettext context, and escapes it for safe use in HTML output.
	 */
	function esc_html_x( string $text, string $context, string $domain = 'default' ) : string {
		return smliser_esc_html_x( $text, $context, $domain );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Retrieves the translation of $text and escapes it for safe use in an attribute.
	 */
	function esc_attr__( string $text, string $domain = 'default' ) : string {
		return smliser_esc_attr__( $text, $domain );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * Displays translated text that has been escaped for safe use in an attribute.
	 */
	function esc_attr_e( string $text, string $domain = 'default' ) : void {
		smliser_esc_attr_e( $text, $domain );
	}
}

if ( ! function_exists( 'esc_attr_x' ) ) {
	/**
	 * Translates string with gettext context, and escapes it for safe use in attributes.
	 */
	function esc_attr_x( string $text, string $context, string $domain = 'default' ) : string {
		return smliser_esc_attr_x( $text, $context, $domain );
	}
}

/**
 * Render a language picker, or nothing when only one language is installed.
 *
 * The picker is wired up by assets/js/i18n.js: the choice is kept in a
 * cookie, and also saved to the signed-in user's preferences when
 * $endpoint (the client dashboard `json/preferences` URL) is given.
 *
 * @param string $endpoint Preferences endpoint, or '' for guests.
 * @param string $class    Extra CSS class.
 * @return string HTML.
 */
function smliser_language_switcher( string $endpoint = '', string $class = '' ) : string {
	$options = I18n::language_options();

	if ( count( $options ) < 2 ) {
		return '';
	}

	$current = I18n::locale()->code();
	$id      = 'smliser-language-' . substr( md5( $endpoint . $class ), 0, 6 );
	$html    = sprintf(
		'<label class="smliser-language-switcher %1$s" for="%2$s"><span class="screen-reader-text">%3$s</span><i class="ti ti-language" aria-hidden="true"></i> <select id="%2$s" data-smliser-language-switcher%4$s>',
		escAttr( $class ),
		escAttr( $id ),
		smliser_esc_html__( 'Language' ),
		'' === $endpoint ? '' : sprintf( ' data-endpoint="%s"', escAttr( $endpoint ) )
	);

	foreach ( $options as $code => $name ) {
		$html .= sprintf(
			'<option value="%1$s" lang="%2$s"%3$s>%4$s</option>',
			escAttr( $code ),
			escAttr( Locale::parse( $code )->to_bcp47() ),
			$code === $current ? ' selected' : '',
			escHtml( $name )
		);
	}

	return $html . '</select></label>';
}
