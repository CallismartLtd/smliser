<?php
/**
 * Specialized Exception for all FileRequest and FileResponse related errors.
 *
 * This class abstracts common HTTP status codes related to file access, 
 * authentication, availability, and processing failures, providing clear
 * documentation for all file-serving endpoints.
 *
 * @package SmartLicenseServer\Exception
 * @author Callistus
 */

namespace SmartLicenseServer\Exceptions;

use Callismart\Http\HttpStatusAwareTrait;

/**
 * Class FileRequestException
 */
class FileRequestException extends Exception {
	use HttpStatusAwareTrait;

	/**
	 * Map of error slugs to their default data (HTTP Status, Title, and Message).
	 *
	 * @var array
	 */
	/**
	 * Known errors: HTTP status, title and message, in the current language.
	 *
	 * @return array<string, array{status: int, title: string, message: string}>
	 */
	protected static function error_map() : array {
		return [

			// 400 Bad Request Errors (Client-side input problems).
			'invalid_app_type_method' => [
				'status'  => 400,
				'title'   => smliser__( 'Invalid App Type' ),
				'message' => smliser__( 'The application type specified in the request is not supported.' )
			],
			'unsupported_repo_type' => [
				'status'  => 400,
				'title'   => smliser__( 'Unsupported Type' ),
				'message' => smliser__( 'The associated repository/filesystem for this application type is not available.' )
			],
			'missing_file_parameter' => [
				'status'  => 400,
				'title'   => smliser__( 'Missing Parameter' ),
				'message' => smliser__( 'Required file identifier (slug or ID) is missing from the request.' )
			],
			'malformed_request' => [
				'status'  => 400,
				'title'   => smliser__( 'Malformed Request' ),
				'message' => smliser__( 'The request format or parameters provided are improperly structured.' )
			],
			'invalid_file_extension' => [
				'status'  => 400,
				'title'   => smliser__( 'Invalid File Type' ),
				'message' => smliser__( 'The uploaded or requested file has an unsupported or mismatched file extension.' )
			],
			'invalid_mime_type' => [
				'status'  => 400,
				'title'   => smliser__( 'Invalid MIME Type' ),
				'message' => smliser__( 'The file MIME type does not match the expected type or is disallowed.' )
			],

			'http_file_download_bad_request' => [
				'status'  => 400,
				'title'   => smliser__( 'Malformed Download Request' ),
				'message' => smliser__( 'Failed to download file(error: bad request).' )
			],
			'file_too_large' => [
				'status'  => 400,
				'title'   => smliser__( 'File Too Large' ),
				'message' => smliser__( 'The uploaded file exceeds the maximum size allowed by the server configuration.' )
			],
			'file_upload_error' => [
				'status'  => 400,
				'title'   => smliser__( 'Upload Failed' ),
				'message' => smliser__( 'The file upload process encountered an unexpected error.' )
			],

			// 401 Unauthorized Errors.
			'invalid_token' => [
				'status'  => 401,
				'title'   => smliser__( 'Unauthorized' ),
				'message' => smliser__( 'The provided download token is invalid, expired, or does not match the file.' )
			],

			'http_file_download_unauthorized' => [
				'status'  => 401,
				'title'   => smliser__( 'Unauthorized Download' ),
				'message' => smliser__( 'Unauthorized file download error.' )
			],
			'auth_header_missing' => [
				'status'  => 401,
				'title'   => smliser__( 'Unauthorized' ),
				'message' => smliser__( 'Authentication credentials are required for this resource but were not provided.' )
			],

			// 402 Payment Required Errors.
			'payment_required' => [
				'status'  => 402,
				'title'   => smliser__( 'Payment Required' ),
				'message' => smliser__( 'This is a monetized application; a valid license or token is required to access the file.' )
			],
			'license_payment_required' => [
				'status'  => 402,
				'title'   => smliser__( 'Payment Required' ),
				'message' => smliser__( 'A valid token is required to access the license document file.' )
			],

			// 403 Forbidden Errors.
			'user_not_authorized' => [
				'status'  => 403,
				'title'   => smliser__( 'Forbidden' ),
				'message' => smliser__( 'The authenticated user does not have permission to access this resource.' )
			],
			'invalid_param_license_id' => [
				'status'  => 400,
				'title'   => smliser__( 'License Invalid' ),
				'message' => smliser__( 'Please provide the license ID in the request parameter.' )
			],
			'license_revoked' => [
				'status'  => 403,
				'title'   => smliser__( 'License Invalid' ),
				'message' => smliser__( 'Access denied because the associated license has been revoked or suspended.' )
			],
			'file_permission_denied' => [
				'status'  => 403,
				'title'   => smliser__( 'Permission Denied' ),
				'message' => smliser__( 'The file exists but cannot be read or written due to insufficient permissions.' )
			],

			// 404 Not Found Errors.
			'app_not_found' => [
				'status'  => 404,
				'title'   => smliser__( 'App Not Found' ),
				'message' => smliser__( 'The requested application (slug or ID) was not found in the repository.' )
			],
			'file_not_found' => [
				'status'  => 404,
				'title'   => smliser__( 'File Not Found' ),
				'message' => smliser__( 'The corresponding file could not be located on the server.' )
			],
			'http_404' => [
				'status'  => 404,
				'title'   => smliser__( 'File Not Found' ),
				'message' => smliser__( 'The corresponding file could not be located on the server.' )
			],
			'directory_not_found' => [
				'status'  => 404,
				'title'   => smliser__( 'Directory Not Found' ),
				'message' => smliser__( 'The specified directory or repository path does not exist on the server.' )
			],

			// 409 Conflict Errors.
			'file_already_exists' => [
				'status'  => 409,
				'title'   => smliser__( 'Conflict' ),
				'message' => smliser__( 'A file with the same name already exists in the target directory.' )
			],
			'repo_sync_conflict' => [
				'status'  => 409,
				'title'   => smliser__( 'Repository Conflict' ),
				'message' => smliser__( 'File synchronization conflict: repository or metadata version mismatch detected.' )
			],

			// 422 Unprocessable Entity Errors.
			'file_integrity_failure' => [
				'status'  => 422,
				'title'   => smliser__( 'File Integrity Error' ),
				'message' => smliser__( 'The file failed integrity checks (e.g., corrupted or tampered).' )
			],
			'file_validation_failed' => [
				'status'  => 422,
				'title'   => smliser__( 'File Validation Error' ),
				'message' => smliser__( 'The file does not meet the required validation criteria (e.g., missing manifest or invalid structure).' )
			],

			// 500 Internal Server Errors.
			'file_reading_error' => [
				'status'  => 500,
				'title'   => smliser__( 'File Reading Error' ),
				'message' => smliser__( 'Internal server error: The file exists but cannot be opened or read by the server.' )
			],
			'file_write_error' => [
				'status'  => 500,
				'title'   => smliser__( 'File Write Error' ),
				'message' => smliser__( 'An error occurred while writing the file to disk.' )
			],
			'disk_space_exhausted' => [
				'status'  => 500,
				'title'   => smliser__( 'Disk Space Error' ),
				'message' => smliser__( 'The server has insufficient storage space to complete the requested file operation.' )
			],
			'filesystem_unavailable' => [
				'status'  => 500,
				'title'   => smliser__( 'Filesystem Error' ),
				'message' => smliser__( 'The underlying filesystem or I/O handler is unavailable or not initialized properly.' )
			],
			'unexpected_repo_failure' => [
				'status'  => 500,
				'title'   => smliser__( 'Repository Error' ),
				'message' => smliser__( 'An unexpected failure occurred while communicating with the application repository.' )
			],
			'file_corrupted' => [
				'status'  => 500,
				'title'   => smliser__( 'Corrupt File' ),
				'message' => smliser__( 'The file appears to be corrupted, incomplete, or unreadable.' )
			],
			'unknown_file_error' => [
				'status'  => 500,
				'title'   => smliser__( 'Unknown File Error' ),
				'message' => smliser__( 'An unexpected or undefined file error occurred during processing.' )
			],
		];
	}

