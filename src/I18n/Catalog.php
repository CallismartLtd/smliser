<?php
/**
 * Translation catalog class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n;

/**
 * The translations of one text domain into one locale.
 *
 * Entries are keyed the gettext way: the source string, prefixed by its
 * context and an EOT (\x04) separator when it has one. Each entry holds one
 * translation per plural form; singular-only strings hold one.
 */
final class Catalog {

	/**
	 * Separator between a message context and its source string.
	 */
	public const CONTEXT_SEPARATOR = "\x04";

	/**
	 * Plural rule, parsed lazily from the headers.
	 */
	private ?PluralForms $plural_forms = null;

	/**
	 * Constructor.
	 *
	 * @param array<string, array<int, string>> $entries Translations keyed by self::key().
	 * @param array<string, string>             $headers Catalog headers (`Plural-Forms`, `Language`, ...).
	 */
	public function __construct(
		private array $entries = [],
		private array $headers = []
	) {}

	/**
	 * Build the lookup key of a message.
	 */
	public static function key( string $text, ?string $context = null ) : string {
		return ( null === $context || '' === $context ) ? $text : $context . self::CONTEXT_SEPARATOR . $text;
	}

	/**
	 * Look up a singular translation.
	 *
	 * @return string|null Null when untranslated.
	 */
	public function get( string $text, ?string $context = null ) : ?string {
		$translation = $this->entries[ self::key( $text, $context ) ][0] ?? null;

		return ( null === $translation || '' === $translation ) ? null : $translation;
	}

	/**
	 * Look up a plural translation.
	 *
	 * @return string|null Null when untranslated.
	 */
	public function get_plural( string $singular, int $count, ?string $context = null ) : ?string {
		$forms = $this->entries[ self::key( $singular, $context ) ] ?? null;

		if ( null === $forms ) {
			return null;
		}

		$translation = $forms[ $this->plural_forms()->index( $count ) ] ?? null;

		return ( null === $translation || '' === $translation ) ? null : $translation;
	}

	/**
	 * Tell whether the catalog has a translation for a message.
	 */
	public function has( string $text, ?string $context = null ) : bool {
		return null !== $this->get( $text, $context );
	}

	/**
	 * Add or replace a translation.
	 *
	 * @param string                   $text
	 * @param string|array<int,string> $translation One string, or one per plural form.
	 * @param string|null              $context
	 */
	public function set( string $text, string|array $translation, ?string $context = null ) : void {
		$this->entries[ self::key( $text, $context ) ] = array_values( (array) $translation );
	}

	/**
	 * The plural rule of the catalog's language.
	 */
	public function plural_forms() : PluralForms {
		return $this->plural_forms ??= PluralForms::from_header_or_default( $this->header( 'Plural-Forms' ) );
	}

	/**
	 * A header value, matched case-insensitively.
	 */
	public function header( string $name ) : ?string {
		foreach ( $this->headers as $key => $value ) {
			if ( 0 === strcasecmp( $key, $name ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * All headers.
	 *
	 * @return array<string, string>
	 */
	public function headers() : array {
		return $this->headers;
	}

	/**
	 * All entries.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function entries() : array {
		return $this->entries;
	}

	/**
	 * Number of entries with at least one non-empty translation.
	 */
	public function count() : int {
		return count( array_filter( $this->entries, static fn ( array $forms ) : bool => '' !== implode( '', $forms ) ) );
	}

	/**
	 * Merge another catalog in. Entries already present win, so merge the
	 * highest-priority catalog first.
	 */
	public function merge( self $other ) : self {
		$entries = $this->entries;

		foreach ( $other->entries as $key => $forms ) {
			if ( ! isset( $entries[ $key ] ) || '' === implode( '', $entries[ $key ] ) ) {
				$entries[ $key ] = $forms;
			}
		}

		return new self( $entries, $this->headers ?: $other->headers );
	}

	/**
	 * Parse a gettext header block (`Key: value` lines).
	 *
	 * @return array<string, string>
	 */
	public static function parse_headers( string $block ) : array {
		$headers = [];

		foreach ( preg_split( '/\r?\n/', $block ) ?: [] as $line ) {
			$pos = strpos( $line, ':' );

			if ( false === $pos ) {
				continue;
			}

			$name = trim( substr( $line, 0, $pos ) );

			if ( '' !== $name ) {
				$headers[ $name ] = trim( substr( $line, $pos + 1 ) );
			}
		}

		return $headers;
	}
}
