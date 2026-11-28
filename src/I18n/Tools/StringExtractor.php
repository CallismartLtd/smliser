<?php
/**
 * Translatable string extractor class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n\Tools
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n\Tools;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SmartLicenseServer\I18n\Po\PoDocument;
use SmartLicenseServer\I18n\Po\PoEntry;
use SmartLicenseServer\I18n\TranslatorInterface;
use SplFileInfo;

/**
 * Scans PHP and JavaScript sources for calls to the translation helpers and
 * builds a POT (translation template) document.
 *
 * Only literal string arguments are extracted; calls with a variable as the
 * text are reported as warnings since they can never be translated.
 */
final class StringExtractor {

	/**
	 * Argument roles of each PHP helper. `text`, `plural`, `context`,
	 * `domain`; null skips an argument (the count).
	 *
	 * @var array<string, array<int, string|null>>
	 */
	public const PHP_FUNCTIONS = [
		'smliser__'          => [ 'text', 'domain' ],
		'smliser_e'          => [ 'text', 'domain' ],
		'smliser__noop'      => [ 'text', 'domain' ],
		'smliser_x'          => [ 'text', 'context', 'domain' ],
		'smliser_n'          => [ 'text', 'plural', null, 'domain' ],
		'smliser_nx'         => [ 'text', 'plural', null, 'context', 'domain' ],
		'smliser_esc_html__' => [ 'text', 'domain' ],
		'smliser_esc_html_e' => [ 'text', 'domain' ],
		'smliser_esc_html_x' => [ 'text', 'context', 'domain' ],
		'smliser_esc_attr__' => [ 'text', 'domain' ],
		'smliser_esc_attr_e' => [ 'text', 'domain' ],
		'smliser_esc_attr_x' => [ 'text', 'context', 'domain' ],
		'smliser_ex'         => [ 'text', 'context', 'domain' ],
		'smliser_n_noop'     => [ 'text', 'plural', 'domain' ],
		'smliser_nx_noop'    => [ 'text', 'plural', 'context', 'domain' ],
		'__'                 => [ 'text', 'domain' ],
		'_e'                 => [ 'text', 'domain' ],
		'_x'                 => [ 'text', 'context', 'domain' ],
		'_ex'                => [ 'text', 'context', 'domain' ],
		'_n'                 => [ 'text', 'plural', null, 'domain' ],
		'_nx'                => [ 'text', 'plural', null, 'context', 'domain' ],
		'_n_noop'            => [ 'text', 'plural', 'domain' ],
		'_nx_noop'           => [ 'text', 'plural', 'context', 'domain' ],
		'esc_html__'         => [ 'text', 'domain' ],
		'esc_html_e'         => [ 'text', 'domain' ],
		'esc_html_x'         => [ 'text', 'context', 'domain' ],
		'esc_attr__'         => [ 'text', 'domain' ],
		'esc_attr_e'         => [ 'text', 'domain' ],
		'esc_attr_x'         => [ 'text', 'context', 'domain' ],
	];

	/**
	 * Argument roles of the JavaScript helpers (`smliserI18n.__()` etc.).
	 *
	 * @var array<string, array<int, string|null>>
	 */
	public const JS_FUNCTIONS = [
		'__'  => [ 'text', 'domain' ],
		'_x'  => [ 'text', 'context', 'domain' ],
		'_n'  => [ 'text', 'plural', null, 'domain' ],
		'_nx' => [ 'text', 'plural', null, 'context', 'domain' ],
	];

	/**
	 * Directory names never scanned.
	 */
	private const SKIP_DIRS = [ 'vendor', 'node_modules', '.git', 'dist', 'tinymce', 'jQuery', 'Select2', 'Chartjs', 'tests' ];

	/**
	 * Files that define the helpers (they pass variables through by design).
	 */
	private const SKIP_FILES = [ 'src/I18n/', 'src/Utils/functions/i18n-functions.php' ];

	/**
	 * Problems found while extracting.
	 *
	 * @var array<int, string>
	 */
	private array $warnings = [];

	/**
	 * Constructor.
	 *
	 * @param string $domain   Text domain to extract; calls for other domains are ignored.
	 * @param string $base_dir Directory references are made relative to.
	 */
	public function __construct(
		private string $domain = TranslatorInterface::DEFAULT_DOMAIN,
		private string $base_dir = ''
	) {
		$this->base_dir = rtrim( $base_dir, '/\\' );
	}

	/**
	 * Extract from files and directories into a new document.
	 *
	 * @param array<int, string> $paths
	 * @return PoDocument
	 */
	public function extract( array $paths, ?PoDocument $document = null ) : PoDocument {
		$document ??= new PoDocument();

		foreach ( $paths as $path ) {
			foreach ( $this->files( $path ) as $file ) {
				$this->extract_file( $file, $document );
			}
		}

		ksort( $document->entries, SORT_STRING );

		return $document;
	}

