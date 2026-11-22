<?php
/**
 * Specialized Exception for rejecting HTTP requests due to client or access issues.
 *
 * This class abstracts common HTTP status codes for validation, authorization,
 * and rate limiting failures. It is intended to be used by controller layers
 * to fail fast when a request is invalid.
 *
 * @package SmartLicenseServer\Exception
 * @author Callistus
 */

namespace SmartLicenseServer\Exceptions;

use SmartLicenseServer\Exceptions\Exception;

/**
 * Class RequestException
 */
class RequestException extends Exception {

	/**
	 * Known errors: HTTP status, title and message, in the current language.
	 *
	 * @return array<string, array{status: int, title: string, message: string}>
	 */
	protected static function error_map() : array {
		return [

			// 400 Bad Request Errors.
			'invalid_input' => [
				'status'  => 400,
				'title'   => smliser__( 'Invalid Request' ),
				'message' => smliser__( 'One or more required parameters are missing or invalid.' )
			],
			'malformed_json' => [
				'status'  => 400,
				'title'   => smliser__( 'Invalid Syntax' ),
				'message' => smliser__( 'The request body could not be parsed as valid JSON.' )
			],
			'missing_data' => [
				'status'  => 400,
				'title'   => smliser__( 'Missing Data' ),
				'message' => smliser__( 'Required request data (body, header, or query parameter) is missing.' )
			],
			'required_param' => [
				'status'  => 400,
				'title'   => smliser__( 'Invalid Parameter' ),
				'message' => smliser__( 'The please provide the missing request parameter.' )
			],
			'required_file' => [
				'status'  => 400,
				'title'   => smliser__( 'Missing File' ),
				'message' => smliser__( 'The please provide the required file.' )
			],
			'invalid_parameter_type' => [
				'status'  => 400,
				'title'   => smliser__( 'Type Mismatch' ),
				'message' => smliser__( 'A parameter has an unexpected type or format.' )
			],

			// 401 Unauthorized Errors.
			'missing_auth' => [
				'status'  => 401,
				'title'   => smliser__( 'Unauthorized' ),
				'message' => smliser__( 'Authentication is required for this request but was not provided.' )
			],
			'invalid_credentials' => [
				'status'  => 401,
				'title'   => smliser__( 'Invalid Credentials' ),
				'message' => smliser__( 'The provided API key or token is incorrect or expired.' )
			],
			'token_expired' => [
				'status'  => 401,
				'title'   => smliser__( 'Session Expired' ),
				'message' => smliser__( 'Your session or authentication token has expired. Please log in again.' )
			],
			'invalid_signature' => [
				'status'  => 401,
				'title'   => smliser__( 'Invalid Signature' ),
				'message' => smliser__( 'The request signature could not be verified.' )
			],
			'incorrect_password' => [
				'status'  => 401,
				'title'   => smliser__( 'Incorrect Password' ),
				'message' => smliser__( 'The password you entered is incorrect.' )
			],

			// 403 Forbidden Errors.
			'unauthorized_scope' => [
				'status'  => 403,
				'title'   => smliser__( 'Insufficient Scope' ),
				'message' => smliser__( 'The provided credentials do not grant access to this resource.' )
			],
			'unuathorized_app_access' => [
				'status'  => 403,
				'title'   => smliser__( 'Unathorized App Access' ),
				'message' => smliser__( 'Sorry, you do not have the correct app ownership permission.' )
			],
			'permission_denied' => [
				'status'  => 403,
				'title'   => smliser__( 'Forbidden' ),
				'message' => smliser__( 'The authenticated user does not have permission to perform this action.' )
			],
			'ip_blocked' => [
				'status'  => 403,
				'title'   => smliser__( 'Access Denied' ),
				'message' => smliser__( 'Access from your IP address has been blocked for security reasons.' )
			],
			'csrf_violation' => [
				'status'  => 403,
				'title'   => smliser__( 'CSRF Verification Failed' ),
				'message' => smliser__( 'Security token verification failed for this request.' )
			],
			'license_suspended' => [
				'status'  => 403,
				'title'   => smliser__( 'License Suspended' ),
				'message' => smliser__( 'The license or subscription for this request has been suspended.' )
			],
			'access_restricted' => [
				'status'  => 403,
				'title'   => smliser__( 'Restricted Access' ),
				'message' => smliser__( 'This operation is restricted under current system settings.' )
			],

			// 404 Not Found Errors.
			'endpoint_not_found' => [
				'status'  => 404,
				'title'   => smliser__( 'Not Found' ),
				'message' => smliser__( 'The requested API endpoint does not exist.' )
			],
			'resource_not_found' => [
				'status'  => 404,
				'title'   => smliser__( 'Resource Not Found' ),
				'message' => smliser__( 'The requested resource could not be located.' )
			],
			'app_not_found' => [
				'status'  => 404,
				'title'   => smliser__( 'Application Not Found' ),
				'message' => smliser__( 'The requested application could not be located.' )
			],
			'release_not_found' => [
				'status'  => 404,
				'title'   => smliser__( 'Release Not Found' ),
				'message' => smliser__( 'The requested application has nothing to download.' )
			],
			'resource_owner_not_found' => [
				'status'  => 404,
				'title'   => smliser__( 'Resource Owner Not Found' ),
				'message' => smliser__( 'The resource owner does not exist.' )
			],

			// 405 Method Not Allowed.
			'method_not_allowed' => [
				'status'  => 405,
				'title'   => smliser__( 'Method Not Allowed' ),
				'message' => smliser__( 'The request method is not supported for this resource.' )
			],

			// 408 Request Timeout
			'request_timeout' => [
				'status'  => 408,
				'title'   => smliser__( 'Request Timeout' ),
				'message' => smliser__( 'The server timed out waiting for the request to complete.' )
			],

			// 409 Conflict
			'resource_conflict' => [
				'status'  => 409,
				'title'   => smliser__( 'Conflict' ),
				'message' => smliser__( 'The request could not be completed due to a resource conflict.' )
			],
			'duplicate_entry' => [
				'status'  => 409,
				'title'   => smliser__( 'Duplicate Entry' ),
				'message' => smliser__( 'A resource with the same unique identifier already exists.' )
			],
			'email_exists' => [
				'status'  => 409,
				'title'   => smliser__( 'Email Conflict' ),
				'message' => smliser__( 'The provided email is not available.' )
			],
			'app_slug_exists' => [
				'status'  => 409,
				'title'   => smliser__( 'App Slug Conflict' ),
				'message' => smliser__( 'The provided slug for this application is not available.' )
			],

			// 412 Precondition Failed
			'precondition_failed' => [
				'status'  => 412,
				'title'   => smliser__( 'Precondition Failed' ),
				'message' => smliser__( 'A required precondition for this request was not met.' )
			],

			// 413 Payload Too Large
			'payload_too_large' => [
				'status'  => 413,
				'title'   => smliser__( 'Payload Too Large' ),
				'message' => smliser__( 'The request payload exceeded the maximum required size.' )
			],

			// 415 Unsupported Media Type
			'unsupported_media_type' => [
				'status'  => 415,
				'title'   => smliser__( 'Unsupported Media Type' ),
				'message' => smliser__( 'The request content type is not supported by this endpoint.' )
			],
			'requires_multipart_form_data' => [
				'status'  => 415,
				'title'   => smliser__( 'Unsupported Content Type' ),
				'message' => smliser__( 'Content-Type header value must be multipart/form-data.' )
			],

			// 422 Unprocessable Entity
			'validation_failed' => [
				'status'  => 422,
				'title'   => smliser__( 'Validation Failed' ),
				'message' => smliser__( 'Some input fields did not pass validation checks.' )
			],

			// 429 Rate Limiting
			'rate_limited' => [
				'status'  => 429,
				'title'   => smliser__( 'Rate Limit Exceeded' ),
				'message' => smliser__( 'You have exceeded the maximum allowed requests in this time window.' )
			],
			'quota_exceeded' => [
				'status'  => 429,
				'title'   => smliser__( 'Quota Exceeded' ),
				'message' => smliser__( 'You have used all available quota for this operation.' )
			],

			// 500 Internal Errors
			'internal_server_error' => [
				'status'  => 500,
				'title'   => smliser__( 'Internal Server Error' ),
				'message' => smliser__( 'An unexpected server error occurred while processing the request.' )
			],
			'database_error' => [
				'status'  => 500,
				'title'   => smliser__( 'Database Error' ),
				'message' => smliser__( 'A database error occurred while handling the request.' )
			],
			'filesystem_error' => [
				'status'  => 500,
				'title'   => smliser__( 'Filesystem Error' ),
				'message' => smliser__( 'A file or directory operation failed unexpectedly.' )
			],

			// 501 Not Implemented
			'not_implemented' => [
				'status'  => 501,
				'title'   => smliser__( 'Method not implemented' ),
				'message' => smliser__( 'The server does not support the action requested.' )
			],

			// 502 Bad Gateway
			'dependency_failure' => [
				'status'  => 502,
				'title'   => smliser__( 'Bad Gateway' ),
				'message' => smliser__( 'An external dependency or microservice failed to respond correctly.' )
			],

			// 503 Service Unavailable
			'service_unavailable' => [
				'status'  => 503,
				'title'   => smliser__( 'Service Unavailable' ),
				'message' => smliser__( 'The service is temporarily unavailable or under maintenance.' )
			],

			// 504 Gateway Timeout
			'gateway_timeout' => [
				'status'  => 504,
				'title'   => smliser__( 'Gateway Timeout' ),
				'message' => smliser__( 'The upstream service did not respond in time.' )
			],

		];
	}

