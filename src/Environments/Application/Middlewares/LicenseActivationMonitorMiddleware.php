<?php
/**
 * License activation monitoring middleware class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Middlewares
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Security\Licensing\LicenseMonitor;

/**
 * Limits activations of one license on one host.
 *
 * An honest client activates once and then reauthenticates; activating an
 * active domain again only returns a short-lived token, so repeating it is
 * token minting.
 */
class LicenseActivationMonitorMiddleware extends LicenseMonitorMiddleware {

	protected function action() : ?string {
		return LicenseMonitor::ACTION_ACTIVATE;
	}

	protected function subject( Request $request, string $license ) : string {
		return LicenseMonitor::host_subject( $license, $this->host_from_request( $request ) );
	}

	protected function limit( Request $request ) : ?int {
		return LicenseMonitor::HOST_ACTION_LIMIT;
	}
}
