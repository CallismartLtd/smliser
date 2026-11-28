<?php
/**
 * Client app actions class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\HostedApps\OwnerAppScope;

/**
 * The client dashboard's app actions (<client dashboard prefix>/json/apps/…).
 *
 * The apps are the current owner's: the user, or the organization they act
 * for (OwnerAppScope). An app of any other owner is not found here, even for
 * a system administrator, who manages other owners' apps in the admin
 * dashboard.
 *
 *   POST apps/save              Create ("app_type", "app_name", "app_author", "app_zip_file")
 *                               or update ("app_type", "app_id", details, an optional new
 *                               "app_zip_file" and "app_json_file"). A new app always
 *                               belongs to the current owner; "app_owner_id" is ignored,
 *                               and so are "app_slug" and "app_status".
 *   POST apps/status            "app_type", "app_slug", "app_status".
 *   POST apps/delete            "app_type", "app_slug": an app in the trash, for good.
 *   POST apps/assets/upload     "app_type", "app_slug", "asset_type", "asset_file[]".
 *   POST apps/assets/delete     "app_type", "app_slug", "asset_name".
 *   POST apps/artifacts/upload  "app_type", "app_slug", "artifact_file", optional
 *                               "canonical_filename" (the file's own name by default).
 *   PUT  apps/artifacts/update  "app_type", "app_slug", "artifact_filename" (the current
 *                               name), "canonical_filename", optional "artifact_file":
 *                               renames, or replaces with the file.
 *   POST apps/artifacts/delete  "app_type", "app_slug", "artifact_filename".
 *
 * Each action is checked twice: here, against the owner's apps and the
 * capabilities of OwnerAppScope::ACTIONS, before anything is touched; then
 * by HostingController, whose rules (capabilities, ownership, suspension,
 * file validation) are enforced once for the admin dashboard, the REST API,
 * the CLI and here. This class answers { success, message, redirect? } for
 * page-forms.js.
 */
final class AppActions {

	/**
	 * Request fields only an administrator sets; never taken from the client dashboard.
	 */
	private const ADMIN_FIELDS = [ 'app_owner_id', 'app_slug', 'app_status', 'new_slug' ];

	/**
	 * @param OwnerAppScope     $scope      The current owner's apps.
	 * @param HostingController $hosting    The hosting rules.
	 * @param URLManager        $urlmanager Redirects.
	 */
	public function __construct(
		private OwnerAppScope $scope,
		private HostingController $hosting,
		private URLManager $urlmanager
	) {}

