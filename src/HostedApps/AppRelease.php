<?php
/**
 * App release class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps
 */

declare( strict_types=1 );

namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Exceptions\StorageException;
use SmartLicenseServer\HostedApps\Storage\AppFile;
use SmartLicenseServer\HostedApps\Storage\AppStorage;

/**
 * Describes what an update client downloads for a hosted app, and how it
 * checks it.
 *
 * Philosophy: the server states only what it stores; the client never
 * builds a URL or guesses a file name.
 *
 * The release is the main package ({slug}.zip), always the latest version
 * of the app. Its integrity is the hosting attestation (HostingAttestation),
 * signed by this installation's key:
 *
 *     version:   the app's version
 *     security:  true when the app's manifest says "security": true
 *     package:   the main package's name and download URL
 *     integrity: { signer: "host", checksums, signature }, or null when
 *                the package is not attested
 *
 * A client may instead target one named artifact (a developer-signed
 * package). Its integrity is the developer's own sidecar artifacts,
 * <base>.sha256 and <base>.sha256.sig, where <base> is the artifact's name
 * without ".zip". The server does not know which version an artifact
 * holds, so version and security are null:
 *
 *     package:   the artifact's name and download URL
 *     integrity: { signer: "publisher", checksums, signature (null when
 *                not published) }, or null without a .sha256 artifact
 *
 * The checksums and signature are embedded as their exact text, so the
 * signature verifies against them directly. Download URLs are plain; a
 * client of a monetized app (requires_token) adds its download token.
 */
final class AppRelease {

	/**
	 * Integrity signed by this installation's attestation key.
	 *
	 * @var string
	 */
	public const SIGNER_HOST = 'host';

	/**
	 * Integrity published by the app's developer.
	 *
	 * @var string
	 */
	public const SIGNER_PUBLISHER = 'publisher';

	/**
	 * Largest sidecar artifact read into a release, in bytes.
	 *
	 * @var int
	 */
	public const MAX_SIDECAR_BYTES = 65536;

	/**
	 * Constructor.
	 *
	 * @param AppStorage $storage    The app storage facade.
	 * @param URLManager $urlmanager The URL manager.
	 */
	public function __construct(
		private AppStorage $storage,
		private URLManager $urlmanager
	) {}

	/**
	 * Describe an app's release.
	 *
	 * @param HostedAppsInterface $app      The app.
	 * @param string|null         $artifact Name of a ZIP artifact to target; null for the main package.
	 * @return array{
	 *     type: string,
	 *     slug: string,
	 *     version: ?string,
	 *     security: ?bool,
	 *     requires_token: bool,
	 *     package: array{name: string, url: string},
	 *     integrity: array{signer: string, checksums: string, signature: ?string}|null
	 * }
	 * @throws RequestException 400 for an invalid artifact name; 404 when there is nothing to download.
	 */
	public function describe( HostedAppsInterface $app, ?string $artifact = null ) : array {
		$release = null === $artifact ? $this->main_package( $app ) : $this->named_artifact( $app, $artifact );

		return [
			'type'           => $app->get_type(),
			'slug'           => $app->get_slug(),
			'version'        => $release['version'],
			'security'       => $release['security'],
			'requires_token' => $app->is_monetized(),
			'package'        => $release['package'],
			'integrity'      => $release['integrity'],
		];
	}

	/**
	 * The main package and its hosting attestation.
	 *
	 * @param HostedAppsInterface $app The app.
	 * @return array{version: ?string, security: bool, package: array{name: string, url: string}, integrity: ?array}
	 * @throws RequestException 404 when the app has no package to download.
	 */
	private function main_package( HostedAppsInterface $app ) : array {
		$type = $app->get_type();
		$slug = $app->get_slug();

		if ( null === $app->get_package() && null === $app->get_custom_download_url() ) {
			throw new RequestException( 'release_not_found', sprintf( smliser__( 'The %s "%s" has no package to download.' ), $type, $slug ), [ 'status' => 404 ] );
		}

		try {
			$attestation = $this->storage->get_attestation( $type, $slug );
		} catch ( StorageException ) {
			$attestation = null;
		}

		$version = $app->get_version();

		return [
			'version'   => '' === $version ? null : $version,
			'security'  => true === ( $app->get_manifest()['security'] ?? false ),
			'package'   => [
				'name' => $slug . '.zip',
				'url'  => $app->get_download_url()->url(),
			],
			'integrity' => null === $attestation ? null : [
				'signer'    => self::SIGNER_HOST,
				'checksums' => $attestation['checksums'],
				'signature' => $attestation['signature'],
			],
		];
	}

	/**
	 * A named ZIP artifact and the developer's sidecars.
	 *
	 * @param HostedAppsInterface $app      The app.
	 * @param string              $artifact Artifact name.
	 * @return array{version: null, security: null, package: array{name: string, url: string}, integrity: ?array}
	 * @throws RequestException 400 for an invalid name; 404 when the artifact does not exist.
	 */
	private function named_artifact( HostedAppsInterface $app, string $artifact ) : array {
		$type = $app->get_type();
		$slug = $app->get_slug();

		if ( ! AppFile::is_segment( $artifact ) || ! str_ends_with( strtolower( $artifact ), '.zip' ) || 4 === strlen( $artifact ) ) {
			throw new RequestException( 'invalid_input', smliser__( 'The artifact must be the name of a ZIP artifact, e.g. "acme-1.2.0.zip".' ), [ 'status' => 400 ] );
		}

		try {
			$names = array_column( $this->storage->list_artifacts( $type, $slug ), 'size', 'name' );
		} catch ( StorageException ) {
			$names = [];
		}

		if ( ! isset( $names[ $artifact ] ) ) {
			throw new RequestException( 'release_not_found', sprintf( smliser__( 'The %s "%s" has no artifact named "%s".' ), $type, $slug, $artifact ), [ 'status' => 404 ] );
		}

		$base      = substr( $artifact, 0, -4 );
		$checksums = $this->read_sidecar( $type, $slug, $base . '.sha256', $names );
		$signature = null === $checksums ? null : $this->read_sidecar( $type, $slug, $base . '.sha256.sig', $names );

		return [
			'version'   => null,
			'security'  => null,
			'package'   => [
				'name' => $artifact,
				'url'  => $this->urlmanager->app_artifact_download_url( $type, $slug, $artifact )->url(),
			],
			'integrity' => null === $checksums ? null : [
				'signer'    => self::SIGNER_PUBLISHER,
				'checksums' => $checksums,
				'signature' => $signature,
			],
		];
	}

	/**
	 * Read a small sidecar artifact.
	 *
	 * @param string             $type  App type.
	 * @param string             $slug  App slug.
	 * @param string             $name  Artifact name.
	 * @param array<string, int> $names Sizes of the app's artifacts, keyed by name.
	 * @return string|null Its exact contents; null when it is missing, empty, too large or unreadable.
	 */
	private function read_sidecar( string $type, string $slug, string $name, array $names ) : ?string {
		$size = $names[ $name ] ?? null;

		if ( null === $size || $size < 1 || $size > self::MAX_SIDECAR_BYTES ) {
			return null;
		}

		try {
			$stream = $this->storage->read_stream( AppFile::artifact( $type, $slug, $name ) );
		} catch ( StorageException ) {
			return null;
		}

		$contents = stream_get_contents( $stream, self::MAX_SIDECAR_BYTES );
		fclose( $stream );

		return is_string( $contents ) && '' !== $contents ? $contents : null;
	}
}
