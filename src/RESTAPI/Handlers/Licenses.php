<?php
/**
 * The license REST API class.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI
 * @since 0.2.0
 */

namespace SmartLicenseServer\RESTAPI\Handlers;

use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;

/**
 * Handles all the REST API for the license endpoints.
 */
/**
 * The license routes a licensed app calls from the site it runs on.
 *
 * Philosophy: the license credentials (service ID and license key) decide
 * where a license is used; the site secret proves which site is calling; a
 * download token proves that site recently proved both. The server keeps
 * the license within its domain limit; the client keeps its token current.
 *
 *  - Activate: on a new domain, within the license's domain limit, returns
 *    the domain's site secret (once) and a short-lived download token. On a
 *    domain already active, the site secret is required and only a new
 *    short-lived token is returned.
 *  - Reauthenticate: exchanges a valid, unexpired token issued to the
 *    calling domain for a longer-lived one; the old token is consumed. An
 *    expired token is refused: the client activates again.
 *  - Uninstall: frees a domain with the credentials alone, so a site that
 *    lost its secret can be uninstalled and activated again. The domain's
 *    tokens are revoked.
 *  - Deactivate: an active domain, with its secret, deactivates the license
 *    itself on every domain, e.g. when the license is compromised, until
 *    its owner resolves it.
 *
 * Tokens are only issued while the license can be served, so renewing a
 * license is its owner's business, never the client's.
 */
class Licenses {

	/**
	 * Lifetime of the token issued at activation, in seconds (2 days).
	 *
	 * @var int
	 */
	public const ACTIVATION_TOKEN_LIFETIME = 172800;

	/**
	 * Lifetime of the token issued at reauthentication, in seconds (2 weeks).
	 *
	 * @var int
	 */
	public const REAUTH_TOKEN_LIFETIME = 1209600;

	/**
	 * The header a site sends its site secret in, base64-encoded.
	 *
	 * A site gets its secret when the license is activated on it, and sends
	 * it on every later request for that domain. It is not sent as
	 * "Authorization: Bearer", which carries platform credentials only.
	 */
	public const SITE_SECRET_HEADER = 'X-Smliser-Site-Secret';

	/**
	 * Class constructor.
	 *
	 * @param RepositoryAnalytics $analytics Records license activity.
	 */
	public function __construct(
		protected RepositoryAnalytics $analytics
	) {}

	/**
	 * The new license activation route permission callback.
	 * 
	 * @param Request $request The current request object.
	 */
	public function activation_permission_callback( Request $request ) : bool|RequestException {
		$service_id  = $request->get( 'service_id' );
		$license_key = $request->get( 'license_key' );
		$domain      = $request->get( 'domain' );
		$app_type    = (string) $request->getTyped( 'app_type', 'string', '' );
		$app_slug    = (string) $request->getTyped( 'app_slug', 'string', '' );

		$hosted_app = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

		if ( ! $hosted_app ) {
			$response = new RequestException( 'license_error', sprintf( smliser__( 'The %s with this slug "%s" does not exist' ), $app_type, $app_slug ), [ 'status' => 404 ] );
			return $response;
		}

		$license = License::get_license( $service_id, $license_key );

		if ( ! $license ) {
			$response = new RequestException( 'license_error', smliser__( 'The license could not be found. The license key or service ID may be incorrect.' ), [ 'status' => 404 ] );
			return $response;
		}

		if ( ! $license->is_issued() ) {
			$error = new RequestException( 'license_error', smliser__( 'Activation not allowed for unissued licenses.' ), ['status' => 400] );

			return $error;
		}

		$request->set( 'smliser_resource', $license );

		$status_access = $license->can_serve_license( $hosted_app->get_id() );
		$domain_access = $this->restrict_domain_activation( $domain, $request );

		$access_denied = is_smliser_error( $status_access ) || is_smliser_error( $domain_access );

		if ( $access_denied ) {
			$error = is_smliser_error( $status_access ) ? $status_access : $domain_access;

			if ( ! ( $error instanceof RequestException ) ) {
				$error = new RequestException( $error->get_error_code(), $error->get_error_message(), $error->get_error_data() );
			}

			return $error;
		}

		return true;
	}

