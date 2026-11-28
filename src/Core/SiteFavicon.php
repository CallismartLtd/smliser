<?php
/**
 * Site favicon class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Core
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\I18n\I18n;
use SmartLicenseServer\I18n\WPTranslator;
use SmartLicenseServer\SettingsAPI\Settings;
use Throwable;

/**
 * The site favicon: the icon of the admin dashboard, the client dashboard
 * and the public theme pages.
 *
 * An uploaded icon is stored in the public uploads directory
 * (SMLISER_UPLOADS_DIR . 'site/') under a name derived from its contents,
 * so its URL changes whenever the icon does (uploads are served with a
 * long, immutable cache lifetime). Settings::SITE_FAVICON holds that name;
 * without one, the default icon is used.
 *
 * Only PNG and ICO files are accepted, by their detected content, not their
 * name. SVG is not: an SVG is a document that can carry scripts, and the
 * uploads directory is served from the site's own origin, so an SVG icon
 * opened directly would run them. There is no SVG sanitizer in the
 * codebase, and PNG and ICO cover every browser.
 *
 * Under WordPress the WordPress site icon applies (Settings → General →
 * Site Icon, printed by wp_head()): nothing is printed and nothing can be
 * uploaded here.
 */
class SiteFavicon {

	/**
	 * File input name of the upload field on the settings page.
	 */
	public const UPLOAD_FIELD = 'site_favicon_file';

	/**
	 * Field name that asks to remove the uploaded icon (value "1").
	 */
	public const REMOVE_FIELD = 'site_favicon_remove';

	/**
	 * Largest icon accepted, in bytes.
	 *
	 * @var int
	 */
	public const MAX_BYTES = 512 * 1024;

	/**
	 * Smallest icon accepted, in pixels per side.
	 *
	 * @var int
	 */
	public const MIN_SIDE = 16;

	/**
	 * Largest icon accepted, in pixels per side.
	 *
	 * @var int
	 */
	public const MAX_SIDE = 512;

	/**
	 * Icon types accepted: detected MIME type => stored extension.
	 *
	 * @var array<string, string>
	 */
	public const TYPES = [
		'image/png'                => 'png',
		'image/vnd.microsoft.icon' => 'ico',
		'image/x-icon'             => 'ico',
	];

	/**
	 * Labels of the accepted types, for messages.
	 *
	 * @var string[]
	 */
	public const TYPE_LABELS = [ 'PNG', 'ICO' ];

	/**
	 * The icon used when none is uploaded (path inside the assets directory).
	 */
	public const DEFAULT_ICON = 'images/smliser-icon.svg';

	/**
	 * Shape of a stored icon's file name.
	 */
	private const STORED_NAME_PATTERN = '/^favicon-[a-f0-9]{16}\.(?:png|ico)$/';

	/**
	 * Constructor.
	 *
	 * @param Settings   $settings   Site settings.
	 * @param FileSystem $fs         Filesystem API.
	 * @param URLManager $urlmanager URL API.
	 */
	public function __construct(
		protected Settings $settings,
		protected FileSystem $fs,
		protected URLManager $urlmanager
	) {}

	/**
	 * Whether WordPress owns the site icon (the WordPress environment).
	 *
	 * @return bool
	 */
	public function is_managed_by_wordpress() : bool {
		return I18n::translator() instanceof WPTranslator;
	}

	/**
	 * Absolute path of the directory uploaded icons are kept in, with a trailing slash.
	 *
	 * @return string
	 */
	public function directory() : string {
		return SMLISER_UPLOADS_DIR . 'site/';
	}

	/*
	|---------
	| READING
	|---------
	*/

	/**
	 * File name of the uploaded icon, when there is one and its file exists.
	 *
	 * @return string|null
	 */
	public function stored_name() : ?string {
		try {
			$name = $this->settings->get( Settings::SITE_FAVICON, '' );
		} catch ( Throwable ) {
			return null;
		}

		if ( ! is_string( $name ) || 1 !== preg_match( self::STORED_NAME_PATTERN, $name ) ) {
			return null;
		}

		return $this->fs->is_file( $this->directory() . $name ) ? $name : null;
	}

	/**
	 * Whether an uploaded icon is in use (rather than the default one).
	 *
	 * @return bool
	 */
	public function is_custom() : bool {
		return null !== $this->stored_name();
	}

	/**
	 * URL of the icon in use: the uploaded one, else the default one.
	 *
	 * @return URL
	 */
	public function url() : URL {
		$name = $this->stored_name();

		return null === $name
			? $this->default_url()
			: $this->urlmanager->uploads_url( 'site' )->append_path( $name );
	}

	/**
	 * URL of the default icon.
	 *
	 * @return URL
	 */
	public function default_url() : URL {
		return $this->urlmanager->assets_url( self::DEFAULT_ICON );
	}

	/**
	 * MIME type of the icon in use, for the type attribute of the link.
	 *
	 * @return string
	 */
	public function mime_type() : string {
		$name = (string) $this->stored_name();

		return match ( FileSystemHelper::get_extension( $name ) ) {
			'png'   => 'image/png',
			'ico'   => 'image/x-icon',
			default => 'image/svg+xml',
		};
	}

