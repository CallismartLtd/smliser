<?php
/**
 * License monitoring middleware class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Middlewares
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Licensing\LicenseMonitor;
use SmartLicenseServer\Security\SecurityLogger;
use Throwable;

/**
 * Applies LicenseMonitor to a license route.
 *
 * Before the route's guard: refuses a request (429, with Retry-After) from a
 * source or for a license with too many recent failures, or whose action
 * budget is used up. After the route: records a client error as a failure,
 * or a success against the action's budget.
 *
 * This class watches failures only, for routes with no action budget
 * (deactivation, validity test). Subclasses name the action and whose
 * budget it spends. If the monitor's cache fails, the route stays available
 * and the failure is logged, as with the authentication routes.
 */
class LicenseMonitorMiddleware implements MiddlewareInterface {

	/**
	 * Constructor.
	 *
	 * @param LicenseMonitor $monitor The license monitor.
	 * @param SecurityLogger $logger  The security logger.
	 */
	public function __construct(
		protected LicenseMonitor $monitor,
		protected SecurityLogger $logger
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Request $request, callable $next ) : mixed {
		$source  = $this->source_from_request( $request );
		$license = $this->license_from_request( $request );
		$action  = $this->action();
		$subject = null;

		try {
			if ( $this->monitor->is_blocked( $source, $license ) ) {
				return $this->blocked_response( LicenseMonitor::FAILURE_WINDOW );
			}

			if ( null !== $action && null !== $license ) {
				$subject = $this->subject( $request, $license );
				$limit   = $this->limit( $request );

				if ( null !== $limit && ! $this->monitor->allows( $action, $subject, $limit ) ) {
					return $this->blocked_response( LicenseMonitor::window( $action ) );
				}
			}
		} catch ( Throwable $e ) {
			$this->log( $e );
		}

		$response = $next( $request );
		$status   = $response instanceof Response ? $response->get_status_code() : 200;

		try {
			if ( $status >= 400 && $status < 500 && 429 !== $status ) {
				$this->monitor->record_failure( $source, $license );
			} elseif ( $status >= 200 && $status < 300 && null !== $action && null !== $subject ) {
				$this->monitor->record( $action, $subject );
			}
		} catch ( Throwable $e ) {
			$this->log( $e );
		}

		return $response;
	}

	/**
	 * The action this route spends from a budget; null to watch failures only.
	 *
	 * @return string|null One of the LicenseMonitor::ACTION_ constants.
	 */
	protected function action() : ?string {
		return null;
	}

	/**
	 * Whose budget the action spends.
	 *
	 * @param Request $request The request.
	 * @param string  $license The license subject.
	 * @return string
	 */
	protected function subject( Request $request, string $license ) : string {
		return $license;
	}

	/**
	 * Actions allowed in the action's window.
	 *
	 * @param Request $request The request.
	 * @return int|null Null to not limit this request.
	 */
	protected function limit( Request $request ) : ?int {
		return null;
	}

	/**
	 * The license subject of the request, from its credentials.
	 *
	 * @param Request $request The request.
	 * @return string|null Null when either credential is missing.
	 */
	protected function license_from_request( Request $request ) : ?string {
		$service_id  = $request->get( 'service_id' );
		$license_key = $request->get( 'license_key' );

		if ( ! is_string( $service_id ) || ! is_string( $license_key ) || '' === trim( $service_id ) || '' === trim( $license_key ) ) {
			return null;
		}

		return LicenseMonitor::license_subject( trim( $service_id ), trim( $license_key ) );
	}

	/**
	 * The host the request names, as activations are keyed.
	 *
	 * @param Request $request The request.
	 * @return string Empty when no usable domain is sent.
	 */
	protected function host_from_request( Request $request ) : string {
		$domain = $request->get( 'domain' );

		if ( ! is_string( $domain ) || '' === trim( $domain ) ) {
			return '';
		}

		try {
			return License::activation_host( trim( $domain ) );
		} catch ( Throwable ) {
			return '';
		}
	}

	/**
	 * The server's direct peer address. Forwarded headers are not trusted,
	 * as in AuthenticationMonitorMiddleware.
	 *
	 * @param Request $request The request.
	 * @return string
	 */
	protected function source_from_request( Request $request ) : string {
		$remote = (string) $request->server( 'REMOTE_ADDR', '' );

		return false !== filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unresolved_ip';
	}

	/**
	 * The response to a refused request.
	 *
	 * @param int $retry_after Seconds until it can be tried again, at most.
	 * @return Response
	 */
	protected function blocked_response( int $retry_after ) : Response {
		return Response::error(
			new RequestException( 'rate_limited', 'Too many license requests. Please wait and try again.', [ 'status' => 429 ] ),
			429,
			[ 'Retry-After' => (string) $retry_after ]
		);
	}

	/**
	 * Log a monitoring failure.
	 *
	 * @param Throwable $e The failure.
	 * @return void
	 */
	private function log( Throwable $e ) : void {
		$this->logger->critical(
			'license_monitor_middleware',
			sprintf( '[License Monitor] Could not check a license request: %s', $e->getMessage() )
		);
	}
}