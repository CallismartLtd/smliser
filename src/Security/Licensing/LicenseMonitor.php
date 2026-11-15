<?php
/**
 * License API abuse monitoring.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Licensing
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Licensing;

use InvalidArgumentException;
use SmartLicenseServer\Cache\Cache;

/**
 * Watches the license routes a licensed app calls, and says when a caller
 * has gone past what an honest client needs.
 *
 * It decides nothing about licenses themselves; LicenseMonitorMiddleware
 * asks it before a request and tells it what happened after. Two kinds of
 * bucket:
 *
 *  - Failures: failed license requests per source (key guessing) and per
 *    license (secret guessing against one license), over 15 minutes. While
 *    either is full, every license request from that source, or for that
 *    license, is refused.
 *  - Actions: successful actions per subject, each over its own window:
 *      - activate: activations of one license on one host, 10 per hour
 *        (an honest client activates once, then reauthenticates);
 *      - reauth: reauthentications of one license on one host, 10 per hour
 *        (an honest client needs one about every 13 days);
 *      - uninstall: uninstalls of one license, as many per 24 hours as the
 *        license allows domains, or 10 for unlimited (domain hopping).
 *    Only successful actions are counted, so a caller without the
 *    credentials cannot use up an honest client's budget.
 *
 * Events live in the cache, capped per bucket, and expire with their
 * window, so nothing grows without bound. Cache keys and records hold
 * keyed hashes, never raw license keys, hosts or addresses. When the cache
 * does not persist between requests (the runtime adapter), nothing is
 * remembered and nothing is refused.
 */
final class LicenseMonitor {

	/**
	 * Successful activations of a license on a host.
	 *
	 * @var string
	 */
	public const ACTION_ACTIVATE = 'activate';

	/**
	 * Successful reauthentications of a license on a host.
	 *
	 * @var string
	 */
	public const ACTION_REAUTH = 'reauth';

	/**
	 * Successful uninstalls of a license.
	 *
	 * @var string
	 */
	public const ACTION_UNINSTALL = 'uninstall';

	/**
	 * Window of the failure buckets, in seconds (15 minutes).
	 *
	 * @var int
	 */
	public const FAILURE_WINDOW = 900;

	/**
	 * Failed license requests allowed per source in the failure window.
	 *
	 * @var int
	 */
	public const SOURCE_FAILURE_LIMIT = 15;

	/**
	 * Failed license requests allowed per license in the failure window.
	 *
	 * @var int
	 */
	public const LICENSE_FAILURE_LIMIT = 10;

	/**
	 * Window of each action bucket, in seconds.
	 *
	 * @var array<string, int>
	 */
	public const ACTION_WINDOWS = [
		self::ACTION_ACTIVATE  => 3600,
		self::ACTION_REAUTH    => 3600,
		self::ACTION_UNINSTALL => 86400,
	];

	/**
	 * Successful activations or reauthentications allowed per license and
	 * host in their window.
	 *
	 * @var int
	 */
	public const HOST_ACTION_LIMIT = 10;

	/**
	 * Uninstalls allowed per day for a license with unlimited domains.
	 *
	 * @var int
	 */
	public const UNLIMITED_UNINSTALL_LIMIT = 10;

	/**
	 * Cache key namespace.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'smliser_license_monitor';

	/**
	 * Most events kept in one bucket; above every limit, so counting stays exact.
	 *
	 * @var int
	 */
	private const MAX_EVENTS = 100;

	/**
	 * Constructor.
	 *
	 * @param Cache  $cache    Holds the buckets.
	 * @param string $hash_key Secret the identifiers in cache keys are hashed with.
	 */
	public function __construct(
		private Cache $cache,
		#[\SensitiveParameter] private string $hash_key
	) {}

	/*
	|-----------
	| Failures
	|-----------
	*/

	/**
	 * Whether license requests from a source, or for a license, are refused.
	 *
	 * @param string      $source  The request source, normally the client IP.
	 * @param string|null $license The license credentials (see license_subject()); null when not sent.
	 * @return bool
	 */
	public function is_blocked( string $source, ?string $license ) : bool {
		$now = time();

		if ( count( $this->events( 'failure_source', $source, self::FAILURE_WINDOW, $now ) ) >= self::SOURCE_FAILURE_LIMIT ) {
			return true;
		}

		return null !== $license
			&& count( $this->events( 'failure_license', $license, self::FAILURE_WINDOW, $now ) ) >= self::LICENSE_FAILURE_LIMIT;
	}

	/**
	 * Record a failed license request.
	 *
	 * @param string      $source  The request source.
	 * @param string|null $license The license credentials; null when not sent.
	 * @return void
	 * @throws \RuntimeException When the cache cannot record it.
	 */
	public function record_failure( string $source, ?string $license ) : void {
		$this->append( 'failure_source', $source, self::FAILURE_WINDOW );

		if ( null !== $license ) {
			$this->append( 'failure_license', $license, self::FAILURE_WINDOW );
		}
	}

