<?php
/**
 * Gettext plural forms class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n;

use Closure;
use InvalidArgumentException;

/**
 * Parses and evaluates a gettext `Plural-Forms` header, such as
 * `nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);`
 *
 * The expression is compiled by a small recursive-descent parser into a
 * closure tree. It never uses eval(), so a catalog from an untrusted
 * translator cannot execute code.
 */
final class PluralForms {

	/**
	 * The English rule, used when a catalog does not declare one.
	 */
	public const DEFAULT_HEADER = 'nplurals=2; plural=(n != 1);';

	/**
	 * Plural-Forms headers of common languages, for new translation files.
	 *
	 * @var array<string, string>
	 */
	private const LANGUAGE_RULES = [
		'nplurals=1; plural=0;'                => 'id ig ja ko ms th vi yo zh',
		'nplurals=2; plural=(n != 1);'         => 'af bg ca da de el en eo es et eu fi gl ha he hu it ka kk lb ml nb nl nn no pt_PT sq sv sw ta te tr ur',
		'nplurals=2; plural=(n > 1);'          => 'ak am fil fr hy ln mg oc pt pt_BR ti wa',
		'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);' => 'be bs hr ru sr uk',
		'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;' => 'cs sk',
		'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);' => 'pl',
		'nplurals=3; plural=(n==1 ? 0 : (n==0 || (n%100 > 0 && n%100 < 20)) ? 1 : 2);' => 'ro',
		'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 2);' => 'lt',
		'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n != 0 ? 1 : 2);' => 'lv',
		'nplurals=4; plural=(n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3);' => 'sl',
		'nplurals=5; plural=(n==1 ? 0 : n==2 ? 1 : n<7 ? 2 : n<11 ? 3 : 4);' => 'ga',
		'nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);' => 'ar',
		'nplurals=2; plural=(n==0 || n==1) ? 0 : 1;' => 'hi bn fa gu kn mr pa si zu',
	];

	/**
	 * The Plural-Forms header for a locale, defaulting to the English rule.
	 */
	public static function header_for_locale( Locale $locale ) : string {
		foreach ( $locale->fallback_chain() as $code ) {
			foreach ( self::LANGUAGE_RULES as $header => $codes ) {
				if ( in_array( $code, explode( ' ', $codes ), true ) ) {
					return $header;
				}
			}
		}

		return self::DEFAULT_HEADER;
	}

	/**
	 * Maximum expression length accepted, to bound parsing work.
	 */
	private const MAX_LENGTH = 512;

	/**
	 * Tokens of the expression being parsed.
	 *
	 * @var array<int, string|int>
	 */
	private array $tokens = [];

	/**
	 * Read position in $tokens.
	 */
	private int $pos = 0;

	/**
	 * The compiled expression.
	 *
	 * @var Closure(int): int
	 */
	private Closure $evaluator;

	/**
	 * Memoized results for small n.
	 *
	 * @var array<int, int>
	 */
	private array $memo = [];

	/**
	 * Constructor.
	 *
	 * @param int    $count      Number of plural forms (nplurals).
	 * @param string $expression The C-like plural expression.
	 * @throws InvalidArgumentException When the expression cannot be parsed.
	 */
	public function __construct( private int $count, private string $expression ) {
		if ( $count < 1 || $count > 6 ) {
			throw new InvalidArgumentException( 'nplurals must be between 1 and 6.' );
		}

		if ( strlen( $expression ) > self::MAX_LENGTH ) {
			throw new InvalidArgumentException( 'Plural expression is too long.' );
		}

		$this->tokens = $this->tokenize( $expression );
		$this->pos    = 0;
		$compiled     = $this->parse_ternary();

		if ( $this->pos !== count( $this->tokens ) ) {
			throw new InvalidArgumentException( sprintf( 'Unexpected token in plural expression "%s".', $expression ) );
		}

		$this->evaluator = $compiled;
		$this->tokens    = [];
	}