	/**
	 * Create or update an app.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function save( Request $request ) : Response {
		$is_new = 0 === (int) $request->get( 'app_id', 0 );
		$type   = (string) $request->get( 'app_type', '' );

		foreach ( self::ADMIN_FIELDS as $field ) {
			$request->remove( $field );
		}

		if ( $is_new ) {
			// A new app goes to the owner acting; without one there is no one to own it.
			if ( null === $this->scope->owner_id() ) {
				return $this->failure( new RequestException( 'unauthorized_scope', smliser__( 'Switch to an account or organization that can host apps first.' ), [ 'status' => 403 ] ) );
			}

			if ( ! $this->scope->can( 'create' ) ) {
				return $this->forbidden();
			}
		} else {
			$app = $this->scope->find( $type, (int) $request->get( 'app_id', 0 ) );

			if ( null === $app ) {
				return $this->not_found();
			}

			if ( ! $this->scope->can( 'update' ) ) {
				return $this->forbidden();
			}

			$request->set( 'app_type', $app->get_type() );
			$request->set( 'app_id', $app->get_id() );
		}

		$response = $this->hosting->save_app( $request );
		$app      = $request->get( 'smliser_resource', null, false );

		return $this->relay( $response, $app instanceof AbstractHostedApp ? $this->app_url( $app ) : null );
	}

	/**
	 * Change an app's status.
	 *
	 * Into or out of the trash needs the trash capabilities too.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function status( Request $request ) : Response {
		$app = $this->owned_app( $request );

		if ( null === $app ) {
			return $this->not_found();
		}

		$trash  = AbstractHostedApp::STATUS_TRASH;
		$action = $trash === (string) $request->get( 'app_status', '' ) || $trash === $app->get_status() ? 'trash' : 'change_status';

		if ( ! $this->scope->can( $action ) ) {
			return $this->forbidden();
		}

		return $this->relay( $this->hosting->change_app_status( $request, $app ), $this->app_url( $app ) );
	}

	/**
	 * Delete an app in the trash for good.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function delete( Request $request ) : Response {
		$app = $this->owned_app( $request );

		if ( null === $app ) {
			return $this->not_found();
		}

		if ( ! $this->scope->can( 'delete' ) ) {
			return $this->forbidden();
		}

		return $this->relay(
			$this->hosting->delete_app( $request ),
			$this->urlmanager->client_dashboard_url( 'apps' )->add_query_param( 'status', AbstractHostedApp::STATUS_TRASH )->url()
		);
	}

	/**
	 * Upload assets.
	 *
	 * The controller answers with what was uploaded and what failed, file
	 * by file; any failure is the answer's message.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function upload_assets( Request $request ) : Response {
		$app = $this->owned_app( $request );

		if ( null === $app ) {
			return $this->not_found();
		}

		if ( ! $this->scope->can( 'upload_assets' ) ) {
			return $this->forbidden();
		}

		$response = $this->hosting->app_asset_upload( $request );

		if ( $response->has_errors() ) {
			return $this->relay( $response, null );
		}

		$body   = $response->get_body();
		$result = is_array( $body ) ? (array) ( $body['result'] ?? [] ) : [];
		$failed = [];

		foreach ( (array) ( $result['failed'] ?? [] ) as $file => $reasons ) {
			/* translators: 1: a file name, or "Upload" for the upload as a whole, 2: why it failed. */
			$failed[] = sprintf( smliser__( '%1$s: %2$s' ), 'system' === $file ? smliser__( 'Upload' ) : $file, implode( ' ', array_map( 'strval', (array) $reasons ) ) );
		}

		if ( [] !== $failed ) {
			return $this->failure( new RequestException( 'asset_upload_failed', implode( ' ', $failed ), [ 'status' => 400 ] ) );
		}

		$count = count( (array) ( $result['uploaded'] ?? [] ) );

		/* translators: %d: number of images uploaded. */
		return $this->success( sprintf( smliser_n( '%d image is uploaded.', '%d images are uploaded.', $count ), $count ), $this->app_url( $app ) );
	}

	/**
	 * Delete an asset.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function delete_asset( Request $request ) : Response {
		$app = $this->owned_app( $request );

		if ( null === $app ) {
			return $this->not_found();
		}

		if ( ! $this->scope->can( 'delete_assets' ) ) {
			return $this->forbidden();
		}

		return $this->relay( $this->hosting->app_asset_delete( $request ), $this->app_url( $app ) );
	}

	/**
	 * Add an artifact: a file distributed beside the main package.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function upload_artifact( Request $request ) : Response {
		$app = $this->owned_app( $request );

		if ( null === $app ) {
			return $this->not_found();
		}

		if ( ! $this->scope->can( 'upload_artifacts' ) ) {
			return $this->forbidden();
		}

		// Without a name, the artifact keeps the uploaded file's own.
		if ( $request->isEmpty( 'canonical_filename' ) ) {
			$request->set( 'canonical_filename', (string) $request->get_file( 'artifact_file' )?->get_client_name() );
		}

		$request->remove( 'artifact_filename' );

		return $this->relay_artifact( $this->hosting->app_artifact_upload( $request ), $app, smliser__( 'The artifact is uploaded.' ) );
	}

	/**
	 * Rename an artifact, or replace it with a new file.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function update_artifact( Request $request ) : Response {
		$app = $this->owned_app( $request );

		if ( null === $app ) {
			return $this->not_found();
		}

		if ( ! $this->scope->can( 'edit_artifacts' ) ) {
			return $this->forbidden();
		}

		// An empty name keeps the current one.
		if ( $request->isEmpty( 'canonical_filename' ) ) {
			$request->set( 'canonical_filename', (string) $request->get( 'artifact_filename', '' ) );
		}

		return $this->relay_artifact( $this->hosting->app_artifact_upload( $request ), $app, smliser__( 'The artifact is saved.' ) );
	}

	/**
	 * Delete an artifact.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function delete_artifact( Request $request ) : Response {
		$app = $this->owned_app( $request );

		if ( null === $app ) {
			return $this->not_found();
		}

		if ( ! $this->scope->can( 'delete_artifacts' ) ) {
			return $this->forbidden();
		}

		return $this->relay_artifact( $this->hosting->app_artifact_delete( $request ), $app, smliser__( 'The artifact is deleted.' ) );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * An app of the current owner, by the request's "app_type" and "app_slug".
	 *
	 * The request is set to the app's own type and slug, so the controller
	 * works on the app checked here.
	 *
	 * @param Request $request
	 * @return AbstractHostedApp|null Null for an app of any other owner.
	 */
	private function owned_app( Request $request ) : ?AbstractHostedApp {
		$app = $this->scope->find_by_slug( (string) $request->get( 'app_type', '' ), (string) $request->get( 'app_slug', '' ) );

		if ( null !== $app ) {
			$request->set( 'app_type', $app->get_type() );
			$request->set( 'app_slug', $app->get_slug() );
		}

		return $app;
	}

	/**
	 * The app's page.
	 *
	 * @param AbstractHostedApp $app
	 * @return string
	 */
	private function app_url( AbstractHostedApp $app ) : string {
		return $this->urlmanager->client_dashboard_url( 'apps' )
			->add_query_params( [ 'type' => $app->get_type(), 'app' => $app->get_id() ] )
			->url();
	}

	/**
	 * Turn a HostingController answer into page-forms.js's.
	 *
	 * @param Response    $response The controller's answer: { success, data: { message } } or an error.
	 * @param string|null $redirect Where to go after success; the controller's admin URL is never used.
	 * @return Response
	 */
	private function relay( Response $response, ?string $redirect ) : Response {
		if ( $response->has_errors() ) {
			$data = $response->get_error_data();
			$data = is_array( $data ) ? $data : [];

			return $this->failure( new RequestException( (string) $response->get_error_code(), (string) $response->get_error_message(), [ 'status' => (int) ( $data['status'] ?? $response->get_status_code() ) ] ) );
		}

		$body    = $response->get_body();
		$message = is_array( $body ) ? (string) ( $body['data']['message'] ?? '' ) : '';

		return $this->success( '' !== $message ? $message : smliser__( 'Done.' ), $redirect );
	}

	/**
	 * Turn an artifact answer into page-forms.js's, back on the app's page.
	 *
	 * The controller's artifact answers carry data, not a message.
	 *
	 * @param Response          $response
	 * @param AbstractHostedApp $app
	 * @param string            $message The message on success.
	 * @return Response
	 */
	private function relay_artifact( Response $response, AbstractHostedApp $app, string $message ) : Response {
		if ( $response->has_errors() ) {
			return $this->relay( $response, null );
		}

		return $this->success( $message, $this->app_url( $app ) );
	}

	/**
	 * The answer for an app outside the owner's apps: it does not exist.
	 *
	 * @return Response
	 */
	private function not_found() : Response {
		return $this->failure( new RequestException( 'resource_not_found', smliser__( 'This app does not exist.' ), [ 'status' => 404 ] ) );
	}

	/**
	 * The answer for an action the user's role does not allow.
	 *
	 * @return Response
	 */
	private function forbidden() : Response {
		return $this->failure( new RequestException( 'unauthorized_scope', smliser__( 'Your role here does not allow this.' ), [ 'status' => 403 ] ) );
	}

	/**
	 * @param string      $message
	 * @param string|null $redirect
	 * @return Response
	 */
	private function success( string $message, ?string $redirect ) : Response {
		return Response::json( array_filter( [ 'success' => true, 'message' => $message, 'redirect' => $redirect ], static fn ( $value ) : bool => null !== $value ), 200 );
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
