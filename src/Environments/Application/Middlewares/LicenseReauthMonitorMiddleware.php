<?php
/**
 * Download token reauthentication monitoring middleware class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Middlewares
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Security\Licensing\LicenseMonitor;

/**
 * Limits reauthentications of one license on one host.
 *
 * A reauthenticated token lasts two weeks, so an honest client needs about
 * one every 13 days; many in an hour is token farming.
 */
class LicenseReauthMonitorMiddleware extends LicenseMonitorMiddleware {

	protected function action() : ?string {
		return LicenseMonitor::ACTION_REAUTH;
	}

	protected function subject( Request $request, string $license ) : string {
		return LicenseMonitor::host_subject( $license, $this->host_from_request( $request ) );
	}

	protected function limit( Request $request ) : ?int {
		return LicenseMonitor::HOST_ACTION_LIMIT;
	}
}
