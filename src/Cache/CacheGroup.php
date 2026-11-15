<?php
/**
 * Cache group class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Cache;

use InvalidArgumentException;

/**
 * A named set of cache entries that can be invalidated together without
 * touching anything else in the cache.
 *
 * Philosophy: invalidating data never destroys state. The cache holds two
 * kinds of entry: copies of data that can be rebuilt from the database, and
 * state that exists only in the cache (rate-limit counters, organization
 * invitations). A group holds the first kind; invalidating it makes every
 * entry in it unreachable, while state outside it is untouched. Only an
 * administrator's explicit "clear cache" flushes everything.
 *
 * Every adapter supports it the same way, with no adapter support needed:
 * the group's entries are stored under a generation token kept in one cache
 * key, and invalidate() replaces the token. Entries of the old generation
 * are never read again and expire on their own. So that they always do, an
 * entry stored "forever" (TTL 0) in a group lives FOREVER_TTL seconds.
 *
 * The token is random, never a counter: if the cache loses it, a new random
 * token is drawn, so old entries can never become reachable again.
 */
final class CacheGroup {

	/**
	 * Data copied from the database by data stores (apps, licenses, pricing,
	 * REST responses). Writes to any of it invalidate the whole group,
	 * because cached entries combine data from several tables.
	 *
	 * @var string
	 */
	public const DATA = 'data';

	/**
	 * Lifetime, in seconds, of an entry stored "forever" (TTL 0) in a group:
	 * one day, so entries orphaned by invalidate() are always removed.
	 *
	 * @var int
	 */
	public const FOREVER_TTL = 86400;

	/**
	 * Constructor.
	 *
	 * @param Cache  $cache The cache.
	 * @param string $name  Group name: lowercase letters, digits, "_" and "-".
	 * @throws InvalidArgumentException For an invalid name.
	 */
	public function __construct(
		private Cache $cache,
		public readonly string $name
	) {
		if ( 1 !== preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
			throw new InvalidArgumentException( sprintf( '"%s" is not a valid cache group name.', $name ) );
		}
	}

	/**
	 * Retrieve an entry.
	 *
	 * @param string $key Key within the group.
	 * @return mixed The value, or false when not found.
	 */
	public function get( string $key ) : mixed {
		return $this->cache->get( $this->key( $key ) );
	}

	/**
	 * Store an entry.
	 *
	 * @param string $key   Key within the group.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Seconds to keep it; 0 for FOREVER_TTL.
	 * @return bool
	 */
	public function set( string $key, mixed $value, int $ttl = 0 ) : bool {
		return $this->cache->set( $this->key( $key ), $value, $ttl > 0 ? $ttl : self::FOREVER_TTL );
	}

	/**
	 * Delete an entry.
	 *
	 * @param string $key Key within the group.
	 * @return bool
	 */
	public function delete( string $key ) : bool {
		return $this->cache->delete( $this->key( $key ) );
	}

	/**
	 * Whether an entry exists.
	 *
	 * @param string $key Key within the group.
	 * @return bool
	 */
	public function has( string $key ) : bool {
		return $this->cache->has( $this->key( $key ) );
	}

	/**
	 * Make every entry of the group unreachable. Nothing outside the group
	 * is touched.
	 *
	 * @return bool False when the new generation could not be stored; the
	 *              old entries then stay readable.
	 */
	public function invalidate() : bool {
		return $this->cache->set( $this->generation_key(), self::new_generation(), 0 );
	}

	/**
	 * The cache key of an entry in the current generation.
	 *
	 * @param string $key Key within the group.
	 * @return string
	 */
	private function key( string $key ) : string {
		return sprintf( 'smliser_group:%s:%s:%s', $this->name, $this->generation(), $key );
	}

	/**
	 * The current generation token, drawn when there is none.
	 *
	 * Read on every call rather than remembered, so long-running processes
	 * (queue workers) see another process's invalidation at once.
	 *
	 * @return string
	 */
	private function generation() : string {
		$generation = $this->cache->get( $this->generation_key() );

		if ( self::is_generation( $generation ) ) {
			return $generation;
		}

		// Draw atomically, so concurrent requests agree on one token.
		$generation = $this->cache->modify(
			$this->generation_key(),
			static fn( mixed $current ) : string => self::is_generation( $current ) ? $current : self::new_generation(),
			0,
			null
		);

		// Without a working cache nothing is stored anyway; any token will do.
		return self::is_generation( $generation ) ? $generation : self::new_generation();
	}

	/**
	 * The key holding the group's generation token.
	 *
	 * @return string
	 */
	private function generation_key() : string {
		return sprintf( 'smliser_group:%s:generation', $this->name );
	}

	/**
	 * A new random generation token.
	 *
	 * @return string
	 */
	private static function new_generation() : string {
		return bin2hex( random_bytes( 8 ) );
	}

	/**
	 * Whether a stored value is a generation token.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	private static function is_generation( mixed $value ) : bool {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{16}$/', $value );
	}
}