	/**
	 * Build from a `Plural-Forms` header value.
	 *
	 * @param string|null $header
	 * @return self
	 */
	public static function from_header( ?string $header ) : self {
		$header = trim( (string) $header );

		if ( '' === $header ) {
			$header = self::DEFAULT_HEADER;
		}

		if ( ! preg_match( '/nplurals\s*=\s*(\d+)\s*;\s*plural\s*=\s*(.+?)\s*;?\s*$/s', $header, $m ) ) {
			throw new InvalidArgumentException( sprintf( 'Invalid Plural-Forms header "%s".', $header ) );
		}

		return new self( (int) $m[1], $m[2] );
	}

	/**
	 * Build from a header, falling back to the English rule when it is invalid.
	 */
	public static function from_header_or_default( ?string $header ) : self {
		try {
			return self::from_header( $header );
		} catch ( InvalidArgumentException ) {
			return self::from_header( self::DEFAULT_HEADER );
		}
	}

	/**
	 * Number of plural forms.
	 */
	public function count() : int {
		return $this->count;
	}

	/**
	 * The original expression.
	 */
	public function expression() : string {
		return $this->expression;
	}

	/**
	 * The normalized header value.
	 */
	public function header() : string {
		return sprintf( 'nplurals=%d; plural=%s;', $this->count, $this->expression );
	}

	/**
	 * The index of the plural form to use for $n, clamped to [0, count - 1].
	 */
	public function index( int $n ) : int {
		$n = abs( $n );

		if ( $n < 1000 && isset( $this->memo[ $n ] ) ) {
			return $this->memo[ $n ];
		}

		$index = ( $this->evaluator )( $n );
		$index = max( 0, min( $this->count - 1, $index ) );

		if ( $n < 1000 ) {
			$this->memo[ $n ] = $index;
		}

		return $index;
	}

	/*
	|-----------
	| PARSER
	|-----------
	*/

	/**
	 * Split the expression into tokens.
	 *
	 * @return array<int, string|int>
	 */
	private function tokenize( string $expression ) : array {
		$tokens = [];
		$length = strlen( $expression );
		$i      = 0;

		while ( $i < $length ) {
			$char = $expression[ $i ];

			if ( ctype_space( $char ) ) {
				++$i;
				continue;
			}

			if ( ctype_digit( $char ) ) {
				$start = $i;
				while ( $i < $length && ctype_digit( $expression[ $i ] ) ) {
					++$i;
				}
				$tokens[] = (int) substr( $expression, $start, $i - $start );
				continue;
			}

			$two = substr( $expression, $i, 2 );

			if ( in_array( $two, [ '==', '!=', '<=', '>=', '&&', '||' ], true ) ) {
				$tokens[] = $two;
				$i       += 2;
				continue;
			}

			if ( str_contains( 'n()?:<>!%*/+-', $char ) ) {
				$tokens[] = $char;
				++$i;
				continue;
			}

			throw new InvalidArgumentException( sprintf( 'Invalid character "%s" in plural expression.', $char ) );
		}

		return $tokens;
	}

	/**
	 * Peek at the current token.
	 */
	private function peek() : string|int|null {
		return $this->tokens[ $this->pos ] ?? null;
	}

	/**
	 * Consume the current token if it equals $token.
	 */
	private function accept( string $token ) : bool {
		if ( $this->peek() === $token ) {
			++$this->pos;
			return true;
		}

		return false;
	}

	/**
	 * Consume $token or fail.
	 */
	private function expect( string $token ) : void {
		if ( ! $this->accept( $token ) ) {
			throw new InvalidArgumentException( sprintf( 'Expected "%s" in plural expression.', $token ) );
		}
	}