	/**
	 * Responds to license activation request.
	 * 
	 * @param Request $request The current request object.
	 */
	public function activation_response( Request $request ) {
		$service_id  = $request->get( 'service_id' );
		$license_key = $request->get( 'license_key' );
		$domain      = $request->get( 'domain' );
		/** @var License $license */
		$license = $request->get( 'smliser_resource' );
		$host    = License::activation_host( (string) $domain );
		$secret  = null;

		if ( $license->is_new_domain( $domain ) ) {
			$domain_secret      = 'sc_' . bin2hex( random_bytes( 32 ) );
			$domain_secret_hash = DownloadToken::hash_site_secret( $domain_secret );
			$activated          = $license->activate_domain( $domain, $domain_secret_hash );

			// The secret is only handed out once the activation is stored.
			if ( $activated instanceof Exception ) {
				return new Response( (int) ( $activated->get_error_data()['status'] ?? 409 ), [], [
					'message' => $activated->get_error_message(),
					'data'    => [ 'code' => $activated->get_error_code() ],
				] );
			}

			$secret = base64_encode( $domain_secret );
		}

		$token = smliser_generate_item_token( $license, self::ACTIVATION_TOKEN_LIFETIME, $host );

		if ( false === $token ) {
			return Response::error( new RequestException( 'download_token_error', smliser__( 'The license is active on this domain, but a download token could not be issued. Activate again to get one.' ), [ 'status' => 500 ] ), 500 );
		}

		$response_data = [
			'message' => sprintf( smliser__( 'License has been activated for your domain "%s"' ), $domain ),
			'data'    => [
				'license_key'    => $license_key,
				'service_id'     => $service_id,
				'download_token' => $token,
				'token_expiry'   => gmdate( 'Y-m-d', DownloadToken::expiry_for( $license, self::ACTIVATION_TOKEN_LIFETIME ) ),
				'license_expiry' => $license->get_end_date()?->format( 'Y-m-d' ) ?? \gmdate( 'Y-m-d', \strtotime( '+15 years' ) )
			]
		];

		if ( null !== $secret ) {
			$response_data['data']['site_secret'] = $secret;
		}

		$response = new Response( 200, [], $response_data );

		$license_data = [
			'license_id' => $license->get_id(),
			'ip_address' => $request->ip(),
			'event_type' => 'activation',
			'website'    => $domain,
			'comment'    => 'License activated',
			'duration'   => microtime( true ) - $request->startTime()
		];

		$this->analytics->log_license_activity( $license_data );
		
		return $response;
	}
	
	/**
	 * License deactivation permission.
	 * The license key and service ID are required to deactivate a license.
	 * 
	 * @param Request $request The REST API request object
	 * @return RequestException|true
	 */
	public function deactivation_permission( Request $request ) : bool|RequestException {
		$license_key = $request->get( 'license_key' );
		$service_id  = $request->get( 'service_id' );
		$license     = License::get_license( $service_id, $license_key );
		
		if ( ! $license ) {
			$response = new RequestException(
				'license_error',
				smliser__( 'The license could not be found. The license key or service ID may be incorrect.' ),
				[ 'status' => 404 ]
			);

			return $response;
		}

		if ( $license->is_deactivated() ) {
			return new RequestException( 'license_deactivated', smliser__( 'The license is already deactivated. Its owner must resolve it.' ), [ 'status' => 409 ] );
		}

		$request->set( 'smliser_resource', $license );
		
		return $this->verify_domain( $request );
	}

