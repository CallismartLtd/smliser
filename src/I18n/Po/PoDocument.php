<?php
/**
 * PO document class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n\Po
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n\Po;

use SmartLicenseServer\I18n\Catalog;
use SmartLicenseServer\I18n\I18nException;

/**
 * Reads and writes gettext PO/POT files.
 *
 * Used at runtime to load `.po` catalogs, and by the `smliser i18n` command
 * to generate the template, update translations and compile them.
 */
final class PoDocument {

	/**
	 * Constructor.
	 *
	 * @param array<string, string>  $headers
	 * @param array<string, PoEntry> $entries Keyed by PoEntry::key().
	 */
	public function __construct(
		public array $headers = [],
		public array $entries = [],
	) {}

	/**
	 * Add an entry, merging references and comments into an existing one.
	 */
	public function add( PoEntry $entry ) : PoEntry {
		$key = $entry->key();

		if ( ! isset( $this->entries[ $key ] ) ) {
			return $this->entries[ $key ] = $entry;
		}

		$existing                     = $this->entries[ $key ];
		$existing->plural           ??= $entry->plural;
		$existing->references         = array_values( array_unique( [ ...$existing->references, ...$entry->references ] ) );
		$existing->extracted_comments = array_values( array_unique( [ ...$existing->extracted_comments, ...$entry->extracted_comments ] ) );

		foreach ( $entry->flags as $flag ) {
			$existing->add_flag( $flag );
		}

		return $existing;
	}

	/**
	 * Read a PO file.
	 *
	 * @throws I18nException
	 */
	public static function from_file( string $path ) : self {
		$data = is_readable( $path ) ? file_get_contents( $path ) : false;

		if ( false === $data ) {
			throw new I18nException( sprintf( 'Translation file "%s" is not readable.', $path ) );
		}

		return self::parse( $data );
	}

	/**
	 * Parse PO text.
	 *
	 * Obsolete (`#~`) entries are dropped.
	 *
	 * @throws I18nException On malformed input.
	 */
	public static function parse( string $text ) : self {
		$text     = preg_replace( '/^\xEF\xBB\xBF/', '', $text ) ?? $text;
		$lines    = preg_split( '/\r\n|\r|\n/', $text ) ?: [];
		$document = new self();
		$current  = self::blank();
		$field    = null; // Name of the field continuation lines append to.
		$number   = 0;

		$flush = static function () use ( &$current, &$field, $document ) : void {
			if ( null !== $current['msgid'] ) {
				if ( '' === $current['msgid'] && null === $current['msgctxt'] ) {
					$document->headers = Catalog::parse_headers( $current['msgstr'][0] ?? '' );
				} else {
					ksort( $current['msgstr'] );
					$document->entries[ Catalog::key( $current['msgid'], $current['msgctxt'] ) ] = new PoEntry(
						msgid: $current['msgid'],
						context: $current['msgctxt'],
						plural: $current['msgid_plural'],
						translations: array_values( $current['msgstr'] ),
						references: $current['references'],
						extracted_comments: $current['extracted'],
						translator_comments: $current['comments'],
						flags: $current['flags'],
					);
				}
			}

			$current = self::blank();
			$field   = null;
		};

		foreach ( $lines as $line ) {
			++$number;
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( str_starts_with( $line, '#' ) ) {
				if ( null !== $current['msgid'] && null !== $field && str_starts_with( $field, 'msgstr' ) ) {
					$flush();
				}

				$marker = substr( $line, 0, 2 );
				$value  = trim( substr( $line, 2 ) );

				match ( $marker ) {
					'#:'    => array_push( $current['references'], ...( preg_split( '/\s+/', $value ) ?: [] ) ),
					'#.'    => $current['extracted'][] = $value,
					'#,'    => array_push( $current['flags'], ...array_map( 'trim', explode( ',', $value ) ) ),
					'#~', '#|' => null,
					default => $current['comments'][] = trim( substr( $line, 1 ) ),
				};
				continue;
			}

			if ( str_starts_with( $line, '"' ) ) {
				if ( null === $field ) {
					throw new I18nException( sprintf( 'Unexpected string on line %d of PO data.', $number ) );
				}

				self::append( $current, $field, self::unquote( $line, $number ) );
				continue;
			}

			if ( ! preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+(".*")$/', $line, $m ) ) {
				throw new I18nException( sprintf( 'Malformed line %d of PO data.', $number ) );
			}

			$keyword = $m[1];
			$value   = self::unquote( $m[3], $number );

			// A new msgctxt/msgid after a msgstr starts a new entry.
			if ( ( 'msgctxt' === $keyword || 'msgid' === $keyword ) && null !== $field && str_starts_with( $field, 'msgstr' ) ) {
				$flush();
			}

			if ( str_starts_with( $keyword, 'msgstr' ) ) {
				$field = 'msgstr:' . ( '' !== ( $m[2] ?? '' ) ? (int) $m[2] : 0 );
			} else {
				$field = $keyword;
			}

			self::append( $current, $field, $value, true );
		}

		$flush();

		return $document;
	}

