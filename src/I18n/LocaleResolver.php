<?php
/**
 * Locale resolver class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n;

/**
 * Decides which locale a request is served in.
 *
 * Precedence, first available match wins:
 *
 * 1. `?lang=xx_XX` in the URL (handy for previews and shared links).
 * 2. The signed-in user's language preference.
 * 3. The guest's language cookie.
 * 4. The site language chosen by the administrator.
 * 5. The browser's `Accept-Language` header.
 * 6. The source locale (`en_US`).
 *
 * Only locales that have translations installed are accepted, so a stale
 * preference never breaks a page.
 */
final class LocaleResolver {

	/**
	 * Query parameter that overrides the locale for one request.
	 */
	public const QUERY_PARAM = 'lang';

	/**
	 * Cookie that remembers a guest's choice.
	 */
	public const COOKIE = 'smliser_lang';

	/**
	 * Site setting holding the administrator's chosen language; empty means
	 * "detect from the browser".
	 */
	public const SITE_SETTING = 'site_locale';

	/**
	 * Constructor.
	 *
	 * @param array<int, string> $available Locale codes with translations installed.
	 */
	public function __construct( private array $available ) {}

	/**
	 * Resolve the locale.
	 *
	 * @param string|null $query_locale  Value of ?lang=.
	 * @param string|null $user_locale   The signed-in user's preference.
	 * @param string|null $cookie_locale The guest's cookie.
	 * @param string|null $site_locale   The site language setting.
	 * @param string|null $accept_header The Accept-Language header.
	 */
	public function resolve(
		?string $query_locale = null,
		?string $user_locale = null,
		?string $cookie_locale = null,
		?string $site_locale = null,
		?string $accept_header = null,
	) : Locale {
		foreach ( [ $query_locale, $user_locale, $cookie_locale, $site_locale ] as $candidate ) {
			$match = $this->match( $candidate );

			if ( null !== $match ) {
				return $match;
			}
		}

		foreach ( self::parse_accept_language( (string) $accept_header ) as $candidate ) {
			$match = $this->match( $candidate, true );

			if ( null !== $match ) {
				return $match;
			}
		}

		return Locale::default();
	}

	/**
	 * Resolve the locale of an HTTP request.
	 *
	 * @param \SmartLicenseServer\Core\Request $request
	 * @param string|null $user_locale The signed-in user's preference, if any.
	 * @param string|null $site_locale The site language setting.
	 */
	public function resolve_request( \SmartLicenseServer\Core\Request $request, ?string $user_locale = null, ?string $site_locale = null ) : Locale {
		$query  = $request->query( self::QUERY_PARAM );
		$cookie = $request->cookie( self::COOKIE );

		return $this->resolve(
			is_string( $query ) ? $query : null,
			$user_locale,
			is_string( $cookie ) ? $cookie : null,
			$site_locale,
			$request->get_header( 'Accept-Language' ),
		);
	}

	/**
	 * Match a requested locale against the available ones.
	 *
	 * Exact match first, then any available locale in the requested one's
	 * fallback chain (`pt_BR` → `pt`). With $loose, a bare language also
	 * matches a regional variant (`fr` → `fr_FR`), as browsers often send
	 * only the language.
	 */
	public function match( ?string $requested, bool $loose = false ) : ?Locale {
		$locale = Locale::try_parse( $requested );

		if ( null === $locale ) {
			return null;
		}

		$available = array_map( static fn ( string $code ) : string => Locale::try_parse( $code )?->code() ?? '', $this->available );

		foreach ( $locale->fallback_chain() as $code ) {
			if ( in_array( $code, $available, true ) ) {
				return Locale::parse( $code );
			}
		}

		if ( $loose ) {
			foreach ( $available as $code ) {
				if ( '' !== $code && Locale::parse( $code )->language() === $locale->language() ) {
					return Locale::parse( $code );
				}
			}
		}

		return null;
	}

	/**
	 * Parse an Accept-Language header into tags ordered by preference.
	 *
	 * @return array<int, string>
	 */
	public static function parse_accept_language( string $header ) : array {
		$tags = [];

		foreach ( explode( ',', substr( $header, 0, 1024 ) ) as $index => $part ) {
			$pieces = explode( ';', trim( $part ) );
			$tag    = trim( $pieces[0] );
			$q      = 1.0;

			foreach ( array_slice( $pieces, 1 ) as $param ) {
				if ( preg_match( '/^\s*q\s*=\s*([01](?:\.\d{0,3})?)\s*$/i', $param, $m ) ) {
					$q = (float) $m[1];
				}
			}

			if ( '' === $tag || '*' === $tag || $q <= 0 ) {
				continue;
			}

			// Stable sort: equal weights keep header order.
			$tags[] = [ $tag, $q, $index ];
		}

		usort( $tags, static fn ( array $a, array $b ) : int => [ $b[1], $a[2] ] <=> [ $a[1], $b[2] ] );

		return array_column( $tags, 0 );
	}
}