	/**
	 * Warnings gathered during extraction.
	 *
	 * @return array<int, string>
	 */
	public function warnings() : array {
		return $this->warnings;
	}

	/**
	 * Extract from one file.
	 */
	public function extract_file( string $file, PoDocument $document ) : void {
		$code = file_get_contents( $file );

		if ( false === $code ) {
			$this->warnings[] = sprintf( 'Cannot read %s.', $file );
			return;
		}

		$relative = $this->relative( $file );

		foreach ( self::SKIP_FILES as $skip ) {
			if ( str_starts_with( $relative, $skip ) ) {
				return;
			}
		}

		if ( str_ends_with( $file, '.js' ) ) {
			$this->extract_js( $code, $relative, $document );
		} else {
			$this->extract_php( $code, $relative, $document );
		}
	}

	/**
	 * Source files under a path.
	 *
	 * @return iterable<string>
	 */
	private function files( string $path ) : iterable {
		if ( is_file( $path ) ) {
			yield $path;
			return;
		}

		if ( ! is_dir( $path ) ) {
			$this->warnings[] = sprintf( 'Path %s does not exist.', $path );
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
				static function ( SplFileInfo $file ) : bool {
					if ( $file->isDir() ) {
						return ! in_array( $file->getFilename(), self::SKIP_DIRS, true );
					}

					$name = $file->getFilename();

					return ( str_ends_with( $name, '.php' ) && ! str_ends_with( $name, '.l10n.php' ) )
						|| ( str_ends_with( $name, '.js' ) && ! str_ends_with( $name, '.min.js' ) );
				}
			)
		);

		$files = [];

		foreach ( $iterator as $file ) {
			$files[] = $file->getPathname();
		}

		sort( $files );

