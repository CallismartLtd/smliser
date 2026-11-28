<?php
/**
 * Password confirmation class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Accounts
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Accounts;

use SmartLicenseServer\Security\Actors\User;

/**
 * The gate in front of sensitive self-service changes: the signed-in user
 * types their current password again.
 *
 * A stolen session cookie alone then cannot change the email address or
 * password, download the account's data or delete the account.
 */
final class PasswordConfirmation {

	/**
	 * Whether the password is the user's current one.
	 *
	 * An empty password, or an account without a password hash, never matches.
	 *
	 * @param User   $user
	 * @param string $password As typed.
	 * @return bool
	 */
	public static function matches( User $user, #[\SensitiveParameter] string $password ) : bool {
		$hash = $user->get_password_hash();

		return '' !== $password && '' !== $hash && password_verify( $password, $hash );
	}
}