	/*
	|----------
	| Actions
	|----------
	*/

	/**
	 * Whether one more action fits in a subject's budget.
	 *
	 * @param string $action  One of the ACTION_ constants.
	 * @param string $subject What the budget belongs to (see license_subject() and host_subject()).
	 * @param int    $limit   Actions allowed in the action's window.
	 * @return bool
	 * @throws InvalidArgumentException For an unknown action.
	 */
	public function allows( string $action, string $subject, int $limit ) : bool {
		return count( $this->events( 'action_' . $action, $subject, self::window( $action ), time() ) ) < $limit;
	}

	/**
	 * Record a successful action.
	 *
	 * @param string $action  One of the ACTION_ constants.
	 * @param string $subject What the budget belongs to.
	 * @return void
	 * @throws InvalidArgumentException For an unknown action.
	 * @throws \RuntimeException When the cache cannot record it.
	 */
	public function record( string $action, string $subject ) : void {
		$this->append( 'action_' . $action, $subject, self::window( $action ) );
	}

	/**
	 * Uninstalls allowed per day for a license.
	 *
	 * @param int $max_allowed_domains The license's domain limit; -1 for unlimited.
	 * @return int
	 */
	public static function uninstall_limit( int $max_allowed_domains ) : int {
		return $max_allowed_domains < 0 ? self::UNLIMITED_UNINSTALL_LIMIT : max( 1, $max_allowed_domains );
	}

	/**
	 * Seconds until a refused action can be tried again, at most.
	 *
	 * @param string $action One of the ACTION_ constants.
	 * @return int
	 * @throws InvalidArgumentException For an unknown action.
	 */
	public static function window( string $action ) : int {
		if ( ! isset( self::ACTION_WINDOWS[ $action ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown license action "%s".', $action ) );
		}

		return self::ACTION_WINDOWS[ $action ];
	}

	/*
	|-----------
	| Subjects
	|-----------
	*/

	/**
	 * The subject for a license's buckets.
	 *
	 * @param string $service_id  The service ID.
	 * @param string $license_key The license key.
	 * @return string
	 */
	public static function license_subject( string $service_id, string $license_key ) : string {
		return $service_id . "\0" . $license_key;
	}

	/**
	 * The subject for a license's buckets on one host.
	 *
	 * @param string $license_subject From license_subject().
	 * @param string $host            Lowercase host of the activation.
	 * @return string
	 */
	public static function host_subject( string $license_subject, string $host ) : string {
		return $license_subject . "\0" . strtolower( $host );
	}

	/*
	|-----------
	| Storage
	|-----------
	*/

	/**
	 * Add an event to a bucket and return the bucket.
	 *
	 * @param string $bucket  Bucket name.
	 * @param string $subject Subject.
	 * @param int    $window  Seconds an event counts for.
	 * @return array<int, int> Event timestamps.
	 * @throws \RuntimeException When the cache cannot record it.
	 */
	private function append( string $bucket, string $subject, int $window ) : array {
		$now    = time();
		$events = $this->cache->modify(
			$this->key( $bucket, $subject ),
			static function ( $current ) use ( $now, $window ) : array {
				$events   = self::recent( is_array( $current ) ? $current : [], $window, $now );
				$events[] = $now;

				return array_slice( $events, -self::MAX_EVENTS );
			},
			$window,
			[]
		);

		if ( ! is_array( $events ) ) {
			throw new \RuntimeException( 'The license monitoring cache could not record an event.' );
		}

		return $events;
	}

	/**
	 * The events of a bucket within its window.
	 *
	 * @param string $bucket  Bucket name.
	 * @param string $subject Subject.
	 * @param int    $window  Seconds an event counts for.
	 * @param int    $now     Current time.
	 * @return array<int, int> Event timestamps.
	 */
	private function events( string $bucket, string $subject, int $window, int $now ) : array {
		$events = $this->cache->get( $this->key( $bucket, $subject ) );

		return self::recent( is_array( $events ) ? $events : [], $window, $now );
	}

	/**
	 * Keep the timestamps within a window.
	 *
	 * @param array<mixed> $events Stored events.
	 * @param int          $window Seconds an event counts for.
	 * @param int          $now    Current time.
	 * @return array<int, int>
	 */
	private static function recent( array $events, int $window, int $now ) : array {
		return array_values(
			array_filter(
				array_map( 'intval', array_filter( $events, 'is_numeric' ) ),
				static fn( int $timestamp ) : bool => $now - $timestamp <= $window
			)
		);
	}

	/**
	 * The cache key of a bucket.
	 *
	 * @param string $bucket  Bucket name.
	 * @param string $subject Subject.
	 * @return string
	 */
	private function key( string $bucket, string $subject ) : string {
		return sprintf( '%s:%s:%s', self::CACHE_PREFIX, $bucket, hash_hmac( 'sha256', $bucket . ':' . $subject, $this->hash_key ) );
	}
}