	/**
	 * License deactivation route handler.
	 * 
	 * @param Request $request
	 * @return Response
	 */
	public function deactivation_response( Request $request ) : Response {
		$domain = smliser_url_origin( $request->get( 'domain' ) );
		/** @var License $license */
		$license         = $request->get( 'smliser_resource' );
		$original_status = $license->get_status();
		
		$license->set_status( License::STATUS_DEACTIVATED );
		$log_data = [
			'license_id' => $license->get_id(),
			'ip_address' => $request->ip(),
			'event_type' => 'deactivation',
			'website'    => $domain,
			'comment'    => '',
			'duration'   => microtime( true ) - $request->startTime()
		];

		if ( $license->save() ) {
			$response_data = [
				'message' => smliser__( 'License has been deactivated' ),
				'data'    => [
					'license_status' => $license->get_status(),
					'date'           => gmdate( 'Y-m-d' )
				],
			];

			$status_code = 200;

		} else {
			$response_data = [
				'message' => smliser__( 'Unable to process this request at the moment.' ),
				'data'    => [
					'license_status' => $original_status,
					'date'           => gmdate( 'Y-m-d' )
				],
			];

			$status_code = 503;

		}

		$log_data['comment'] = $response_data['message'];
		
		$this->analytics->log_license_activity( $log_data );
		
		$response = new Response( $status_code, [], $response_data );
		
		return $response;
	}

	/**
	 * License uninstallation permission callback.
	 *
	 * Needs the license credentials and an active domain, but not the
	 * domain's site secret: whoever holds the credentials decides where the
	 * license is used, and a site that lost its secret must be able to free
	 * its domain to activate again. Abuse is limited by LicenseMonitor.
	 *
	 * @param Request $request The REST Request Object.
	 * @return RequestException|true
	 */
	public function uninstallation_permission( Request $request ) : bool|RequestException {
		$license = License::get_license( $request->get( 'service_id' ), $request->get( 'license_key' ) );

		if ( ! $license ) {
			return new RequestException( 'license_error', smliser__( 'The license could not be found. The license key or service ID may be incorrect.' ), [ 'status' => 404 ] );
		}

		$domain = (string) $request->get( 'domain' );

		if ( $license->is_new_domain( $domain ) ) {
			return new RequestException( 'domain_not_active', sprintf( smliser__( 'The license is not activated on %s.' ), $domain ), [ 'status' => 404 ] );
		}

		$request->set( 'smliser_resource', $license );

		return true;
	}

	/**
	 * Uninstall or remove th given domain from the list of activated domains for this license.
	 * 
	 * @param Request $request The REST Request Object
	 */
	public function uninstallation_response( Request $request ) : Response {
		$domain = $request->get( 'domain' );
		/** @var License $license */
		$license       = $request->get( 'smliser_resource' );

		$log_data = [
			'license_id' => $license->get_id(),
			'event_type' => 'uninstallation',
			'ip_address' => $request->ip(),
			'website'    => $domain,
			'comment'    => '',
			'duration'   => microtime( true ) - $request->startTime()
		];

		$removed       = $license->remove_activated_domain( $domain );

		if ( $removed ) {
			// A freed domain keeps no download access.
			DownloadToken::revoke_for_activation( $license, License::activation_host( $domain ) );
		}

		$response_data = [
			'data' => [
				'license status' => $license->get_status(),
				'activated on'   => $license->get_total_active_domains()
			]
		];

		if ( $removed ) {
			$response_data['message'] = sprintf( smliser__( 'The license uninstalled for the domain "%s" successfully.' ), $domain );
			$status_code              = 200;
		} else {
			$response_data['message'] = sprintf( smliser__( 'Unable to unstall %s please try again later.' ), $domain );
			$status_code              = 503;
		}

		$log_data['comment'] = $response_data['message'];

		$response = new Response( $status_code, [], $response_data );
		$this->analytics->log_license_activity( $log_data );

		return $response;
	}

	/**
	 * License validity test permission callback
	 * 
	 * @param Request $request The REST API request object.
	 * @return RequestException|true
	 */
	public function validity_test_permission( Request $request ) {
		$service_id  = $request->get( 'service_id' );
		$license_key = $request->get( 'license_key' );
		$app_type    = (string) $request->getTyped( 'app_type', 'string', '' );
		$app_slug    = (string) $request->getTyped( 'app_slug', 'string', '' );
		$hosted_app  = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

		if ( ! $hosted_app ) {
			$response = new RequestException( 'license_error', sprintf( smliser__( 'The %s with this slug "%s" does not exist' ), $app_type, $app_slug ), [ 'status' => 404 ] );
			return $response;
		}

		$license = License::get_license( $service_id, $license_key );
		
		if ( ! $license ) {
			$response = new RequestException( 'license_error', smliser__( 'The license could not be found. The license key or service ID may be incorrect.' ), [ 'status' => 404 ] );
			return $response;
		}

		$request->set( 'smliser_resource', $license );
		$request->set( 'hosted_app', $hosted_app );

		$domain_access = $this->verify_domain( $request );
		if ( is_smliser_error( $domain_access ) ) {
			return $domain_access;
		}

		$download_token = $request->get_header( 'x-download-token' );

		if ( ! $download_token ) {
			return new RequestException( 'missing_download_token', smliser__( 'Please provide the X-Download-Token header value' ), [ 'status' => 400 ] );
		}

		return true;
	}

