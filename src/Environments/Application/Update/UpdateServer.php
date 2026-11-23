<?php
/**
 * UpdateServer class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Update
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Update;

use Callismart\Http\HttpClient;
use SmartLicenseServer\FileSystem\FileSystem;
use Throwable;

/**
 * The Smart License Server instance that publishes this application's
 * releases, and the only source of updates.
 *
 * Every question goes to the server's release route
 * (/repository/{type}/{slug}/release, see AppRelease), which states what to
 * download and embeds the checksums and signature to check it with; this
 * class never builds a download URL or guesses a file name.
 *
 * Two kinds of package, each checked against its own pinned keys:
 *
 *  - This application (download()): the build of a version, published as
 *    the artifact "smart-license-server-<version>-standalone.zip" with the
 *    release's own .sha256 and .sha256.sig artifacts, verified with the
 *    release keys (ReleaseSignature::PUBLIC_KEYS).
 *  - Marketplace modules (download_module()): a theme's or extension's main
 *    package, verified against the hosting attestation of the official
 *    marketplace (HostingAttestation::MARKETPLACE_PUBLIC_KEY), which also
 *    names the app it belongs to.
 *
 * Which keys apply is decided by the caller, never by what the server says.
 */
final class UpdateServer {

	use UpdateProgressTrait;

	/**
	 * The update server URL.
	 *
	 * @var string
	 */
	public const HOST = 'https://smliser.local';

	/**
	 * Path of the server's REST API.
	 *
	 * @var string
	 */
	public const API_PATH = '/smliser/v1';

	/**
	 * Hosted app type of this application on the update server.
	 *
	 * @var string
	 */
	public const APP_TYPE = 'software';

	/**
	 * Hosted app slug, also the release package name.
	 *
	 * @var string
	 */
	public const APP_SLUG = 'smart-license-server';

	/**
	 * Build target this installation runs.
	 *
	 * @var string
	 */
	public const TARGET = 'standalone';

	/**
	 * Seconds allowed for the release request.
	 *
	 * @var int
	 */
	private const TIMEOUT = 20;

	/**
	 * Seconds allowed for the zip download.
	 *
	 * @var int
	 */
	private const DOWNLOAD_TIMEOUT = 600;

	/**
	 * Constructor.
	 *
	 * @param HttpClient      $http                 Outgoing HTTP client.
	 * @param PackageVerifier $verifier             Checks application releases (release keys).
	 * @param FileSystem      $fs                   Filesystem API.
	 * @param PackageVerifier $marketplace_verifier Checks marketplace modules (marketplace key).
	 */
	public function __construct(
		private HttpClient $http,
		private PackageVerifier $verifier,
		private FileSystem $fs,
		private PackageVerifier $marketplace_verifier
	) {}

	/*
	|------------
	| Releases
	|------------
	*/

	/**
	 * The release of a hosted app on the update server.
	 *
	 * @param string        $app_type Hosted app type on the update server.
	 * @param string        $app_slug Hosted app slug on the update server.
	 * @param string|null   $artifact Name of a ZIP artifact to target; null for the main package.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{
	 *     type: string,
	 *     slug: string,
	 *     version: ?string,
	 *     security: ?bool,
	 *     requires_token: bool,
	 *     package: array{name: string, url: string},
	 *     integrity: array{signer: string, checksums: string, signature: ?string}|null,
	 *     source: string
	 * } The release as the server describes it (see AppRelease), and the URL it was asked at.
	 * @throws UpdateException When the server cannot be reached, does not publish it, or does not answer as expected.
	 */
	public function release( string $app_type, string $app_slug, ?string $artifact = null, ?callable $progress = null ) : array {
		$url = self::HOST . self::API_PATH . '/repository/' . rawurlencode( $app_type ) . '/' . rawurlencode( $app_slug ) . '/release';

		if ( null !== $artifact ) {
			$url .= '?' . http_build_query( [ 'artifact' => $artifact ] );
		}

		$this->report( $progress, 'check', self::STATUS_INFO, sprintf( smliser__( 'Asking %s for the release.' ), $url ) );

		try {
			$response = $this->http->get(
				url: $url,
				headers: [
					'Accept'     => 'application/json',
					'User-Agent' => \smliser_http_ua( true ),
				],
				options: [ 'timeout' => self::TIMEOUT ]
			);
		} catch ( Throwable $e ) {
			throw new UpdateException( sprintf( smliser__( 'Could not reach the update server: %s' ), $e->getMessage() ), 0, $e );
		}

		if ( 404 === $response->status_code ) {
			throw new UpdateException(
				null !== $artifact
					? sprintf( smliser__( 'The update server does not publish %s yet; try again later.' ), $artifact )
					: ( self::APP_TYPE === $app_type && self::APP_SLUG === $app_slug
						? smliser__( 'The update server does not publish this application.' )
						: sprintf( smliser__( 'The update server does not publish "%s".' ), $app_slug ) )
			);
		}

		if ( ! $response->is_success() ) {
			throw new UpdateException( sprintf( smliser__( 'The update server answered with HTTP %d; try again later.' ), $response->status_code ) );
		}

		$body    = $response->json();
		$release = is_array( $body ) ? ( $body['release'] ?? null ) : null;

		if ( ! is_array( $release ) || ! self::is_release( $release ) ) {
			throw new UpdateException( smliser__( 'The update server did not describe a release; try again later.' ) );
		}

		// The release must be the one asked for, so its identity can be checked against the attestation.
		if ( $app_type !== $release['type'] || $app_slug !== $release['slug'] || ( null !== $artifact && $artifact !== $release['package']['name'] ) ) {
			throw new UpdateException( smliser__( 'The update server described a different release than the one asked for.' ) );
		}

		$release['source'] = $url;

		return $release;
	}

