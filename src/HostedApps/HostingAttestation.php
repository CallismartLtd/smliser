<?php
/**
 * Hosting attestation class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps
 */

declare( strict_types=1 );

namespace SmartLicenseServer\HostedApps;

use RuntimeException;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Signs the main package of every hosted app with this installation's key.
 *
 * Philosophy: the server vouches for what it serves. Whenever an app's main
 * package ({slug}.zip) is stored, the server writes signed checksums of it
 * (the attestation) beside it. A client that pins this installation's public
 * key can then prove the package it downloaded is the one stored here,
 * unchanged. The attestation is replaced with every new package and is
 * never uploaded by anyone; developer-signed files are named artifacts and
 * never collide with it.
 *
 * The signed checksums name the app, so a signature cannot be moved to
 * another app:
 *
 *     # smliser-attestation-v1
 *     # type: smliser-theme
 *     # slug: acme
 *     # artifact: acme.zip
 *     <sha256>  acme.zip
 *
 * The secret key is SMLISER_ATTESTATION_SIGNING_KEY (base64 Ed25519 secret
 * key) in the environment. Each installation has its own; the official
 * marketplace server uses the key whose public half is pinned in client code
 * as MARKETPLACE_PUBLIC_KEY.
 */
final class HostingAttestation {

	/**
	 * First line of every attestation's checksums.
	 *
	 * @var string
	 */
	public const HEADER = '# smliser-attestation-v1';

	/**
	 * Environment variable holding the base64 Ed25519 secret key.
	 *
	 * @var string
	 */
	public const ENV_KEY = 'SMLISER_ATTESTATION_SIGNING_KEY';

	/**
	 * Public key of the official marketplace server, pinned by installations
	 * to verify the themes and extensions they install from it.
	 *
	 * @var string
	 */
	public const MARKETPLACE_PUBLIC_KEY = 'rs9am8LBRxNSqxIxhDo40OyFucyjxxER2NFpTwfhtvY=';

	/**
	 * No signing key is set: packages are served unsigned.
	 *
	 * @var string
	 */
	public const KEY_MISSING = 'missing';

	/**
	 * A signing key is set but is not a base64 Ed25519 secret key.
	 *
	 * @var string
	 */
	public const KEY_INVALID = 'invalid';

	/**
	 * A valid signing key is set: packages are signed.
	 *
	 * @var string
	 */
	public const KEY_CONFIGURED = 'configured';

	/**
	 * Constructor.
	 *
	 * @param FileSystem $fs Filesystem API.
	 */
	public function __construct(
		private FileSystem $fs
	) {}

	/**
	 * Whether a valid signing key is configured.
	 *
	 * @return bool
	 */
	public function configured() : bool {
		return null !== $this->secret_key();
	}

	/**
	 * The state of the signing key.
	 *
	 * @return string KEY_MISSING, KEY_INVALID or KEY_CONFIGURED.
	 */
	public function key_status() : string {
		if ( '' === self::raw_key() ) {
			return self::KEY_MISSING;
		}

		return null === $this->secret_key() ? self::KEY_INVALID : self::KEY_CONFIGURED;
	}

	/**
	 * The configured key's public half, for app owners to pin in their apps.
	 *
	 * @return string|null Base64 Ed25519 public key; null when no valid key is configured.
	 */
	public function public_key() : ?string {
		$key = $this->secret_key();

		return null === $key ? null : base64_encode( sodium_crypto_sign_publickey_from_secretkey( $key ) );
	}

	/**
	 * The configured key's fingerprint, for checking a copied key.
	 *
	 * @return string|null Null when no valid key is configured.
	 */
	public function fingerprint() : ?string {
		$public_key = $this->public_key();

		return null === $public_key ? null : self::fingerprint_of( $public_key );
	}

	/**
	 * The fingerprint of a public key: "SHA256:" and the unpadded base64
	 * SHA-256 of the raw key, the form OpenSSH uses for its keys.
	 *
	 * Short enough to read out or compare by eye against a copy published
	 * somewhere other than this site.
	 *
	 * @param string $public_key Base64 Ed25519 public key.
	 * @return string|null Null when it is not a base64 Ed25519 public key.
	 */
	public static function fingerprint_of( string $public_key ) : ?string {
		$raw = base64_decode( trim( $public_key ), true );

		if ( false === $raw || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $raw ) ) {
			return null;
		}