	/**
	 * ternary := or ( '?' ternary ':' ternary )?
	 *
	 * @return Closure(int): int
	 */
	private function parse_ternary() : Closure {
		$condition = $this->parse_binary( 0 );

		if ( ! $this->accept( '?' ) ) {
			return $condition;
		}

		$then = $this->parse_ternary();
		$this->expect( ':' );
		$else = $this->parse_ternary();

		return static fn ( int $n ) : int => $condition( $n ) ? $then( $n ) : $else( $n );
	}

	/**
	 * Binary operators by precedence level, lowest first.
	 */
	private const LEVELS = [
		[ '||' ],
		[ '&&' ],
		[ '==', '!=' ],
		[ '<', '>', '<=', '>=' ],
		[ '+', '-' ],
		[ '*', '/', '%' ],
	];

	/**
	 * Left-associative binary operators at a precedence level.
	 *
	 * @return Closure(int): int
	 */
	private function parse_binary( int $level ) : Closure {
		if ( $level >= count( self::LEVELS ) ) {
			return $this->parse_unary();
		}

		$left = $this->parse_binary( $level + 1 );

		while ( is_string( $op = $this->peek() ) && in_array( $op, self::LEVELS[ $level ], true ) ) {
			++$this->pos;
			$right = $this->parse_binary( $level + 1 );
			$left  = self::binary( $op, $left, $right );
		}

		return $left;
	}

	/**
	 * Build a binary operation closure.
	 *
	 * @param Closure(int): int $l
	 * @param Closure(int): int $r
	 * @return Closure(int): int
	 */
	private static function binary( string $op, Closure $l, Closure $r ) : Closure {
		return match ( $op ) {
			'||' => static fn ( int $n ) : int => (int) ( $l( $n ) || $r( $n ) ),
			'&&' => static fn ( int $n ) : int => (int) ( $l( $n ) && $r( $n ) ),
			'==' => static fn ( int $n ) : int => (int) ( $l( $n ) === $r( $n ) ),
			'!=' => static fn ( int $n ) : int => (int) ( $l( $n ) !== $r( $n ) ),
			'<'  => static fn ( int $n ) : int => (int) ( $l( $n ) < $r( $n ) ),
			'>'  => static fn ( int $n ) : int => (int) ( $l( $n ) > $r( $n ) ),
			'<=' => static fn ( int $n ) : int => (int) ( $l( $n ) <= $r( $n ) ),
			'>=' => static fn ( int $n ) : int => (int) ( $l( $n ) >= $r( $n ) ),
			'+'  => static fn ( int $n ) : int => $l( $n ) + $r( $n ),
			'-'  => static fn ( int $n ) : int => $l( $n ) - $r( $n ),
			'*'  => static fn ( int $n ) : int => $l( $n ) * $r( $n ),
			'/'  => static fn ( int $n ) : int => 0 === ( $d = $r( $n ) ) ? 0 : intdiv( $l( $n ), $d ),
			'%'  => static fn ( int $n ) : int => 0 === ( $d = $r( $n ) ) ? 0 : $l( $n ) % $d,
		};
	}

	/**
	 * unary := '!' unary | primary
	 *
	 * @return Closure(int): int
	 */
	private function parse_unary() : Closure {
		if ( $this->accept( '!' ) ) {
			$operand = $this->parse_unary();
			return static fn ( int $n ) : int => (int) ! $operand( $n );
		}

		return $this->parse_primary();
	}

	/**
	 * primary := 'n' | integer | '(' ternary ')'
	 *
	 * @return Closure(int): int
	 */
	private function parse_primary() : Closure {
		$token = $this->peek();

		if ( 'n' === $token ) {
			++$this->pos;
			return static fn ( int $n ) : int => $n;
		}

		if ( is_int( $token ) ) {
			++$this->pos;
			return static fn ( int $n ) : int => $token;
		}

		if ( $this->accept( '(' ) ) {
			$inner = $this->parse_ternary();
			$this->expect( ')' );
			return $inner;
		}

		throw new InvalidArgumentException( 'Unexpected end of plural expression.' );
	}
}
