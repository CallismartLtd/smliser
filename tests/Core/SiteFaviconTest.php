<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\Core;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\Contracts\URLManagerInterface;
use SmartLicenseServer\Core\SiteFavicon;
use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\FileSystem\Adapters\DirectFileSystem;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\SettingsAPI\Providers\SettingsStorageInterface;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Validation and storage of the site favicon (SiteFavicon).
 *
 * Uploads are simulated with temporary files carrying SMLISER_UPLOAD_TMP_PREFIX,
 * the marker UploadedFile accepts for uploads parsed by the application itself.
 */
final class SiteFaviconTest extends TestCase {

	private static string $root;

	private static FileSystem $fs;

	private static Settings $settings;

	private SiteFavicon $favicon;

	public static function setUpBeforeClass() : void {
		if ( ! function_exists( 'imagepng' ) ) {
			self::markTestSkipped( 'The GD extension is needed to build test images.' );
		}

		self::$root = sys_get_temp_dir() . '/smliser-favicon-test-' . bin2hex( random_bytes( 4 ) ) . '/';
		mkdir( self::$root . 'uploads', 0755, true );

		defined( 'SMLISER_ROOT' ) || define( 'SMLISER_ROOT', dirname( __DIR__, 2 ) . '/' );
		defined( 'SMLISER_UPLOAD_TMP_PREFIX' ) || define( 'SMLISER_UPLOAD_TMP_PREFIX', 'smliser_tmp_' );
		defined( 'SMLISER_FILE_PERMISSION' ) || define( 'SMLISER_FILE_PERMISSION', 0644 );
		defined( 'SMLISER_DIR_PERMISSION' ) || define( 'SMLISER_DIR_PERMISSION', 0755 );
		defined( 'SMLISER_UPLOADS_DIR' ) || define( 'SMLISER_UPLOADS_DIR', self::$root . 'uploads/' );

		self::$fs       = FileSystem::instance( new DirectFileSystem( 0644, 0755 ) );
		self::$settings = Settings::instance( new InMemorySettingsStorage() );
	}

	public static function tearDownAfterClass() : void {
		if ( isset( self::$root ) && is_dir( self::$root ) ) {
			exec( 'rm -rf ' . escapeshellarg( self::$root ) );
		}
	}

	protected function setUp() : void {
		self::$settings->delete( Settings::SITE_FAVICON );

		$urls = $this->createStub( URLManagerInterface::class );
		$urls->method( 'uploads_url' )->willReturnCallback( static fn ( string $path = '' ) => new URL( 'https://example.test/uploads/' . $path ) );
		$urls->method( 'assets_url' )->willReturnCallback( static fn ( string $path = '', array $query = [] ) => new URL( 'https://example.test/assets/' . $path ) );

		$this->favicon = new SiteFavicon( self::$settings, self::$fs, new URLManager( $urls ) );
	}

	/*
	|------------
	| VALIDATION
	|------------
	*/

	public function test_accepts_a_png() : void {
		$this->assertNull( $this->favicon->validate( $this->upload( $this->png( 32, 32 ), 'icon.png' ) ) );
	}

	public function test_accepts_an_ico() : void {
		$this->assertNull( $this->favicon->validate( $this->upload( $this->ico( 32 ), 'favicon.ico' ) ) );
	}

	public function test_rejects_an_svg() : void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><script>alert(1)</script><rect width="16" height="16"/></svg>';

