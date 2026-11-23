<?php
/**
 * Locale value object class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n;

use InvalidArgumentException;
use Stringable;

/**
 * An immutable, normalized locale identifier.
 *
 * Locales are stored in the gettext/WordPress form `ll_RR` (for example
 * `en_US`, `pt_BR`, `zh_Hans_CN`), which is also the file-name form used for
 * catalogs (`smliser-pt_BR.po`). BCP 47 input such as `pt-br` or `zh-Hans-CN`
 * is accepted and normalized.
 */
final class Locale implements Stringable {

	/**
	 * The application's source language: the language strings are written in.
	 */
	public const DEFAULT = 'en_US';

	/**
	 * Languages written right to left.
	 *
	 * @var array<int, string>
	 */
	private const RTL_LANGUAGES = [
		'ar', 'arc', 'azb', 'ckb', 'dv', 'fa', 'ha', 'he', 'khw', 'ks',
		'ku', 'ps', 'sd', 'syr', 'ug', 'ur', 'yi',
	];

	/**
	 * Native display names for common locales, used by language pickers when
	 * the intl extension is unavailable.
	 *
	 * @var array<string, string>
	 */
	private const NATIVE_NAMES = [
		'ar'    => 'العربية',
		'de'    => 'Deutsch',
		'de_DE' => 'Deutsch',
		'en'    => 'English',
		'en_GB' => 'English (UK)',
		'en_US' => 'English (United States)',
		'es'    => 'Español',
		'es_ES' => 'Español',
		'es_MX' => 'Español de México',
		'fa'    => 'فارسی',
		'fr'    => 'Français',
		'fr_FR' => 'Français',
		'ha'    => 'Hausa',
		'he'    => 'עברית',
		'hi'    => 'हिन्दी',
		'id'    => 'Bahasa Indonesia',
		'ig'    => 'Asụsụ Igbo',
		'it'    => 'Italiano',
		'it_IT' => 'Italiano',
		'ja'    => '日本語',
		'ko'    => '한국어',
		'ko_KR' => '한국어',
		'nl'    => 'Nederlands',
		'nl_NL' => 'Nederlands',
		'pl'    => 'Polski',
		'pl_PL' => 'Polski',
		'pt'    => 'Português',
		'pt_BR' => 'Português do Brasil',
		'pt_PT' => 'Português',
		'ru'    => 'Русский',
		'ru_RU' => 'Русский',
		'sw'    => 'Kiswahili',
		'tr'    => 'Türkçe',
		'tr_TR' => 'Türkçe',
		'uk'    => 'Українська',
		'yo'    => 'Èdè Yorùbá',
		'zh'    => '中文',
		'zh_CN' => '简体中文',
		'zh_TW' => '繁體中文',
	];

	/**
	 * Lower-case language subtag, e.g. `pt`.
	 */
	private string $language;

	/**
	 * Optional title-case script subtag, e.g. `Hans`.
	 */
	private ?string $script;

	/**
	 * Optional upper-case region subtag, e.g. `BR`.
	 */
	private ?string $region;

	/**
	 * Optional variant, e.g. `formal` (WordPress uses `de_DE_formal`).
	 */
	private ?string $variant;

	/**
	 * Constructor; use Locale::parse().
	 */
	private function __construct( string $language, ?string $script, ?string $region, ?string $variant ) {
		$this->language = $language;
		$this->script   = $script;
		$this->region   = $region;
		$this->variant  = $variant;
	}

	/**
	 * Parse a locale identifier.
	 *
	 * Accepts `en`, `en_US`, `en-us`, `zh-Hans-CN`, `de_DE_formal`, and strips
	 * any encoding or modifier suffix (`en_US.UTF-8`, `sr_RS@latin`).
	 *
	 * @param string $locale
	 * @return self
	 * @throws InvalidArgumentException When the identifier is not a locale.
	 */
	public static function parse( string $locale ) : self {
		$value = trim( $locale );
		$value = preg_replace( '/[.@].*$/', '', $value ) ?? '';
		$parts = preg_split( '/[-_]/', $value ) ?: [];

		$language = strtolower( (string) array_shift( $parts ) );

		if ( ! preg_match( '/^[a-z]{2,3}$/', $language ) ) {
			throw new InvalidArgumentException( sprintf( 'Invalid locale "%s".', $locale ) );
		}

		$script  = null;
		$region  = null;
		$variant = null;

		foreach ( $parts as $part ) {
			if ( null === $script && null === $region && preg_match( '/^[a-z]{4}$/i', $part ) ) {
				$script = ucfirst( strtolower( $part ) );
			} elseif ( null === $region && null === $variant && preg_match( '/^([a-z]{2}|\d{3})$/i', $part ) ) {
				$region = strtoupper( $part );
			} elseif ( null === $variant && preg_match( '/^[a-z0-9]{1,16}$/i', $part ) ) {
				$variant = strtolower( $part );
			} else {
				throw new InvalidArgumentException( sprintf( 'Invalid locale "%s".', $locale ) );
			}
		}

		return new self( $language, $script, $region, $variant );
	}