	/**
	 * The latest release of this application.
	 *
	 * A release is a security release when its app.json has "security": true.
	 *
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{version: string, security: bool, source: string}
	 * @throws UpdateException When the server cannot be reached or does not answer as expected.
	 */
	public function latest( ?callable $progress = null ) : array {
		return $this->latest_of( self::APP_TYPE, self::APP_SLUG, $progress );
	}

	/**
	 * The latest version of a hosted app the update server publishes.
	 *
	 * @param string        $app_type Hosted app type on the update server.
	 * @param string        $app_slug Hosted app slug on the update server.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{version: string, security: bool, source: string}
	 * @throws UpdateException When the server cannot be reached, does not publish the app, or does not answer as expected.
	 */
	public function latest_of( string $app_type, string $app_slug, ?callable $progress = null ) : array {
		$release = $this->release( $app_type, $app_slug, null, $progress );
		$version = self::assert_version( $release );

		$this->report( $progress, 'check', self::STATUS_OK, sprintf( $release['security'] ? smliser__( 'The update server publishes %s %s (security release).' ) : smliser__( 'The update server publishes %s %s.' ), $app_slug, $version ) );

		return [
			'version'  => $version,
			'security' => true === $release['security'],
			'source'   => $release['source'],
		];
	}

	/**
	 * Browse the hosted apps the update server publishes (its repository REST listing).
	 *
	 * The server's search matches an app's name, slug or author; an empty
	 * search lists every app of the type.
	 *
	 * @param string        $app_type Hosted app type on the update server.
	 * @param string        $search   Search term; empty to list.
	 * @param int           $page     Page number, from 1.
	 * @param int           $limit    Apps per page.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{apps: list<array<string, mixed>>, pagination: array{total: int, page: int, limit: int, total_pages: int}}
	 *         The apps as the REST API describes them, and the pagination.
	 * @throws UpdateException When the server cannot be reached or does not answer as expected.
	 */
	public function browse( string $app_type, string $search = '', int $page = 1, int $limit = 24, ?callable $progress = null ) : array {
		$query = [ 'app_types' => [ $app_type ], 'page' => max( 1, $page ), 'limit' => max( 1, $limit ) ];

		if ( '' !== $search ) {
			$query['search'] = $search;
		}

		$url = self::HOST . self::API_PATH . '/repository/?' . http_build_query( $query );

		$this->report( $progress, 'check', self::STATUS_INFO, sprintf( smliser__( 'Asking %s for its %s apps.' ), self::HOST, $app_type ) );

		try {
			$response = $this->http->get(
				url: $url,
				headers: [
					'Accept'     => 'application/json',
					'User-Agent' => \smliser_http_ua( true ),
				],
				options: [ 'timeout' => self::TIMEOUT ]
			);
		} catch ( Throwable $e ) {
			throw new UpdateException( sprintf( smliser__( 'Could not reach the update server: %s' ), $e->getMessage() ), 0, $e );
		}

		if ( ! $response->is_success() ) {
			throw new UpdateException( sprintf( smliser__( 'The update server answered with HTTP %d; try again later.' ), $response->status_code ) );
		}

		$body = $response->json();
		$apps = is_array( $body ) ? ( $body['apps'] ?? null ) : null;

		if ( ! is_array( $apps ) || ! is_array( $body['pagination'] ?? null ) ) {
			throw new UpdateException( smliser__( 'The update server did not answer with a repository listing; try again later.' ) );
		}

		return [
			'apps'       => array_values( array_filter( $apps, 'is_array' ) ),
			'pagination' => [
				'total'       => (int) ( $body['pagination']['total'] ?? 0 ),
				'page'        => (int) ( $body['pagination']['page'] ?? $page ),
				'limit'       => (int) ( $body['pagination']['limit'] ?? $limit ),
				'total_pages' => (int) ( $body['pagination']['total_pages'] ?? 0 ),
			],
		];
	}

