<?php
/**
 * Account deletion class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Accounts
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Accounts;

use Callismart\DBPrism\Database;
use SmartLicenseServer\Core\AvatarManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use Throwable;

/**
 * A user deleting their own account (client dashboard → Account).
 *
 * ## When it is refused
 *
 * - The user hosts apps themselves (trashed ones included): they delete
 *   them or move them to another owner first, as for an organization.
 * - The user is the only active Organization Administrator of an
 *   organization: they make another member an administrator, or delete
 *   the organization, first.
 * - The user is the only active system administrator.
 *
 * ## What happens to their data
 *
 * One transaction, through the same deletion as the admin dashboard and
 * the CLI (ContextServiceProvider::delete_entity()), which removes the
 * user, their role assignments, organization memberships, resource owner
 * record and its service accounts. Before it, in the same transaction:
 *
 * - Licenses linked to the user are unlinked, not deleted. A license
 *   belongs to the purchase it came from; it keeps its licensee name and
 *   email, its activations keep working, and it is linked again if an
 *   account verifies that email address later (License::link_to_user()).
 * - Purchase requests are unlinked the same way; the buyer's name and
 *   email stay on the record, as the seller's bookkeeping.
 * - The user's own records go: preferences (user options), second
 *   factors and recovery codes, passkeys, pending sign-in challenges and
 *   identity provider links.
 *
 * After the commit, their avatar is removed. Logs are kept as they are:
 * the security log and job history are the site's audit trail, and refer
 * to the user only by ID and email address.
 *
 * There is no soft-delete: a "delete scheduled" status exists only for
 * WordPress users removed from WordPress, without a purge that would
 * finish the job, so deleting here is immediate and final.
 */
class AccountDeletion {

	/**
	 * @param Database      $db      The database.
	 * @param AvatarManager $avatars Removes the avatar.
	 */
	public function __construct(
		protected Database $db,
		protected AvatarManager $avatars
	) {}

	/**
	 * Why the user cannot delete their account yet; null when they can.
	 *
	 * @param User $user
	 * @return Exception|null With status 409 and the reasons in "reasons".
	 */
	public function refusal( User $user ) : ?Exception {
		return static::refusal_for(
			$this->owned_app_count( $user ),
			$this->sole_admin_organizations( $user ),
			$this->is_last_system_admin( $user )
		);
	}

	/**
	 * The refusal rules, from the facts about a user.
	 *
	 * @param int      $owned_apps          Apps the user hosts themselves, trashed ones included.
	 * @param string[] $sole_admin_of       Names of organizations the user is the only active administrator of.
	 * @param bool     $last_system_admin   Whether the user is the only active system administrator.
	 * @return Exception|null
	 */
	public static function refusal_for( int $owned_apps, array $sole_admin_of, bool $last_system_admin ) : ?Exception {
		$reasons = [];

		if ( $owned_apps > 0 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of apps. */
				smliser_n(
					'You host %d app, including any in the trash. Delete it, or move it to another owner, first.',
					'You host %d apps, including any in the trash. Delete them, or move them to another owner, first.',
					$owned_apps
				),
				$owned_apps
			);
		}

		foreach ( $sole_admin_of as $name ) {
			$reasons[] = sprintf(
				/* translators: %s: organization name. */
				smliser__( 'You are the only Organization Administrator of %s. Make another member an Organization Administrator, or delete the organization, first.' ),
				$name
			);
		}

		if ( $last_system_admin ) {
			$reasons[] = smliser__( 'You are the only active system administrator. Make another user an active system administrator first.' );
		}

		if ( [] === $reasons ) {
			return null;
		}

		return new Exception(
			'account_deletion_refused',
			smliser__( 'Your account cannot be deleted yet.' ) . ' ' . implode( ' ', $reasons ),
			[ 'status' => 409, 'reasons' => $reasons ]
		);
	}

	/**
	 * Delete the account, after refusal() returned null.
	 *
	 * @param User $user
	 * @return void
	 * @throws Exception When it is refused, or the deletion failed (nothing was changed then).
	 */
	public function delete( User $user ) : void {
		$refusal = $this->refusal( $user );

		if ( null !== $refusal ) {
			throw $refusal;
		}

		$id  = (int) $user->get_id();
		$uid = $user->get_unique_identifier();

		try {
			$this->db->transactional( function ( Database $db ) use ( $user, $id ) : bool {
				// A failed statement must undo the whole deletion.
				$ok = static function ( int|false $result, TableName $table ) : void {
					if ( false === $result ) {
						throw new \RuntimeException( sprintf( 'Could not update %s.', $table->value ) );
					}
				};

				// Kept, unlinked: they belong to the purchases they came from.
				foreach ( [ TableName::LICENSES, TableName::PURCHASE_REQUESTS ] as $table ) {
					$ok( $db->update( $table->table(), [ 'user_id' => null ], [ 'user_id' => $id ] ), $table );
				}

				// The user's own records.
				foreach ( [ TableName::USER_OPTIONS, TableName::USER_SECURITY, TableName::USER_PASSKEYS, TableName::AUTH_CHALLENGES, TableName::IDENTITY_PROVIDER_LOOKUP ] as $table ) {
					$ok( $db->delete( $table->table(), [ 'user_id' => $id ] ), $table );
				}

				// The user, roles, memberships, owner record and service accounts.
				ContextServiceProvider::delete_entity( $user );

				return true;
			} );
		} catch ( Throwable $e ) {
			\smliser_log_error( sprintf( '[Accounts] User #%d could not delete their account: %s', $id, $e->getMessage() ) );

			throw new Exception( 'account_not_deleted', smliser__( 'Your account could not be deleted. Nothing was changed. Please try again.' ), [ 'status' => 500 ] );
		}

		try {
			$this->avatars->delete( $user->get_type(), $uid );
		} catch ( Throwable ) {
			// An avatar left behind is not worth failing a deletion that is done.
		}
	}

	/*
	|---------------------------------------------
	| THE FACTS (protected: tests and extensions)
	|---------------------------------------------
	*/

	/**
	 * Apps the user hosts themselves, whatever their status.
	 *
	 * @param User $user
	 * @return int
	 */
	protected function owned_app_count( User $user ) : int {
		$owner = ContextServiceProvider::get_owner_for( $user );

		return $owner ? HostedApplicationService::count_apps_for_owner( (int) $owner->get_id() ) : 0;
	}

	/**
	 * Organizations nobody but the user runs.
	 *
	 * @param User $user
	 * @return string[] Their names.
	 */
	protected function sole_admin_organizations( User $user ) : array {
		$names = [];

		foreach ( ContextServiceProvider::get_user_organizations( $user ) ?? [] as $organization ) {
			$admins = [];

			foreach ( ContextServiceProvider::get_organization_admins( $organization ) as $admin ) {
				if ( $admin->can_authenticate() ) {
					$admins[ (int) $admin->get_id() ] = true;
				}
			}

			if ( isset( $admins[ (int) $user->get_id() ] ) && 1 === count( $admins ) ) {
				$names[] = $organization->get_display_name();
			}
		}

		return $names;
	}

	/**
	 * Whether the user is the only active system administrator.
	 *
	 * @param User $user
	 * @return bool
	 */
	protected function is_last_system_admin( User $user ) : bool {
		$is_admin = $user->can_authenticate()
			&& DefaultRoles::get( 'system_admin' )['slug'] === ContextServiceProvider::get_principal_role( $user )?->get_slug();

		return $is_admin && 0 === ContextServiceProvider::count_active_platform_admins( (int) $user->get_id() );
	}
}