	/**
	 * The <link rel="icon"> tag for a page's <head>.
	 *
	 * Empty under WordPress, where wp_head() prints the WordPress site icon.
	 *
	 * @return string
	 */
	public function link_tag() : string {
		if ( $this->is_managed_by_wordpress() ) {
			return '';
		}

		return sprintf(
			'<link rel="icon" href="%1$s" type="%2$s">' . "\n",
			\escUrl( $this->url()->url() ),
			\escAttr( $this->mime_type() )
		);
	}

	/*
	|---------
	| WRITING
	|---------
	*/

	/**
	 * Why an uploaded icon is not accepted, or null when it is.
	 *
	 * The upload must have succeeded, be at most MAX_BYTES, be a PNG or an
	 * ICO by its detected content (finfo, not the file name or the type the
	 * browser sent), decode as that same type, and measure between MIN_SIDE
	 * and MAX_SIDE pixels per side.
	 *
	 * @param UploadedFile $file The upload.
	 * @return RequestException|null
	 */
	public function validate( UploadedFile $file ) : ?RequestException {
		if ( ! $file->exists() || ! $file->is_upload_successful() ) {
			return new RequestException( 'invalid_param', $file->get_error_message(), [ 'status' => 400, 'field_id' => self::UPLOAD_FIELD ] );
		}

		if ( ! $file->is_uploaded_file() ) {
			return new RequestException( 'invalid_param', smliser__( 'The favicon could not be read. Please upload it again.' ), [ 'status' => 400, 'field_id' => self::UPLOAD_FIELD ] );
		}

		// Measured on disk: the size in the upload entry is reported by the client.
		$size = (int) $this->fs->filesize( (string) $file->get_tmp_path() );

		if ( $size < 1 || $size > self::MAX_BYTES ) {
			return new RequestException(
				'invalid_param',
				/* translators: %d: largest file size, in kilobytes. */
				sprintf( smliser__( 'The favicon must be %d KB or smaller.' ), self::MAX_BYTES / 1024 ),
				[ 'status' => 413, 'field_id' => self::UPLOAD_FIELD ]
			);
		}

		$mime = (string) $file->get_detected_mime();

		if ( ! isset( self::TYPES[ $mime ] ) ) {
			return new RequestException(
				'invalid_param',
				/* translators: %s: list of file types, e.g. "PNG, ICO". */
				sprintf( smliser__( 'The favicon must be a %s file.' ), implode( ', ', self::TYPE_LABELS ) ),
				[ 'status' => 415, 'field_id' => self::UPLOAD_FIELD ]
			);
		}

		$info = @getimagesize( (string) $file->get_tmp_path() );

		if ( false === $info || ( self::TYPES[ $info['mime'] ] ?? null ) !== self::TYPES[ $mime ] ) {
			return new RequestException( 'invalid_param', smliser__( 'The favicon image could not be read. Please upload a valid PNG or ICO file.' ), [ 'status' => 422, 'field_id' => self::UPLOAD_FIELD ] );
		}

		[ $width, $height ] = $info;

		if ( $width < self::MIN_SIDE || $height < self::MIN_SIDE || $width > self::MAX_SIDE || $height > self::MAX_SIDE ) {
			return new RequestException(
				'invalid_param',
				/* translators: 1: smallest size, 2: largest size, 3: the image's width, 4: the image's height (all in pixels). */
				sprintf( smliser__( 'The favicon must be between %1$d and %2$d pixels per side; this image is %3$d × %4$d pixels.' ), self::MIN_SIDE, self::MAX_SIDE, $width, $height ),
				[ 'status' => 422, 'field_id' => self::UPLOAD_FIELD ]
			);
		}

		return null;
	}

	/**
	 * Validate and store an uploaded icon, and use it from now on.
	 *
	 * The icon replaced, if any, is deleted.
	 *
	 * @param UploadedFile $file The upload.
	 * @return string The stored file name.
	 * @throws RequestException When the icon is not accepted or cannot be saved.
	 */
	public function store( UploadedFile $file ) : string {
		$problem = $this->validate( $file );

		if ( null !== $problem ) {
			throw $problem;
		}

		$previous = $this->stored_name();

		// Named after its contents: a new icon gets a new URL, past the browser cache.
		$name = 'favicon-' . substr( (string) $file->checksum( 'sha256' ), 0, 16 );

		try {
			$stored = basename( $file->move( $this->directory(), $name, true ) );
		} catch ( Throwable ) {
			throw new RequestException( 'server_error', smliser__( 'The favicon could not be saved. Please try again.' ) );
		}

		// The same icon uploaded again: same name, already in use.
		if ( $previous === $stored ) {
			return $stored;
		}

		if ( 1 !== preg_match( self::STORED_NAME_PATTERN, $stored ) || ! $this->settings->set( Settings::SITE_FAVICON, $stored ) ) {
			$this->fs->delete( $this->directory() . $stored );
			throw new RequestException( 'server_error', smliser__( 'The favicon could not be saved. Please try again.' ) );
		}

		if ( null !== $previous ) {
			$this->fs->delete( $this->directory() . $previous );
		}

		return $stored;
	}

	/**
	 * Stop using the uploaded icon and delete it; the default icon applies again.
	 *
	 * @return void
	 */
	public function remove() : void {
		$previous = $this->stored_name();

		$this->settings->delete( Settings::SITE_FAVICON );

		if ( null !== $previous ) {
			$this->fs->delete( $this->directory() . $previous );
		}
	}
}