	/**
	 * Constructor for FileRequestException.
	 *
	 * Accepts either a known error slug (looked up in error_map()) or a raw
	 * HTTP status code (resolved to a reason phrase). Unknown slugs fall back
	 * to 'unknown_file_error'. A custom message overrides the map default.
	 * Custom data is merged on top of the resolved status and title.
	 *
	 * @param string|int  $error_slug     Known error slug or raw HTTP status code.
	 * @param string|null $custom_message Optional message override.
	 * @param array       $custom_data    Optional data merged over resolved defaults.
	 */
	public function __construct( string|int $error_slug, ?string $custom_message = null, array $custom_data = [] ) {
		if ( is_int( $error_slug ) ) {
			// Raw HTTP status code — resolve title from reason phrase.
			$defaults = [
				'status'  => $error_slug,
				'title'   => static::reason_phrase( $error_slug ),
				'message' => static::reason_phrase( $error_slug ),
			];
		} else {
			// Named slug — fall back to unknown_file_error if unrecognised.
			$defaults = self::error_map()[ $error_slug ] ?? self::error_map()['unknown_file_error'];
		}

		$message = $custom_message ?: $defaults['message'];
		$data    = array_merge(
			[ 'status' => $defaults['status'], 'title' => $defaults['title'] ],
			$custom_data
		);

		parent::__construct( $error_slug, $message, $data );
	}
}