	/**
	 * Perform data validation test.
	 * This operation will test for:
	 * - License document validity.
	 * - Domain validity.
	 * - Download token validity.
	 */
	public function validity_test_response( Request $request ) {
		/** @var License $license */
		$license = $request->get( 'smliser_resource' );

		/** @var AbstractHostedApp $hosted_app */
		$hosted_app = $request->get( 'hosted_app' );

		$download_token = $request->get_header( 'x-download-token' );
		$token_validity = smliser_verify_item_token( $download_token, $hosted_app );
		
		$response_data = [
			'message' => smliser__( 'License validity test result is ready.' ),
			'data'    => [
				'license' => [
					'status'      => $license->get_status(),
					'expiry_date' => $license->get_end_date()?->format( 'Y-m-d' ) ?? \gmdate( 'Y-m-d', \strtotime( '+15 years' ) )
				],
				'token_validity' => \is_smliser_error( $token_validity ) ? 'Invalid' : 'Valid',
				'token_error'    => \is_smliser_error( $token_validity ) ? $token_validity->get_error_message() : null
			]
		];

		$log_data = [
			'license_id' => $license->get_id(),
			'event_type' => 'verification',
			'ip_address' => $request->ip(),
			'website'    => $request->get( 'domain' ),
			'comment'    => \sprintf( 'Message: %s | Token validity: %s', $response_data['message'], $response_data['data']['token_validity'] ),
			'duration'   => microtime( true ) - $request->startTime()
		];

		$response = new Response( 200, [], $response_data );
		$this->analytics->log_license_activity( $log_data );

		return $response;

	}

	/**
	 * Download token reauthentication permission callback.
	 * 
	 * @param Request $request
	 * @return RequestException|true
	 */
	public function download_reauth_permission( Request $request ) {
		$service_id  = $request->get( 'service_id' );
		$license_key = $request->get( 'license_key' );
		$license     = License::get_license( $service_id, $license_key );

		$app_type   = (string) $request->getTyped( 'app_type', 'string', '' );
		$app_slug   = (string) $request->getTyped( 'app_slug', 'string', '' );
		$hosted_app = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

		if ( ! $hosted_app ) {
			$response = new RequestException( 'license_error', sprintf( smliser__( 'The %s with this slug "%s" does not exist' ), $app_type, $app_slug ), [ 'status' => 404 ] );
			return $response;
		}

		if ( ! $license ) {
			$response = new RequestException( 'license_error', smliser__( 'The license could not be found. The license key or service ID may be incorrect.' ), [ 'status' => 404 ] );
			return $response;
		}

		$request->set( 'smliser_resource', $license );
		$status_access = $license->can_serve_license( $hosted_app->get_id() );

		if ( $status_access instanceof Exception ) {
			return $status_access;
		}

		$domain_access = $this->verify_domain( $request );

		if ( $domain_access instanceof Exception ) {
			return $domain_access;
		}

		$record = smliser_verify_item_token( (string) $request->get( 'download_token' ), $hosted_app );

		if ( $record instanceof Exception ) {
			return 'download_token_expired' === $record->get_error_code()
				? new RequestException( 'download_token_expired', smliser__( 'The download token has expired. Activate the license on this domain again to get a new one.' ), [ 'status' => 401 ] )
				: new RequestException( 'download_token_invalid', sprintf( smliser__( '%s Activate the license on this domain again to get a new one.' ), $record->get_error_message() ), [ 'status' => 403 ] );
		}

		$host = License::activation_host( (string) $request->get( 'domain' ) );

		if ( ! hash_equals( $record->get_license_key(), $license->get_license_key() ) || ! $record->belongs_to_host( $host ) ) {
			return new RequestException( 'download_token_invalid', smliser__( 'The download token was not issued to this license on this domain.' ), [ 'status' => 403 ] );
		}

		$request->set( 'download_token_record', $record );

		return true;
	}