		$this->assertRejected( $this->upload( $svg, 'icon.svg' ), 415 );
	}

	public function test_rejects_by_content_not_by_name() : void {
		// An HTML page named like a PNG, sent with an image type.
		$this->assertRejected( $this->upload( '<!doctype html><script>alert(1)</script>', 'icon.png', 'image/png' ), 415 );
	}

	public function test_rejects_other_image_types() : void {
		$image = imagecreatetruecolor( 32, 32 );
		ob_start();
		imagegif( $image );
		$gif = (string) ob_get_clean();

		$this->assertRejected( $this->upload( $gif, 'icon.png', 'image/png' ), 415 );
	}

	public function test_rejects_a_damaged_image() : void {
		// Detected as a PNG, but cut off before the image header ends.
		$this->assertRejected( $this->upload( substr( $this->png( 32, 32 ), 0, 20 ), 'icon.png' ), 422 );
	}

	public function test_rejects_a_file_over_the_size_cap() : void {
		// Measured on disk, whatever size the client reports.
		$contents = $this->png( 32, 32 ) . random_bytes( SiteFavicon::MAX_BYTES );

		$this->assertRejected( $this->upload( $contents, 'icon.png', 'image/png', 1024 ), 413 );
	}

	public function test_rejects_images_too_small_or_too_large() : void {
		$this->assertRejected( $this->upload( $this->png( 8, 8 ), 'icon.png' ), 422 );
		$this->assertRejected( $this->upload( $this->png( SiteFavicon::MAX_SIDE + 1, 32 ), 'icon.png' ), 422 );
	}

	public function test_rejects_a_failed_upload() : void {
		$file = UploadedFile::from_array( [ 'name' => 'icon.png', 'type' => 'image/png', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0 ], 'favicon', self::$fs );

		$this->assertRejected( $file, 400 );
	}

	public function test_rejects_a_path_that_is_not_an_upload() : void {
		// An arbitrary file on disk slipped into the upload entry.
		$path = self::$root . 'not-an-upload.png';
		file_put_contents( $path, $this->png( 32, 32 ) );

		$file = UploadedFile::from_array( [ 'name' => 'icon.png', 'type' => 'image/png', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize( $path ) ], 'favicon', self::$fs );

		$this->assertRejected( $file, 400 );
	}

	/*
	|---------
	| STORAGE
	|---------
	*/

	public function test_default_icon_without_an_upload() : void {
		$this->assertFalse( $this->favicon->is_custom() );
		$this->assertSame( 'https://example.test/assets/' . SiteFavicon::DEFAULT_ICON, $this->favicon->url()->url() );
		$this->assertStringContainsString( 'type="image/svg+xml"', $this->favicon->link_tag() );
	}

	public function test_stores_replaces_and_removes_the_icon() : void {
		$first = $this->favicon->store( $this->upload( $this->png( 32, 32 ), 'icon.png' ) );

		$this->assertMatchesRegularExpression( '/^favicon-[a-f0-9]{16}\.png$/', $first );
		$this->assertFileExists( $this->favicon->directory() . $first );
		$this->assertSame( $first, self::$settings->get( Settings::SITE_FAVICON ) );
		$this->assertSame( 'https://example.test/uploads/site/' . $first, $this->favicon->url()->url() );
		$this->assertSame(
			'<link rel="icon" href="https://example.test/uploads/site/' . $first . '" type="image/png">' . "\n",
			$this->favicon->link_tag()
		);

		$second = $this->favicon->store( $this->upload( $this->ico( 48 ), 'favicon.ico' ) );

		$this->assertMatchesRegularExpression( '/^favicon-[a-f0-9]{16}\.ico$/', $second );
		$this->assertFileDoesNotExist( $this->favicon->directory() . $first );
		$this->assertSame( 'image/x-icon', $this->favicon->mime_type() );

		$this->favicon->remove();

		$this->assertFileDoesNotExist( $this->favicon->directory() . $second );
		$this->assertFalse( $this->favicon->is_custom() );
		$this->assertSame( 'https://example.test/assets/' . SiteFavicon::DEFAULT_ICON, $this->favicon->url()->url() );
	}

	public function test_storing_the_same_icon_again_keeps_it() : void {
		$png   = $this->png( 32, 32 );
		$first = $this->favicon->store( $this->upload( $png, 'icon.png' ) );
		$again = $this->favicon->store( $this->upload( $png, 'icon.png' ) );

		$this->assertSame( $first, $again );
		$this->assertFileExists( $this->favicon->directory() . $first );
		$this->assertTrue( $this->favicon->is_custom() );
	}

	public function test_store_refuses_an_invalid_icon() : void {
		$this->expectException( RequestException::class );

		try {
			$this->favicon->store( $this->upload( '<svg xmlns="http://www.w3.org/2000/svg"/>', 'icon.svg' ) );
		} finally {
			$this->assertFalse( $this->favicon->is_custom() );
		}
	}

	public function test_ignores_a_stored_name_that_is_not_ours() : void {
		self::$settings->set( Settings::SITE_FAVICON, '../../config.php' );

		$this->assertFalse( $this->favicon->is_custom() );
		$this->assertSame( 'https://example.test/assets/' . SiteFavicon::DEFAULT_ICON, $this->favicon->url()->url() );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	private function assertRejected( UploadedFile $file, int $status ) : void {
		$problem = $this->favicon->validate( $file );

		$this->assertInstanceOf( RequestException::class, $problem );
		$this->assertSame( $status, $problem->get_error_data()['status'] ?? null, $problem->getMessage() );
	}

	private function upload( string $contents, string $name, string $type = 'application/octet-stream', ?int $reported_size = null ) : UploadedFile {
		$path = tempnam( self::$root, SMLISER_UPLOAD_TMP_PREFIX );
		file_put_contents( $path, $contents );

		return UploadedFile::from_array(
			[
				'name'     => $name,
				'type'     => $type,
				'tmp_name' => $path,
				'error'    => UPLOAD_ERR_OK,
				'size'     => $reported_size ?? strlen( $contents ),
			],
			'favicon',
			self::$fs
		);
	}

	private function png( int $width, int $height ) : string {
		$image = imagecreatetruecolor( $width, $height );
		imagefill( $image, 0, 0, (int) imagecolorallocate( $image, 30, 120, 200 ) );
		// A pixel that depends on the size, so different sizes have different checksums.
		imagesetpixel( $image, 0, 0, (int) imagecolorallocate( $image, $width % 256, $height % 256, 7 ) );

		ob_start();
		imagepng( $image );

		return (string) ob_get_clean();
	}

	/**
	 * A single-image ICO file holding a PNG (the format browsers use).
	 */
	private function ico( int $side ) : string {
		$png = $this->png( $side, $side );

		return pack( 'vvv', 0, 1, 1 )
			. pack( 'CCCCvvVV', $side % 256, $side % 256, 0, 0, 1, 32, strlen( $png ), 22 )
			. $png;
	}
}

/**
 * Settings kept in memory for the test.
 */
final class InMemorySettingsStorage implements SettingsStorageInterface {

	/** @var array<string, mixed> */
	private array $values = [];

	public function get( string $key, $default = null ) {
		return $this->values[ $key ] ?? $default;
	}

	public function set( string $key, mixed $value ) : bool {
		$this->values[ $key ] = $value;
		return true;
	}

	public function delete( string $key ) : bool {
		unset( $this->values[ $key ] );
		return true;
	}

	public function has( string $key ) : bool {
		return array_key_exists( $key, $this->values );
	}

	public function all( int $page = 1, int $limit = 20 ) : array {
		return $this->values;
	}

	public function search( string $query, int $page, int $limit = 50 ) : array {
		return [];
	}
}
