<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\Accounts;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\Security\Accounts\AccountDataExport;
use SmartLicenseServer\Security\Actors\User;

/**
 * The data export never carries a secret, wherever it sits in the document.
 */
final class AccountDataExportTest extends TestCase {

	public function test_redact_drops_secrets_at_any_depth() : void {
		$clean = AccountDataExport::redact( [
			'email'         => 'jane@example.com',
			'password_hash' => '$argon2id$...',
			'Password'      => 'hunter2',
			'license_key'   => 'ABCD-EFGH',
			'settings'      => [ 'theme' => 'dark', 'password_reset_key' => 'k', 'csrf_token' => 't' ],
			'security'      => [ 'totp_secret' => 'JBSWY3DP', 'recovery_codes_remaining' => 8, 'recovery_codes' => [ 'a', 'b' ] ],
			'passkeys'      => [ [ 'label' => 'Laptop', 'credential_data' => '{}', 'credential_id_hash' => 'h', 'public_key' => 'pk' ] ],
			'sites'         => [ [ 'host' => 'example.com', 'secret' => 's', 'secret_hash' => 'sh' ] ],
			'downloads'     => [ 'download_token' => 'dt', 'api_key' => 'ak', 'nonce' => 'n' ],
			'receipt_file'  => '/srv/uploads/receipts/abc.pdf',
		] );

		$this->assertSame(
			[
				'email'       => 'jane@example.com',
				'license_key' => 'ABCD-EFGH',
				'settings'    => [ 'theme' => 'dark' ],
				'security'    => [ 'recovery_codes_remaining' => 8 ],
				'passkeys'    => [ [ 'label' => 'Laptop' ] ],
				'sites'       => [ [ 'host' => 'example.com' ] ],
				'downloads'   => [],
			],
			$clean
		);
	}

	public function test_built_document_is_redacted_even_when_a_part_leaks_a_secret() : void {
		$export = ( new \ReflectionClass( LeakyExport::class ) )->newInstanceWithoutConstructor();
		$user   = ( new User() )->set_id( 7 )->set_email( 'jane@example.com' );
		$json   = $export->to_json( $user );

		$this->assertStringContainsString( 'ABCD-EFGH', $json, 'The license key is the user\'s own data.' );

		foreach ( [ 'hunter2', 'JBSWY3DP', 'site-secret', 'tok-123', 'csrf-1', 'reset-k', 'cred-data' ] as $secret ) {
			$this->assertStringNotContainsString( $secret, $json, $secret );
		}

		$document = json_decode( $json, true );
		$this->assertSame( AccountDataExport::FORMAT, $document['format'] );
		$this->assertSame( 'Jane', $document['profile']['display_name'] );
	}

	public function test_filename_names_the_user_and_day() : void {
		$user = ( new User() )->set_id( 42 );

		$this->assertMatchesRegularExpression( '/^account-data-42-\d{4}-\d{2}-\d{2}\.json$/', AccountDataExport::filename( $user ) );
	}
}

/**
 * An export whose parts carry secrets, to prove build() strips them.
 */
class LeakyExport extends AccountDataExport {
	protected function profile( User $user ) : array {
		return [ 'id' => $user->get_id(), 'display_name' => 'Jane', 'password_hash' => 'hunter2', 'password' => 'hunter2' ];
	}
	protected function settings( User $user ) : array {
		return [ 'theme' => 'dark', 'password_reset_key' => 'reset-k' ];
	}
	protected function email_notifications( User $user ) : array {
		return [ 'receive' => [], 'turned_off' => [] ];
	}
	protected function security( User $user ) : array {
		return [ 'totp_secret' => 'JBSWY3DP', 'passkeys' => [ [ 'label' => 'Key', 'credential_data' => 'cred-data' ] ] ];
	}
	protected function licenses( User $user ) : array {
		return [ [ 'license_key' => 'ABCD-EFGH', 'sites' => [ [ 'host' => 'a.test', 'secret' => 'site-secret' ] ], 'download_token' => 'tok-123' ] ];
	}
	protected function purchases( User $user ) : array {
		return [ [ 'id' => 1, 'csrf_token' => 'csrf-1' ] ];
	}
	protected function organizations( User $user ) : array {
		return [];
	}
	protected function apps( User $user ) : array {
		return [];
	}
}
