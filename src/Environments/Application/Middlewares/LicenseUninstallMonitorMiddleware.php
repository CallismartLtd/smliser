<?php
/**
 * License uninstallation monitoring middleware class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Middlewares
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Licensing\LicenseMonitor;

/**
 * Limits uninstalls of one license per day to the domains it allows.
 *
 * Uninstalling needs no site secret, so without a limit a license could be
 * moved from site to site faster than its domain limit means: uninstall,
 * activate elsewhere, repeat.
 */
class LicenseUninstallMonitorMiddleware extends LicenseMonitorMiddleware {

	protected function action() : ?string {
		return LicenseMonitor::ACTION_UNINSTALL;
	}

	/**
	 * The license's own limit; none when the license does not exist, which
	 * the route's guard refuses and the monitor counts as a failure.
	 */
	protected function limit( Request $request ) : ?int {
		$license = License::get_license( (string) $request->get( 'service_id' ), (string) $request->get( 'license_key' ) );

		return $license ? LicenseMonitor::uninstall_limit( (int) $license->get_max_allowed_domains( 'raw' ) ) : null;
	}
}