	/**
	 * Parse a locale, returning null instead of throwing.
	 *
	 * @param string|null $locale
	 * @return self|null
	 */
	public static function try_parse( ?string $locale ) : ?self {
		if ( null === $locale || '' === trim( $locale ) ) {
			return null;
		}

		try {
			return self::parse( $locale );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Tell whether a value is a valid locale identifier.
	 */
	public static function is_valid( ?string $locale ) : bool {
		return null !== self::try_parse( $locale );
	}

	/**
	 * The default (source) locale.
	 */
	public static function default() : self {
		return self::parse( self::DEFAULT );
	}

	/**
	 * Lower-case language subtag.
	 */
	public function language() : string {
		return $this->language;
	}

	/**
	 * Region subtag, if any.
	 */
	public function region() : ?string {
		return $this->region;
	}

	/**
	 * Script subtag, if any.
	 */
	public function script() : ?string {
		return $this->script;
	}

	/**
	 * Canonical gettext form, e.g. `pt_BR`.
	 */
	public function code() : string {
		return implode( '_', array_filter( [ $this->language, $this->script, $this->region, $this->variant ] ) );
	}

	/**
	 * BCP 47 language tag for HTML `lang` attributes and HTTP headers, e.g. `pt-BR`.
	 */
	public function to_bcp47() : string {
		return implode( '-', array_filter( [ $this->language, $this->script, $this->region ] ) );
	}

	/**
	 * Text direction: `rtl` or `ltr`.
	 */
	public function direction() : string {
		if ( 'Latn' === $this->script || 'Cyrl' === $this->script ) {
			return 'ltr';
		}

		return in_array( $this->language, self::RTL_LANGUAGES, true ) ? 'rtl' : 'ltr';
	}

	/**
	 * Tell whether the locale is written right to left.
	 */
	public function is_rtl() : bool {
		return 'rtl' === $this->direction();
	}

	/**
	 * Locales to try, most specific first, when looking up a catalog.
	 *
	 * `zh_Hans_CN` → [`zh_Hans_CN`, `zh_CN`, `zh_Hans`, `zh`].
	 * `de_DE_formal` → [`de_DE_formal`, `de_DE`, `de`].
	 *
	 * @return array<int, string>
	 */
	public function fallback_chain() : array {
		$chain = [ $this->code() ];

		if ( null !== $this->variant ) {
			$chain[] = implode( '_', array_filter( [ $this->language, $this->script, $this->region ] ) );
		}

		if ( null !== $this->script && null !== $this->region ) {
			$chain[] = $this->language . '_' . $this->region;
			$chain[] = $this->language . '_' . $this->script;
		} elseif ( null !== $this->script || null !== $this->region ) {
			$chain[] = $this->language . '_' . ( $this->script ?? $this->region );
		}

		$chain[] = $this->language;

		return array_values( array_unique( $chain ) );
	}

	/**
	 * The locale's name in its own language, e.g. `Français`.
	 *
	 * Uses the intl extension when available.
	 */
	public function native_name() : string {
		if ( class_exists( \Locale::class ) ) {
			$name = \Locale::getDisplayName( $this->to_bcp47(), $this->to_bcp47() );

			if ( is_string( $name ) && '' !== $name && strtolower( $name ) !== strtolower( $this->to_bcp47() ) ) {
				return function_exists( 'mb_convert_case' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 ) : ucfirst( $name );
			}
		}

		foreach ( $this->fallback_chain() as $candidate ) {
			if ( isset( self::NATIVE_NAMES[ $candidate ] ) ) {
				return self::NATIVE_NAMES[ $candidate ];
			}
		}

		return $this->code();
	}

	/**
	 * Tell whether two locales are the same.
	 */
	public function equals( self|string $other ) : bool {
		$other = $other instanceof self ? $other : self::try_parse( $other );

		return null !== $other && $other->code() === $this->code();
	}

	/**
	 * {@inheritdoc}
	 */
	public function __toString() : string {
		return $this->code();
	}
}
