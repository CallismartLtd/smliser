<?php
/**
 * License app access class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Monetization
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;

/**
 * Who may work on a license through the app it was issued for.
 *
 * Fails closed: a license whose app cannot be resolved (deleted, or app
 * properties that name no app) belongs to no owner, so only a system
 * administrator may work on it; anyone else is told the license does not
 * exist. A license whose app exists may be worked on by the app's owner,
 * acting for that owner, or by a system administrator.
 *
 * Capabilities are the caller's check; this is ownership only.
 */
final class LicenseAppAccess {

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}

	/**
	 * Check the principal's access to a license, by its app.
	 *
	 * @param Principal|null         $principal The signed-in principal.
	 * @param AbstractHostedApp|null $app       The license's app (License::get_app()); null when it does not resolve.
	 * @return true|RequestException
	 */
	public static function check( ?Principal $principal, ?AbstractHostedApp $app ) : true|RequestException {
		if ( null === $principal ) {
			return new RequestException( 'missing_auth' );
		}

		if ( $principal->is( 'system_admin' ) ) {
			return true;
		}

		if ( null === $app || ! $app->exists() ) {
			return new RequestException( 'resource_not_found', smliser__( 'This license does not exist.' ), [ 'status' => 404 ] );
		}

		if ( ! $principal->get_owner()?->owns_app( $app ) ) {
			return new RequestException( 'unuathorized_app_access' );
		}

		return true;
	}

	/**
	 * Check the current principal's access to a license, throwing on failure.
	 *
	 * @param Guard   $guard
	 * @param License $license
	 * @return void
	 * @throws RequestException
	 */
	public static function assert( Guard $guard, License $license ) : void {
		$access = self::check( $guard->get_principal(), $license->get_app() );

		if ( $access instanceof RequestException ) {
			throw $access;
		}
	}
}
