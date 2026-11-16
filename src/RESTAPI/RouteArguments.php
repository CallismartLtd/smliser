<?php
/**
 * REST route arguments class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI
 */

declare( strict_types=1 );

namespace SmartLicenseServer\RESTAPI;

use LogicException;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;

/**
 * Enforces the arguments a REST route declares.
 *
 * Philosophy: a route's "args" are its contract. A request that breaks the
 * contract is the client's mistake and is answered 400 before any guard or
 * handler runs; a request that keeps it reaches them with every declared
 * argument in its declared PHP type. Handlers never see a value of the
 * wrong shape.
 *
 * An argument is absent when the request does not send it, or sends null.
 * A required argument must be present and not "" (or [] for arrays); an
 * absent optional argument takes its "default", if it declares one.
 *
 * Types:
 *  - string:  a string; a number is accepted as its string form.
 *  - integer: an integer, or a string of digits with an optional sign,
 *             within PHP's integer range. Stored as int. An optional
 *             integer sent as "" is absent.
 *  - array:   a list whose items are strings or numbers, or one string or
 *             number, kept as sent (handlers may split it). A list holding
 *             lists or objects is refused.
 *  - binary:  an uploaded file under the argument's name. Checked only for
 *             presence when required.
 *
 * An argument without a type is checked only for presence. A type not in
 * this list is a mistake in the route and fails when the route is built.
 */
final class RouteArguments {

	/**
	 * The types an argument may declare.
	 *
	 * @var string[]
	 */
	public const TYPES = [ 'string', 'integer', 'array', 'binary' ];

	/**
	 * Constructor.
	 *
	 * @param array<string, array<string, mixed>> $args  The route's args, by name.
	 * @param string                              $route The route pattern, for error messages.
	 * @throws LogicException When an argument declares an unknown type.
	 */
	public function __construct( private array $args, string $route = '' ) {
		foreach ( $args as $name => $config ) {
			$type = $config['type'] ?? null;

			if ( null !== $type && ! in_array( $type, self::TYPES, true ) ) {
				throw new LogicException( sprintf( 'Argument "%s" of REST route "%s" declares the unknown type "%s"; use one of: %s.', $name, $route, (string) $type, implode( ', ', self::TYPES ) ) );
			}
		}
	}

	/**
	 * Check a request against the arguments, and store each present
	 * argument in its declared type and each absent one's default.
	 *
	 * @param Request $request The request.
	 * @return true|RequestException True when the request keeps the contract.
	 */
	public function enforce( Request $request ) : true|RequestException {
		foreach ( $this->args as $name => $config ) {
			$name     = (string) $name;
			$type     = $config['type'] ?? null;
			$required = ! empty( $config['required'] );

			if ( 'binary' === $type ) {
				if ( $required && null === $request->get_files( $name ) ) {
					return self::missing( $name, 'required_file' );
				}

				continue;
			}

			$value = $request->get( $name, null, false );

			if ( null === $value || ( '' === $value && 'integer' === $type && ! $required ) ) {
				if ( $required ) {
					return self::missing( $name );
				}

				if ( array_key_exists( 'default', $config ) ) {
					$request->set( $name, $config['default'] );
				}

				continue;
			}

			if ( $required && ( '' === $value || [] === $value ) ) {
				return self::missing( $name );
			}

			$typed = match ( $type ) {
				'string'  => self::to_string( $value ),
				'integer' => self::to_integer( $value ),
				'array'   => self::to_list( $value ),
				default   => $value,
			};

			if ( null === $typed ) {
				return new RequestException( 'invalid_parameter_type', sprintf( 'The "%s" parameter must be %s.', $name, self::describe( (string) $type ) ), [ 'status' => 400, 'param' => $name ] );
			}

			if ( $typed !== $value ) {
				$request->set( $name, $typed );
			}
		}

		return true;
	}

	/*
	|------------
	| Coercions
	|------------
	*/

	/**
	 * A value as a string.
	 *
	 * @param mixed $value Value.
	 * @return string|null Null when it is not a string or a number.
	 */
	private static function to_string( mixed $value ) : ?string {
		return match ( true ) {
			is_string( $value )                     => $value,
			is_int( $value ) || is_float( $value ) => (string) $value,
			default                                 => null,
		};
	}

	/**
	 * A value as an integer.
	 *
	 * @param mixed $value Value.
	 * @return int|null Null when it is not an integer or a string of one in range.
	 */
	private static function to_integer( mixed $value ) : ?int {
		if ( is_int( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^[+-]?\d+$/', $value ) ) {
			return null;
		}

		$int = filter_var( $value, FILTER_VALIDATE_INT );

		return false === $int ? null : $int;
	}

	/**
	 * A value as a list of strings and numbers, or one string or number.
	 *
	 * @param mixed $value Value.
	 * @return array<array-key, string|int|float>|string|int|float|null Null when it holds anything else.
	 */
	private static function to_list( mixed $value ) : array|string|int|float|null {
		if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return null;
		}

		foreach ( $value as $item ) {
			if ( ! is_string( $item ) && ! is_int( $item ) && ! is_float( $item ) ) {
				return null;
			}
		}

		return $value;
	}

	/*
	|----------
	| Helpers
	|----------
	*/

	/**
	 * The error for a missing required argument.
	 *
	 * @param string $name Argument name.
	 * @param string $slug Error slug.
	 * @return RequestException
	 */
	private static function missing( string $name, string $slug = 'required_param' ) : RequestException {
		$what = 'required_file' === $slug ? 'file' : 'parameter';

		return new RequestException( $slug, sprintf( 'The "%s" %s is required.', $name, $what ), [ 'status' => 400, 'param' => $name ] );
	}

	/**
	 * What a type accepts, for error messages.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	private static function describe( string $type ) : string {
		return match ( $type ) {
			'string'  => 'a string',
			'integer' => 'an integer',
			'array'   => 'a list of strings or numbers',
			default   => $type,
		};
	}
}
