<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\Accounts;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Accounts\AccountDeletion;
use SmartLicenseServer\Security\Actors\User;

/**
 * Self-service deletion is refused while the user still hosts apps, runs an
 * organization alone, or is the last system administrator.
 */
final class AccountDeletionTest extends TestCase {

	public function test_nothing_in_the_way_allows_deletion() : void {
		$this->assertNull( AccountDeletion::refusal_for( 0, [], false ) );
	}

	public function test_owned_apps_refuse() : void {
		$refusal = AccountDeletion::refusal_for( 3, [], false );

		$this->assertInstanceOf( Exception::class, $refusal );
		$this->assertSame( 'account_deletion_refused', $refusal->get_error_code() );
		$this->assertSame( 409, $refusal->get_error_data()['status'] );
		$this->assertCount( 1, $refusal->get_error_data()['reasons'] );
		$this->assertStringContainsString( '3 apps', $refusal->get_error_message() );
	}

	public function test_each_sole_admin_organization_is_named() : void {
		$refusal = AccountDeletion::refusal_for( 0, [ 'Acme', 'Globex' ], false );
		$reasons = $refusal->get_error_data()['reasons'];

		$this->assertCount( 2, $reasons );
		$this->assertStringContainsString( 'Acme', $reasons[0] );
		$this->assertStringContainsString( 'Globex', $reasons[1] );
	}

	public function test_all_reasons_are_given_at_once() : void {
		$refusal = AccountDeletion::refusal_for( 1, [ 'Acme' ], true );

		$this->assertCount( 3, $refusal->get_error_data()['reasons'] );
		$this->assertStringContainsString( '1 app,', $refusal->get_error_message() );
	}

	public function test_delete_refuses_before_touching_anything() : void {
		$deletion = ( new \ReflectionClass( BlockedDeletion::class ) )->newInstanceWithoutConstructor();
		$user     = ( new User() )->set_id( 5 )->set_email( 'jane@example.com' );

		$this->assertNotNull( $deletion->refusal( $user ) );

		try {
			// No database is configured: reaching it would fail differently.
			$deletion->delete( $user );
			$this->fail( 'The deletion should have been refused.' );
		} catch ( Exception $e ) {
			$this->assertSame( 'account_deletion_refused', $e->get_error_code() );
		}
	}
}

/**
 * A user who still runs an organization alone.
 */
class BlockedDeletion extends AccountDeletion {
	protected function owned_app_count( User $user ) : int {
		return 0;
	}
	protected function sole_admin_organizations( User $user ) : array {
		return [ 'Acme' ];
	}
	protected function is_last_system_admin( User $user ) : bool {
		return false;
	}
}