		return 'SHA256:' . rtrim( base64_encode( hash( 'sha256', $raw, true ) ), '=' );
	}

	/**
	 * Fail unless a valid signing key is configured.
	 *
	 * @return void
	 * @throws RuntimeException When no valid key is configured.
	 */
	public function assert_configured() : void {
		if ( null === $this->secret_key() ) {
			throw new RuntimeException( sprintf( 'Hosting attestation is not configured. Set %s to a base64 Ed25519 secret key.', self::ENV_KEY ) );
		}
	}

	/**
	 * Attest a main package before it is stored.
	 *
	 * @param string $type       App type.
	 * @param string $slug       App slug.
	 * @param string $local_path Absolute path of the package's local file.
	 * @return array{checksums: string, signature: string} The checksums and their base64 signature.
	 * @throws RuntimeException When no valid key is configured or the file cannot be read.
	 */
	public function attest( string $type, string $slug, string $local_path ) : array {
		$this->assert_configured();

		// Hashed in streaming mode, so a large package is never loaded into memory.
		$sha256 = $this->fs->is_file( $local_path ) ? hash_file( 'sha256', $local_path ) : false;

		if ( false === $sha256 ) {
			throw new RuntimeException( sprintf( 'The package of "%s" could not be read for attestation.', $slug ) );
		}

		$checksums = self::checksums( $type, $slug, $sha256 );

		return [
			'checksums' => $checksums,
			'signature' => base64_encode( sodium_crypto_sign_detached( $checksums, (string) $this->secret_key() ) ),
		];
	}

	/**
	 * The checksums text attesting a main package.
	 *
	 * @param string $type   App type.
	 * @param string $slug   App slug.
	 * @param string $sha256 The package's SHA-256, lowercase hex.
	 * @return string
	 */
	public static function checksums( string $type, string $slug, string $sha256 ) : string {
		$artifact = $slug . '.zip';

		return sprintf(
			"%s\n# type: %s\n# slug: %s\n# artifact: %s\n%s  %s\n",
			self::HEADER,
			$type,
			$slug,
			$artifact,
			strtolower( $sha256 ),
			$artifact
		);
	}

	/**
	 * Read the identity an attestation's checksums name.
	 *
	 * @param string $checksums Checksums text.
	 * @return array{type: string, slug: string, artifact: string}|null Null when it is not an attestation.
	 */
	public static function identity( string $checksums ) : ?array {
		$lines = preg_split( '/\R/', $checksums ) ?: [];

		if ( self::HEADER !== trim( (string) ( $lines[0] ?? '' ) ) ) {
			return null;
		}

		$identity = [];

		foreach ( $lines as $line ) {
			if ( 1 === preg_match( '/^# (type|slug|artifact): (.+)$/', trim( $line ), $match ) ) {
				$identity[ $match[1] ] = $match[2];
			}
		}

		if ( ! isset( $identity['type'], $identity['slug'], $identity['artifact'] ) ) {
			return null;
		}

		return [ 'type' => $identity['type'], 'slug' => $identity['slug'], 'artifact' => $identity['artifact'] ];
	}

	/**
	 * The configured secret key, decoded.
	 *
	 * @return string|null Null when it is missing or not an Ed25519 secret key.
	 */
	private function secret_key() : ?string {
		$encoded = self::raw_key();
		$key     = '' === $encoded ? false : base64_decode( $encoded, true );

		return false === $key || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $key ) ? null : $key;
	}

	/**
	 * The signing key's value as set in the environment, trimmed.
	 *
	 * @return string Empty when it is not set.
	 */
	private static function raw_key() : string {
		$encoded = $_ENV[ self::ENV_KEY ] ?? getenv( self::ENV_KEY );

		return is_string( $encoded ) ? trim( $encoded ) : '';
	}
}