		yield from $files;
	}

	/**
	 * Path relative to the base directory, with forward slashes.
	 */
	private function relative( string $file ) : string {
		$file = str_replace( '\\', '/', $file );
		$base = str_replace( '\\', '/', $this->base_dir );

		if ( '' !== $base && str_starts_with( $file, $base . '/' ) ) {
			return substr( $file, strlen( $base ) + 1 );
		}

		return $file;
	}

	/*
	|-----------
	| PHP
	|-----------
	*/

	/**
	 * Extract from PHP source.
	 */
	public function extract_php( string $code, string $file, PoDocument $document ) : void {
		$tokens  = token_get_all( $code );
		$count   = count( $tokens );
		$comment = null; // Last translators: comment and the line it ended on.

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( is_array( $token ) && in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT ], true ) ) {
				if ( preg_match( '/translators:\s*(.+?)\s*(?:\*\/)?$/is', $token[1], $m ) ) {
					$text    = trim( preg_replace( '/\s*\n\s*(?:\*|\/\/)?\s*/', ' ', $m[1] ) ?? $m[1] );
					$comment = [ 'translators: ' . $text, $token[2] + substr_count( $token[1], "\n" ) ];
				}
				continue;
			}

			if ( ! is_array( $token ) || ! in_array( $token[0], [ T_STRING, T_NAME_FULLY_QUALIFIED ], true ) ) {
				continue;
			}

			$function = ltrim( $token[1], '\\' );

			if ( ! isset( self::PHP_FUNCTIONS[ $function ] ) ) {
				continue;
			}

			// Skip method calls and declarations: ->__(), ::__(), function __().
			$prev = $this->previous_significant( $tokens, $i );

			if ( is_array( $prev ) && in_array( $prev[0], [ T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR ], true ) ) {
				continue;
			}

			$j = $this->next_significant( $tokens, $i );

			if ( null === $j || '(' !== $tokens[ $j ] ) {
				continue;
			}

			$line = $token[2];
			$args = $this->php_arguments( $tokens, $j );

			$note = ( null !== $comment && $line - $comment[1] <= 2 ) ? $comment[0] : null;
			$this->add( $document, self::PHP_FUNCTIONS[ $function ], $args, $file, $line, $note, $function );
			$comment = null;
		}
	}

	/**
	 * Index of the next non-whitespace, non-comment token.
	 */
	private function next_significant( array $tokens, int $i ) : ?int {
		for ( $k = $i + 1, $n = count( $tokens ); $k < $n; $k++ ) {
			if ( ! is_array( $tokens[ $k ] ) || ! in_array( $tokens[ $k ][0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
				return $k;
			}
		}

		return null;
	}

	/**
	 * The previous non-whitespace token.
	 */
	private function previous_significant( array $tokens, int $i ) : mixed {
		for ( $k = $i - 1; $k >= 0; $k-- ) {
			if ( ! is_array( $tokens[ $k ] ) || T_WHITESPACE !== $tokens[ $k ][0] ) {
				return $tokens[ $k ];
			}
		}

		return null;
	}

	/**
	 * Read call arguments starting at the opening parenthesis. Each argument
	 * is its literal string value, or null when it is not a plain literal.
	 *
	 * @return array<int, string|null>
	 */
	private function php_arguments( array $tokens, int $open ) : array {
		$args    = [];
		$depth   = 0;
		$literal = '';
		$plain   = true;
		$empty   = true;

		for ( $k = $open, $n = count( $tokens ); $k < $n; $k++ ) {
			$t = $tokens[ $k ];

			if ( '(' === $t || '[' === $t || '{' === $t ) {
				if ( 0 < $depth++ ) {
					$plain = false;
				}
				continue;
			}

			if ( ')' === $t || ']' === $t || '}' === $t ) {
				if ( 0 === --$depth ) {
					if ( ! $empty ) {
						$args[] = $plain ? $literal : null;
					}
					break;
				}
				continue;
			}

			if ( 1 === $depth && ',' === $t ) {
				$args[]  = $plain && ! $empty ? $literal : null;
				$literal = '';
				$plain   = true;
				$empty   = true;
				continue;
			}

			if ( is_array( $t ) && in_array( $t[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
				continue;
			}

			$empty = false;

			if ( 1 !== $depth ) {
				$plain = false;
				continue;
			}

			if ( is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
				$literal .= self::php_string_value( $t[1] );
			} elseif ( '.' !== $t ) {
				$plain = false;
			}
		}

		return $args;
	}

	/**
	 * The value of a PHP string literal token.
	 */
	private static function php_string_value( string $literal ) : string {
		$quote = $literal[0];
		$body  = substr( $literal, 1, -1 );

		if ( "'" === $quote ) {
			return strtr( $body, [ "\\\\" => "\\", "\\'" => "'" ] );
		}

		return stripcslashes( strtr( $body, [ '\\$' => '$' ] ) );
	}

	/*
	|-----------
	| JavaScript
	|-----------
	*/

	/**
	 * Extract from JavaScript source.
	 *
	 * Matches `__( ... )`, `_x()`, `_n()`, `_nx()` whether called bare or as
	 * a property (`smliserI18n.__()`, `i18n._n()`).
	 */
	public function extract_js( string $code, string $file, PoDocument $document ) : void {
		$pattern = '/(?<![\w$])(_nx|_n|_x|__)\s*\(/';
		$source  = $code;
		$code    = self::strip_js_comments( $code );

		if ( ! preg_match_all( $pattern, $code, $matches, PREG_OFFSET_CAPTURE ) ) {
			return;
		}

		foreach ( $matches[1] as $index => [ $name, $offset ] ) {
			// Skip definitions such as `function __(` or `__: function (`.
			$before = substr( $code, max( 0, $offset - 12 ), min( 12, $offset ) );

			if ( preg_match( '/function\s*$/', $before ) ) {
				continue;
			}

			$open = $matches[0][ $index ][1] + strlen( $matches[0][ $index ][0] ) - 1;
			$line = substr_count( $code, "\n", 0, $offset ) + 1;
			$args = $this->js_arguments( $code, $open );

			$note = null;
			$head = substr( $source, max( 0, $offset - 300 ), min( 300, $offset ) );

			if ( preg_match( '/(?:\/\/|\/\*)\s*translators:\s*([^\n]*?)\s*(?:\*\/)?\s*\n[^\n]*$/i', $head, $m ) ) {
				$note = 'translators: ' . $m[1];
			}

			$this->add( $document, self::JS_FUNCTIONS[ $name ], $args, $file, $line, $note, $name );
		}
	}

	/**
	 * Blank out JS comments (keeping offsets and line numbers) so examples
	 * in documentation are not extracted.
	 */
	private static function strip_js_comments( string $code ) : string {
		$out    = $code;
		$length = strlen( $code );
		$quote  = null;

		for ( $i = 0; $i < $length; $i++ ) {
			$c = $code[ $i ];

			if ( null !== $quote ) {
				if ( '\\' === $c ) {
					++$i;
				} elseif ( $c === $quote ) {
					$quote = null;
				}
				continue;
			}

			if ( '"' === $c || "'" === $c || '`' === $c ) {
				$quote = $c;
				continue;
			}

			if ( '/' !== $c || $i + 1 >= $length ) {
				continue;
			}

			$next = $code[ $i + 1 ];

			if ( '/' === $next ) {
				$end = strpos( $code, "\n", $i );
				$end = false === $end ? $length : $end;
			} elseif ( '*' === $next ) {
				$end = strpos( $code, '*/', $i + 2 );
				$end = false === $end ? $length : $end + 2;
			} else {
				continue;
			}

			for ( $k = $i; $k < $end; $k++ ) {
				if ( "\n" !== $code[ $k ] ) {
					$out[ $k ] = ' ';
				}
			}

			$i = $end - 1;
		}

		return $out;
	}

	/**
	 * Read JS call arguments: literal strings (and `'a' + 'b'`) or null.
	 *
	 * @return array<int, string|null>
	 */
	private function js_arguments( string $code, int $open ) : array {
		$args    = [];
		$depth   = 0;
		$literal = '';
		$plain   = true;
		$empty   = true;
		$length  = strlen( $code );

		for ( $k = $open; $k < $length; $k++ ) {
			$c = $code[ $k ];

			if ( '"' === $c || "'" === $c || '`' === $c ) {
				$end = $k + 1;

				while ( $end < $length && $code[ $end ] !== $c ) {
					$end += '\\' === $code[ $end ] ? 2 : 1;
				}

				$raw   = substr( $code, $k + 1, $end - $k - 1 );
				$empty = false;

				if ( 1 === $depth && ! ( '`' === $c && str_contains( $raw, '${' ) ) ) {
					$literal .= self::js_string_value( $raw );
				} else {
					$plain = false;
				}

				$k = $end;
				continue;
			}

			if ( '(' === $c || '[' === $c || '{' === $c ) {
				if ( 0 < $depth++ ) {
					$plain = false;
					$empty = false;
				}
				continue;
			}

			if ( ')' === $c || ']' === $c || '}' === $c ) {
				if ( 0 === --$depth ) {
					if ( ! $empty ) {
						$args[] = $plain ? $literal : null;
					}
					break;
				}
				continue;
			}

			if ( 1 === $depth && ',' === $c ) {
				$args[]  = $plain && ! $empty ? $literal : null;
				$literal = '';
				$plain   = true;
				$empty   = true;
				continue;
			}

			if ( ctype_space( $c ) || ( 1 === $depth && '+' === $c ) ) {
				continue;
			}

			$empty = false;
			$plain = false;
		}

		return $args;
	}

	/**
	 * Decode the body of a JS string literal.
	 */
	private static function js_string_value( string $raw ) : string {
		$raw = preg_replace_callback( '/\\\\u\{?([0-9a-fA-F]{4,6})\}?/', static fn ( array $m ) : string => mb_chr( (int) hexdec( $m[1] ), 'UTF-8' ), $raw ) ?? $raw;

		return stripcslashes( $raw );
	}

	/*
	|-----------
	| Shared
	|-----------
	*/

	/**
	 * Add a call's strings to the document.
	 *
	 * @param array<int, string|null> $roles
	 * @param array<int, string|null> $args
	 */
	private function add( PoDocument $document, array $roles, array $args, string $file, int $line, ?string $note, string $function ) : void {
		$values = [];

		foreach ( $roles as $index => $role ) {
			if ( null !== $role ) {
				$values[ $role ] = array_key_exists( $index, $args ) ? $args[ $index ] : false; // false: not given.
			}
		}

		$domain = $values['domain'] ?? false;

		if ( null === $domain ) {
			return; // Variable domain: cannot tell; skip.
		}

		$domain = ( false === $domain || 'default' === $domain || '' === $domain ) ? TranslatorInterface::DEFAULT_DOMAIN : $domain;

		if ( $domain !== $this->domain ) {
			return;
		}

		foreach ( [ 'text', 'plural', 'context' ] as $role ) {
			if ( array_key_exists( $role, $values ) && null === $values[ $role ] ) {
				$this->warnings[] = sprintf( '%s:%d: %s() is called with a non-literal %s, so it cannot be translated.', $file, $line, $function, $role );
				return;
			}
		}

		$text = $values['text'] ?? false;

		if ( false === $text || '' === $text ) {
			return;
		}

		$plural  = isset( $values['plural'] ) && false !== $values['plural'] ? $values['plural'] : null;
		$context = isset( $values['context'] ) && false !== $values['context'] ? $values['context'] : null;

		$entry = new PoEntry(
			msgid: $text,
			context: $context,
			plural: $plural,
			references: [ $file . ':' . $line ],
			extracted_comments: null === $note ? [] : [ $note ],
		);

		if ( preg_match( '/%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/', $text . (string) $plural ) ) {
			$entry->add_flag( str_ends_with( $file, '.js' ) ? 'javascript-format' : 'php-format' );
		}

		$document->add( $entry );
	}
}