	/**
	 * Constructor for RequestException.
	 *
	 * Creates the exception by passing a known error slug, which automatically
	 * loads the correct HTTP status code, title, and default message.
	 *
	 * @param string $error_slug The known error code (must exist in error_map() keys).
	 * @param string|null $custom_message Optional custom message.
	 * @param mixed $custom_data Optional custom data array to merge with defaults.
	 */
	public function __construct( string $error_slug, ?string $custom_message = null, $custom_data = [] ) {

		$has_map = isset( static::error_map()[ $error_slug ] );

		$default_data = $has_map
			? static::error_map()[ $error_slug ]
			: static::error_map()['invalid_input'];

		$resolved_slug = $error_slug;

		$message = $custom_message ?? $default_data['message'];
		
		$data = array_merge(
			[
				'status' => $default_data['status'],
				'title'  => $default_data['title'],
			],
			(array) $custom_data
		);

		parent::__construct( $resolved_slug, $message, $data );
	}

	/**
	 * Static factory.
	 * 
	 * @param string $error_slug The known error code (must exist in error_map() keys).
	 * @param string|null $custom_message Optional custom message.
	 * @param mixed $custom_data Optional custom data array to merge with defaults.
	 */
	public static function make( string $error_slug, ?string $custom_message, mixed $custom_data ) : static {
		return new static( $error_slug, $custom_message, $custom_data );
	}
}
