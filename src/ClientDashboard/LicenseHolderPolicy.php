<?php
/**
 * License holder policy class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard;

use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Actors\User;

/**
 * What the holder of a license may see and do in the client dashboard.
 *
 * A license is the holder's when it is linked to their account
 * (License::get_for_user()): its user ID is theirs and their email address
 * is verified. Any other license, someone else's or one that does not
 * exist, is "not found" to them, so its existence is not revealed.
 *
 * The holder may:
 *   - see the license, its activated sites and its activation history;
 *   - deactivate the license, e.g. when its key is compromised. It is then
 *     refused on every site; no site is freed.
 *
 * The holder may never free a site (remove, uninstall, reset or transfer an
 * activation) nor reactivate a deactivated license: both stay with the
 * owner of the licensed app and the system administrators, so a license
 * cannot be shared by freeing and reusing its sites.
 */
final class LicenseHolderPolicy {

	/**
	 * Whether a license is the user's.
	 *
	 * @param License   $license
	 * @param User|null $user The signed-in user; null for anyone else.
	 * @return bool
	 */
	public static function owns( License $license, ?User $user ) : bool {
		if ( null === $user || ! $user->is_email_verified() ) {
			return false;
		}

		$user_id = (int) $user->get_id();

		return $user_id > 0 && (int) $license->get_id() > 0 && $license->get_user_id() === $user_id;
	}

	/**
	 * The user's license by its ID; null when there is none or it is not theirs.
	 *
	 * @param int                          $license_id
	 * @param User|null                    $user
	 * @param (callable(int): ?License)|null $loader Finds a license by ID; License::get_by_id() by default.
	 * @return License|null
	 */
	public static function find( int $license_id, ?User $user, ?callable $loader = null ) : ?License {
		if ( $license_id <= 0 || null === $user || ! $user->is_email_verified() ) {
			return null;
		}

		$license = ( $loader ?? [ License::class, 'get_by_id' ] )( $license_id );

		return $license instanceof License && self::owns( $license, $user ) ? $license : null;
	}

	/**
	 * Statuses in which the license still works on sites
	 * (License::can_serve_license()), so its holder may deactivate it.
	 *
	 * An expired, suspended or revoked license is refused everywhere
	 * already, and its status is the owner's to change: deactivating it
	 * would hide why it was refused.
	 */
	public const DEACTIVATABLE_STATUSES = [
		License::STATUS_ACTIVE,
		License::STATUS_LIFETIME,
		License::STATUS_PENDING,
		License::STATUS_INACTIVE,
	];

	/**
	 * Whether the user may deactivate the license (report it as compromised).
	 *
	 * @param License   $license
	 * @param User|null $user
	 * @return bool
	 */
	public static function can_deactivate( License $license, ?User $user ) : bool {
		return self::owns( $license, $user ) && in_array( $license->get_status(), self::DEACTIVATABLE_STATUSES, true );
	}

	/**
	 * Whether the user may reactivate the license: never, for its holder.
	 *
	 * @param License   $license
	 * @param User|null $user
	 * @return false
	 */
	public static function can_reactivate( License $license, ?User $user ) : bool {
		return false;
	}

	/**
	 * Whether the user may free a site of the license (remove, uninstall,
	 * reset or transfer an activation): never, for its holder.
	 *
	 * @param License   $license
	 * @param User|null $user
	 * @return false
	 */
	public static function can_release_sites( License $license, ?User $user ) : bool {
		return false;
	}
}
