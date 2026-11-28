<?php
/**
 * Client app license actions class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\ClientDashboard\AppOwnerScope;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\LicensingService;

/**
 * The client dashboard's actions on the licenses of the current owner's
 * apps (<client dashboard prefix>/json/app-licenses/…).
 *
 *   POST app-licenses/free-site   monetization.license.uninstall_domain   ("license_id", "domain")
 *   POST app-licenses/status      monetization.license.update             ("license_id", "license_action":
 *                                 reactivate (a deactivated license), suspend, unsuspend (a suspended one))
 *
 * Every action looks the license up by ID and its app by the license's own
 * app properties, never by app IDs or types in the request, and acts only
 * when that app is the current owner's (AppOwnerScope): 404 otherwise, and
 * 403 when the role lacks the capability. A license holder cannot free a
 * site or reactivate their license here: freeing sites is the app owner's
 * call, which is what stops a license being passed from site to site.
 *
 * The rules are LicensingService's, shared with the admin dashboard. The
 * answer is { success, message, redirect? } for page-forms.js.
 */
final class AppLicenseActions {

	/**
	 * @param AppOwnerScope    $scope      The current owner's licenses.
	 * @param LicensingService $licensing  The Licensing API.
	 * @param URLManager       $urlmanager Redirects.
	 */
	public function __construct(
		private AppOwnerScope $scope,
		private LicensingService $licensing,
		private URLManager $urlmanager
	) {}

	/**
	 * Free a site: remove a domain from the license's activations.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function free_site( Request $request ) : Response {
		$license = $this->scope->license( (int) $request->get( 'license_id', 0 ), AppOwnerScope::CAP_FREE_SITE );

		if ( ! $license instanceof License ) {
			return $this->failure( $license );
		}

		$domain = trim( (string) $request->get( 'domain', '' ) );
		$result = $this->licensing->free_site( $license, $domain, $this->activity( $request ) );

		if ( $result instanceof Exception ) {
			return $this->failure( $result );
		}

		/* translators: %s: the site's domain. */
		return $this->success( sprintf( smliser__( '%s is freed. It can no longer use this license, and the license can be activated on another site.' ), License::activation_host( $domain ) ), $this->license_url( $license ) );
	}

	/**
	 * Reactivate, suspend or unsuspend a license.
	 *
	 * Reactivating and unsuspending return the license to the status its
	 * dates give (active, lifetime, expired, ...).
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function status( Request $request ) : Response {
		$license = $this->scope->license( (int) $request->get( 'license_id', 0 ), AppOwnerScope::CAP_CHANGE_STATUS );

		if ( ! $license instanceof License ) {
			return $this->failure( $license );
		}

		$stored = $license->get_status( 'edit' );
		$action = (string) $request->get( 'license_action', '' );

		[ $status, $message, $error ] = match ( $action ) {
			'reactivate' => License::STATUS_DEACTIVATED === $license->get_status()
				? [ '', smliser__( 'The license is reactivated.' ), null ]
				: [ null, null, smliser__( 'Only a deactivated license can be reactivated.' ) ],
			'suspend'    => ! in_array( $stored, [ License::STATUS_SUSPENDED, License::STATUS_REVOKED ], true )
				? [ License::STATUS_SUSPENDED, smliser__( 'The license is suspended. It cannot be validated or used to download the app until you unsuspend it.' ), null ]
				: [ null, null, smliser__( 'This license cannot be suspended.' ) ],
			'unsuspend'  => License::STATUS_SUSPENDED === $stored
				? [ '', smliser__( 'The license is unsuspended.' ), null ]
				: [ null, null, smliser__( 'Only a suspended license can be unsuspended.' ) ],
			default      => [ null, null, smliser__( 'Choose what to do with the license.' ) ],
		};

		if ( null === $status ) {
			return $this->failure( new RequestException( 'invalid_license_action', (string) $error, [ 'status' => 409 ] ) );
		}

		$result = $this->licensing->set_status( $license, $status, $this->activity( $request ) );

		if ( $result instanceof Exception ) {
			return $this->failure( $result );
		}

		return $this->success( (string) $message, $this->license_url( $license ) );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * The request's details for the license activity log.
	 *
	 * @param Request $request
	 * @return array{ip_address: string, user_agent: string, duration: float}
	 */
	private function activity( Request $request ) : array {
		return [
			'ip_address' => $request->ip(),
			'user_agent' => (string) $request->userAgent(),
			'duration'   => microtime( true ) - $request->startTime(),
		];
	}

	/**
	 * The license's page.
	 *
	 * @param License $license
	 * @return string
	 */
	private function license_url( License $license ) : string {
		return $this->urlmanager->client_dashboard_url( 'app-licenses', [ 'license' => $license->get_id() ] )->url();
	}

	/**
	 * @param string $message
	 * @param string $redirect
	 * @return Response
	 */
	private function success( string $message, string $redirect ) : Response {
		return Response::json( [ 'success' => true, 'message' => $message, 'redirect' => $redirect ], 200 );
	}

	/**
	 * @param Exception $e With a "status".
	 * @return Response
	 */
	private function failure( Exception $e ) : Response {
		$data   = $e->get_error_data();
		$status = is_array( $data ) ? (int) ( $data['status'] ?? 400 ) : 400;

		return Response::json( [ 'success' => false, 'message' => $e->get_error_message() ], $status >= 400 ? $status : 400 );
	}
}