	/*
	|-------------
	| Downloads
	|-------------
	*/

	/**
	 * Download a version of this application and verify it with the release keys.
	 *
	 * @param string        $version        Version to download.
	 * @param string        $dir            Existing, writable directory to download into.
	 * @param bool          $trust_unsigned Accept a package without a valid signature.
	 * @param callable|null $progress       Progress callback, see UpdateProgressTrait.
	 * @return array{zip: string, source: string, sha256: string, signed_by: ?string, warnings: string[]}
	 *         The verified zip, the URL it came from, its SHA-256, the key that signed it, and warnings.
	 * @throws UpdateException When the release, the download or a check fails; nothing downloaded is kept.
	 */
	public function download( string $version, string $dir, bool $trust_unsigned = false, ?callable $progress = null ) : array {
		$release = $this->release( self::APP_TYPE, self::APP_SLUG, self::package_base( $version ) . '.zip', $progress );

		return $this->fetch_verified( $release, $dir, $this->verifier, $trust_unsigned, null, $progress );
	}

	/**
	 * Download a marketplace module's main package and verify its hosting
	 * attestation with the pinned marketplace key.
	 *
	 * @param array         $release  The module's release, from release() with no artifact.
	 * @param string        $dir      Existing, writable directory to download into.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{zip: string, source: string, sha256: string, signed_by: ?string, warnings: string[]}
	 * @throws UpdateException When the download or a check fails; nothing downloaded is kept.
	 */
	public function download_module( array $release, string $dir, ?callable $progress = null ) : array {
		if ( ! self::is_release( $release ) ) {
			throw new UpdateException( smliser__( 'The module release is not a release from release().' ) );
		}

		$identity = [ 'type' => $release['type'], 'slug' => $release['slug'], 'artifact' => $release['slug'] . '.zip' ];

		if ( $identity['artifact'] !== $release['package']['name'] ) {
			throw new UpdateException( sprintf( smliser__( 'Marketplace modules install from their main package, not from %s.' ), $release['package']['name'] ) );
		}

		return $this->fetch_verified( $release, $dir, $this->marketplace_verifier, false, $identity, $progress );
	}

	/**
	 * Release artifact base name of a version of this application.
	 *
	 * @param string $version Version.
	 * @return string E.g. "smart-license-server-1.2.0-standalone".
	 */
	public static function package_base( string $version ) : string {
		return self::APP_SLUG . '-' . $version . '-' . self::TARGET;
	}

	/**
	 * The version a release announces.
	 *
	 * @param array $release A release from release().
	 * @return string
	 * @throws UpdateException When it announces no valid version.
	 */
	public static function assert_version( array $release ) : string {
		$version = $release['version'] ?? null;

		if ( ! is_string( $version ) || 1 !== preg_match( '/^\d+\.\d+\.\d+([.+-][0-9A-Za-z.+-]+)?$/', $version ) ) {
			throw new UpdateException( smliser__( 'The update server did not report a valid version; try again later.' ) );
		}

		return $version;
	}

