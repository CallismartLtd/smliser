<?php
/**
 * Client license actions class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\ClientDashboard\LicenseHolderPolicy;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;

/**
 * The client dashboard's license actions (<client dashboard prefix>/json/licenses/…),
 * for the holder of a license (LicenseHolderPolicy).
 *
 *   POST licenses/deactivate  "license_id", "confirm" = "1": report the license as
 *                             compromised. It is deactivated, so it is refused on
 *                             every site, and the event is logged as the REST API's
 *                             license deactivation is. No site is freed.
 *
 * There is deliberately no action that frees a site (remove, uninstall,
 * reset or transfer an activation) or reactivates a license: those belong
 * to the licensed app's owner and the system administrators.
 *
 * Answers { success, message, redirect? } for page-forms.js. A license that
 * is not the user's is "not found" (404), whether it exists or not.
 */
final class LicenseActions {

	/**
	 * @param Guard               $guard      The signed-in principal.
	 * @param RepositoryAnalytics $analytics  Records license activity.
	 * @param URLManager          $urlmanager Redirects.
	 */
	public function __construct(
		private Guard $guard,
		private RepositoryAnalytics $analytics,
		private URLManager $urlmanager
	) {}

	/**
	 * Deactivate the user's license (report it as compromised).
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function deactivate( Request $request ) : Response {
		$user    = $this->current_user();
		$license = LicenseHolderPolicy::find( (int) $request->get( 'license_id', 0 ), $user );

		if ( null === $license ) {
			return $this->failure( smliser__( 'This license does not exist.' ), 404 );
		}

		if ( '1' !== (string) $request->get( 'confirm', '' ) ) {
			return $this->failure( smliser__( 'Confirm that you want to deactivate this license on every site.' ), 400 );
		}

		if ( $license->is_deactivated() ) {
			return $this->failure( smliser__( 'This license is already deactivated. Only the app\'s owner or an administrator can reactivate it.' ), 409 );
		}

		if ( ! LicenseHolderPolicy::can_deactivate( $license, $user ) ) {
			return $this->failure( smliser__( 'This license is already refused on every site, so there is nothing to deactivate. Contact the app\'s owner about it.' ), 409 );
		}

		$license->set_status( License::STATUS_DEACTIVATED );

		if ( ! $license->save() ) {
			return $this->failure( smliser__( 'Unable to process this request at the moment.' ), 503 );
		}

		// Logged as the REST API's license deactivation is (Licenses::deactivation_response()).
		$this->analytics->log_license_activity( [
			'license_id' => $license->get_id(),
			'ip_address' => $request->ip(),
			'event_type' => 'deactivation',
			'website'    => 'N/A',
			'comment'    => sprintf( 'License deactivated by its holder (user #%d) from the client dashboard: reported as compromised.', (int) $user?->get_id() ),
			'duration'   => microtime( true ) - $request->startTime(),
		] );

		return $this->success(
			smliser__( 'The license is deactivated. It is refused on every site until the app\'s owner or an administrator reactivates it.' ),
			$this->urlmanager->client_dashboard_url( 'licenses', [ 'license' => $license->get_id() ] )->url()
		);
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * The signed-in user, or null for anyone else.
	 *
	 * @return User|null
	 */
	private function current_user() : ?User {
		$actor = $this->guard->get_principal()?->get_actor();

		return $actor instanceof User ? $actor : null;
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
	 * @param string $message
	 * @param int    $status
	 * @return Response
	 */
	private function failure( string $message, int $status ) : Response {
		return Response::json( [ 'success' => false, 'message' => $message ], $status );
	}
}
