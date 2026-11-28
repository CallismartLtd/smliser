<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\Authorization;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\Software;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;
use SmartLicenseServer\Security\SecurityAwareTrait;

/**
 * SecurityAwareTrait::check_record_app_ownership(): a record (license,
 * monetization, app files) whose app does not resolve is a system
 * administrator's only; otherwise the app's owner decides.
 */
final class RecordAppOwnershipTest extends TestCase {

	public function test_owner_passes() : void {
		$this->assertNull( $this->check( $this->principal( 'resource_owner', 7 ), $this->app( 7 ) ) );
	}

	public function test_other_owner_is_refused() : void {
		$this->assertSame( 'unuathorized_app_access', $this->check( $this->principal( 'resource_owner', 8 ), $this->app( 7 ) ) );
	}

	public function test_missing_app_is_not_found_for_non_admin() : void {
		$this->assertSame( 'resource_not_found', $this->check( $this->principal( 'resource_owner', 7 ), null ) );
		$this->assertSame( 'resource_not_found', $this->check( $this->principal( 'resource_owner', 7 ), new Software() ), 'an app that does not exist counts as missing' );
	}

	public function test_missing_app_is_allowed_for_system_admin() : void {
		$this->assertNull( $this->check( $this->principal( 'system_admin', 1 ), null ) );
	}

	public function test_no_principal_is_refused() : void {
		$this->assertSame( 'missing_auth', $this->check( null, null ) );
	}

	/**
	 * @return string|null The error code, or null when allowed.
	 */
	private function check( ?Principal $principal, ?Software $app ) : ?string {
		$guard = new Guard();

		if ( $principal ) {
			$guard->set_principal( $principal );
		}

		$subject = new class( $guard ) {
			use SecurityAwareTrait;

			public function __construct( Guard $guard ) {
				$this->guard = $guard;
			}

			public function run( ?Software $app ) : void {
				$this->check_record_app_ownership( $app );
			}
		};

		try {
			$subject->run( $app );
			return null;
		} catch ( RequestException $e ) {
			return (string) $e->get_error_code();
		}
	}

	private function principal( string $role, int $owner_id ) : Principal {
		$owner = ( new Owner() )->set_id( $owner_id )->set_type( Owner::TYPE_INDIVIDUAL );
		$roles = ( new Role() )->set_slug( $role )->set_capabilities( DefaultRoles::get( $role )['capabilities'] );

		return new Principal( new User(), $roles, $owner );
	}

	private function app( int $owner_id ) : Software {
		$app = new Software();
		$app->set_id( 11 );
		$app->set_owner_id( $owner_id );

		return $app;
	}
}