	/**
	 * Download a release's package and verify it against its integrity.
	 *
	 * @param array                $release        A release from release().
	 * @param string               $dir            Existing, writable directory to download into.
	 * @param PackageVerifier      $verifier       Verifier holding the keys the package must be signed with.
	 * @param bool                 $trust_unsigned Accept a package without a valid signature.
	 * @param array|null           $identity       Attestation identity the checksums must name; null for none.
	 * @param callable|null        $progress       Progress callback, see UpdateProgressTrait.
	 * @return array{zip: string, source: string, sha256: string, signed_by: ?string, warnings: string[]}
	 * @throws UpdateException When the download or a check fails; nothing downloaded is kept.
	 */
	private function fetch_verified( array $release, string $dir, PackageVerifier $verifier, bool $trust_unsigned, ?array $identity, ?callable $progress ) : array {
		if ( $release['requires_token'] ) {
			throw new UpdateException( sprintf( smliser__( '%s needs a download token, which this installation cannot send yet.' ), $release['package']['name'] ) );
		}

		$url = $release['package']['url'];
		$zip = rtrim( $dir, '/\\' ) . '/' . $release['package']['name'];

		$this->report( $progress, 'download', self::STATUS_INFO, sprintf( 'Downloading %s.', $url ) );

		try {
			$response = $this->http->download(
				url: $url,
				destination: $zip,
				headers: [
					'User-Agent' => \smliser_http_ua( true ),
				],
				options: [ 'timeout' => self::DOWNLOAD_TIMEOUT ]
			);
		} catch ( Throwable $e ) {
			$this->discard( $zip );
			throw new UpdateException( sprintf( smliser__( 'The download of %s failed: %s' ), basename( $zip ), $e->getMessage() ), 0, $e );
		}

		if ( ! $response->is_success() ) {
			$this->discard( $zip );
			throw new UpdateException( sprintf( smliser__( 'The download of %s failed with HTTP %d.' ), basename( $zip ), $response->status_code ) );
		}

		$this->report( $progress, 'download', self::STATUS_OK, sprintf( smliser__( 'Downloaded %s (%s) to %s.' ), basename( $zip ), $this->size( $zip ), $zip ) );

		$checksums = $release['integrity']['checksums'] ?? null;
		$signature = $release['integrity']['signature'] ?? null;

		try {
			$verified = $verifier->verify( $zip, $checksums, $signature, $trust_unsigned, $progress, $identity );
		} catch ( UpdateException $e ) {
			$this->discard( $zip );
			throw $e;
		}

		return [ 'zip' => $zip, 'source' => $url ] + $verified;
	}

	/**
	 * Whether an array has the shape of a release (see AppRelease).
	 *
	 * The package name must be a plain ZIP file name, since it becomes the
	 * downloaded file's name.
	 *
	 * @param array $release Candidate release.
	 * @return bool
	 */
	private static function is_release( array $release ) : bool {
		$package   = $release['package'] ?? null;
		$integrity = $release['integrity'] ?? null;

		return is_string( $release['type'] ?? null )
			&& is_string( $release['slug'] ?? null )
			&& ( null === ( $release['version'] ?? null ) || is_string( $release['version'] ) )
			&& ( null === ( $release['security'] ?? null ) || is_bool( $release['security'] ) )
			&& is_bool( $release['requires_token'] ?? null )
			&& is_array( $package )
			&& is_string( $package['name'] ?? null )
			&& 1 === preg_match( '/^[^\/\\\\\x00-\x1F]+\.zip$/i', $package['name'] )
			&& ! str_starts_with( $package['name'], '.' )
			&& is_string( $package['url'] ?? null )
			&& '' !== $package['url']
			&& ( null === $integrity || (
				is_array( $integrity )
				&& is_string( $integrity['checksums'] ?? null )
				&& ( null === ( $integrity['signature'] ?? null ) || is_string( $integrity['signature'] ) )
			) );
	}

	/**
	 * A file's size for messages.
	 *
	 * @param string $path File path.
	 * @return string E.g. "4.2 MB".
	 */
	private function size( string $path ) : string {
		$bytes = (int) $this->fs->filesize( $path );

		return $bytes >= 1048576 ? sprintf( '%.1f MB', $bytes / 1048576 ) : sprintf( '%d KB', (int) ceil( $bytes / 1024 ) );
	}

	/**
	 * Remove a download that will not be used.
	 *
	 * @param string $path File path.
	 * @return void
	 */
	private function discard( string $path ) : void {
		if ( $this->fs->is_file( $path ) ) {
			$this->fs->delete( $path );
		}
	}
}