	/**
	 * A blank parser state.
	 *
	 * @return array{msgctxt: ?string, msgid: ?string, msgid_plural: ?string, msgstr: array<int,string>, references: array<int,string>, extracted: array<int,string>, comments: array<int,string>, flags: array<int,string>}
	 */
	private static function blank() : array {
		return [
			'msgctxt'      => null,
			'msgid'        => null,
			'msgid_plural' => null,
			'msgstr'       => [],
			'references'   => [],
			'extracted'    => [],
			'comments'     => [],
			'flags'        => [],
		];
	}

	/**
	 * Append text to a field of the parser state.
	 */
	private static function append( array &$current, string $field, string $value, bool $start = false ) : void {
		if ( str_starts_with( $field, 'msgstr:' ) ) {
			$index                       = (int) substr( $field, 7 );
			$current['msgstr'][ $index ] = ( $start ? '' : ( $current['msgstr'][ $index ] ?? '' ) ) . $value;
			return;
		}

		$current[ $field ] = ( $start ? '' : (string) $current[ $field ] ) . $value;
	}

	/**
	 * Decode a quoted PO string.
	 */
	private static function unquote( string $quoted, int $line ) : string {
		if ( strlen( $quoted ) < 2 || '"' !== $quoted[0] || '"' !== $quoted[ strlen( $quoted ) - 1 ] ) {
			throw new I18nException( sprintf( 'Unterminated string on line %d of PO data.', $line ) );
		}

		return stripcslashes( substr( $quoted, 1, -1 ) );
	}

	/**
	 * Encode a string for a PO file, splitting multi-line text.
	 */
	private static function quote( string $text ) : string {
		$escape = static fn ( string $s ) : string => '"' . addcslashes( $s, "\0..\37\"\\" ) . '"';

		if ( ! str_contains( $text, "\n" ) || "\n" === $text ) {
			return $escape( $text );
		}

		$parts = preg_split( '/(?<=\n)/', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: [];

		return '""' . "\n" . implode( "\n", array_map( $escape, $parts ) );
	}

	/**
	 * Convert to a runtime catalog. Fuzzy and untranslated entries are skipped.
	 */
	public function to_catalog() : Catalog {
		$entries = [];

		foreach ( $this->entries as $key => $entry ) {
			if ( $entry->is_fuzzy() || '' === implode( '', $entry->translations ) ) {
				continue;
			}

			$entries[ $key ] = $entry->translations;
		}

		return new Catalog( $entries, $this->headers );
	}

	/**
	 * Serialize to PO text.
	 */
	public function to_string() : string {
		$out   = [];
		$out[] = 'msgid ""';
		$out[] = 'msgstr ""';

		foreach ( $this->headers as $name => $value ) {
			$out[] = '"' . addcslashes( $name . ': ' . $value, "\"\\" ) . '\n"';
		}

		foreach ( $this->entries as $entry ) {
			$out[] = '';

			foreach ( $entry->translator_comments as $c ) {
				$out[] = rtrim( '# ' . $c );
			}

			foreach ( $entry->extracted_comments as $c ) {
				$out[] = '#. ' . $c;
			}

			foreach ( array_chunk( $entry->references, 4 ) as $refs ) {
				$out[] = '#: ' . implode( ' ', $refs );
			}

			if ( [] !== $entry->flags ) {
				$out[] = '#, ' . implode( ', ', $entry->flags );
			}

			if ( null !== $entry->context ) {
				$out[] = 'msgctxt ' . self::quote( $entry->context );
			}

			$out[] = 'msgid ' . self::quote( $entry->msgid );

			if ( null !== $entry->plural ) {
				$out[] = 'msgid_plural ' . self::quote( $entry->plural );
				$forms = [] === $entry->translations ? [ '', '' ] : $entry->translations;

				foreach ( $forms as $i => $t ) {
					$out[] = "msgstr[{$i}] " . self::quote( $t );
				}
			} else {
				$out[] = 'msgstr ' . self::quote( $entry->translations[0] ?? '' );
			}
		}

		return implode( "\n", $out ) . "\n";
	}

	/**
	 * Write to a file.
	 *
	 * @throws I18nException
	 */
	public function save( string $path ) : void {
		$dir = dirname( $path );

		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new I18nException( sprintf( 'Cannot create directory "%s".', $dir ) );
		}

		if ( false === file_put_contents( $path, $this->to_string(), LOCK_EX ) ) {
			throw new I18nException( sprintf( 'Cannot write "%s".', $path ) );
		}
	}
}