	/**
	 * Exchange a valid download token for a longer-lived one.
	 *
	 * The token presented was verified by download_reauth_permission(); it
	 * is consumed once the new one is issued, so each token is exchanged at
	 * most once.
	 *
	 * @param Request $request.
	 * @return Response
	 */
	public function app_download_reauth( Request $request ) {
		/** @var License $license */
		$license = $request->get( 'smliser_resource' );
		/** @var DownloadToken $record */
		$record  = $request->get( 'download_token_record' );
		$token   = smliser_generate_item_token( $license, self::REAUTH_TOKEN_LIFETIME, $record->get_activation_host() );

		if ( false === $token ) {
			return Response::error( new RequestException( 'download_token_error', smliser__( 'A new download token could not be issued. Your current token is still valid; try again later.' ), [ 'status' => 500 ] ), 500 );
		}

		$record->delete();

		$response_data = [
			'message' => smliser__( 'Download token has been refreshed.' ),
			'data'    => [
				'download_token' => $token,
				'token_expiry'   => gmdate( 'Y-m-d', DownloadToken::expiry_for( $license, self::REAUTH_TOKEN_LIFETIME ) ),
			]
		];

		return new Response( 200, [], $response_data );
	}

	/**
	 * Whether or not to restrict a domain from activating a license
	 * 
	 * @param string $domain The domian name to check
	 * @return false|RequestException Returns false if the domain is allowed, or a RequestException object if the domain is restricted.
	 */
	public function restrict_domain_activation( string $domain, Request $request ) {
		/** @var License $license */
		$license = $request->get( 'smliser_resource' );;

		if ( $license->is_new_domain( $domain ) ) {
			if ( $license->has_reached_max_allowed_domains() ) {
				return new RequestException( 'license_error', smliser__( 'Maximum allowed domains has been reached.' ), [ 'status' => 409 ] );
				
			}
			
			return false;
		}

		$result = $this->verify_domain( $request );
		return is_smliser_error( $result ) ? $result : false;
	}

	/**
	 * Verify that a domain can access the license REST API endpoint.
	 *
	 * The domain must be activated on the license, and the request must
	 * carry its site secret, base64-encoded, in the SITE_SECRET_HEADER header.
	 * 
	 * @param Request $request The request object.
	 * @return RequestException|true Error object on failure, true otherwise.
	 */
	public function verify_domain( Request $request ) {
		/** @var License $license */
		$license = $request->get( 'smliser_resource' );
		$domain  = $request->get( 'domain' );

		$domain_data = $license->get_active_domain( $domain );

		if ( ! isset( $domain_data['secret'] ) ) {
			return new RequestException( 'site_token_missing', sprintf( smliser__( 'Invalid domain, please activate the domain %s to access this route' ), $domain ), [ 'status' => 401 ] );
		}

		$known_secret  = $domain_data['secret'];
		$client_secret = trim( (string) $request->get_header( self::SITE_SECRET_HEADER, '' ) );

		if ( '' === $client_secret ) {
			return new RequestException( 'site_secret_missing', sprintf( smliser__( 'Send the site secret for this domain in the %s header.' ), self::SITE_SECRET_HEADER ), [ 'status' => 400 ] );
		}

		$client_secret = base64_decode( $client_secret );

		if ( ! $client_secret ) {
			return new RequestException( 'invalid_token_format', smliser__( 'Token could not be decoded properly, please be sure that the token is base64 encoded or not double encoding it.' ), [ 'status' => 400 ] );
		}

		$client_secret_hash = DownloadToken::hash_site_secret( $client_secret );

		if ( ! hash_equals( $known_secret, $client_secret_hash ) ) {
			return new RequestException( 'site_secret_invalid', smliser__( 'The site secret is not valid for this domain.' ), [ 'status' => 401 ] );
		}

		return true;
	}
}
