<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\Authorization;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\ClientDashboard\AppOwnerScope;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\Software;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\LicenseAppAccess;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Who may manage the licenses issued for an app: its owner (acting for
 * it, with the capability), never anyone else; in the admin dashboard, a
 * system administrator too. Runs without a database: the license, its
 * app and the principal are built in memory.
 */
final class AppLicenseAuthorizationTest extends TestCase {

	private const OWNER_ID = 7;

	public function test_owner_may_free_sites_and_change_status() : void {
		$scope   = $this->scope( $this->principal( 'resource_owner', Owner::TYPE_INDIVIDUAL, self::OWNER_ID ) );
		$license = $this->license();

		$this->assertSame( $license, $scope->authorize( $license, $this->app( self::OWNER_ID ), AppOwnerScope::CAP_FREE_SITE ) );
		$this->assertSame( $license, $scope->authorize( $license, $this->app( self::OWNER_ID ), AppOwnerScope::CAP_CHANGE_STATUS ) );
		$this->assertSame( $license, $scope->authorize( $license, $this->app( self::OWNER_ID ) ) );
		$this->assertTrue( $scope->can_view_licenses() );
	}

	public function test_organization_admin_acting_for_owner_is_allowed() : void {
		$scope = $this->scope( $this->principal( 'organization_admin', Owner::TYPE_ORGANIZATION, self::OWNER_ID ) );

		$this->assertInstanceOf( License::class, $scope->authorize( $this->license(), $this->app( self::OWNER_ID ), AppOwnerScope::CAP_FREE_SITE ) );
	}

	public function test_non_owner_gets_not_found() : void {
		// A user acting for themselves (owner 99) with every license capability.
		$scope = $this->scope( $this->principal( 'resource_owner', Owner::TYPE_INDIVIDUAL, 99 ) );

		$this->assertStatus( 404, $scope->authorize( $this->license(), $this->app( self::OWNER_ID ), AppOwnerScope::CAP_FREE_SITE ) );
		$this->assertStatus( 404, $scope->authorize( $this->license(), $this->app( self::OWNER_ID ) ) );
	}

	public function test_owner_of_a_different_app_gets_not_found() : void {
		// Owner 7 owns an app, but this license's app is owner 8's.
		$scope = $this->scope( $this->principal( 'resource_owner', Owner::TYPE_INDIVIDUAL, self::OWNER_ID ) );

		$this->assertTrue( $scope->owns( $this->app( self::OWNER_ID ) ) );
		$this->assertStatus( 404, $scope->authorize( $this->license(), $this->app( 8 ), AppOwnerScope::CAP_FREE_SITE ) );
		$this->assertStatus( 404, $scope->authorize( $this->license(), $this->app( 8 ), AppOwnerScope::CAP_CHANGE_STATUS ) );
	}

	public function test_organization_member_without_the_capability_is_denied() : void {
		// An analyst of the owning organization: sees nothing, may do nothing.
		$scope = $this->scope( $this->principal( 'analyst', Owner::TYPE_ORGANIZATION, self::OWNER_ID ) );

		$this->assertFalse( $scope->can_view_licenses() );
		$this->assertStatus( 403, $scope->authorize( $this->license(), $this->app( self::OWNER_ID ), AppOwnerScope::CAP_FREE_SITE ) );
		$this->assertStatus( 403, $scope->authorize( $this->license(), $this->app( self::OWNER_ID ), AppOwnerScope::CAP_CHANGE_STATUS ) );
		$this->assertStatus( 403, $scope->authorize( $this->license(), $this->app( self::OWNER_ID ) ) );
	}

	public function test_app_manager_cannot_free_sites() : void {
		$scope = $this->scope( $this->principal( 'app_manager', Owner::TYPE_ORGANIZATION, self::OWNER_ID ) );

		$this->assertStatus( 403, $scope->authorize( $this->license(), $this->app( self::OWNER_ID ), AppOwnerScope::CAP_FREE_SITE ) );
	}

	public function test_license_with_missing_app_is_not_found_in_client_dashboard() : void {
		$scope = $this->scope( $this->principal( 'resource_owner', Owner::TYPE_INDIVIDUAL, self::OWNER_ID ) );

		$this->assertStatus( 404, $scope->authorize( $this->license(), null, AppOwnerScope::CAP_FREE_SITE ) );
		$this->assertStatus( 404, $scope->authorize( null, $this->app( self::OWNER_ID ), AppOwnerScope::CAP_FREE_SITE ) );
		// An unsaved app (no ID) is not an app.
		$this->assertStatus( 404, $scope->authorize( $this->license(), ( new Software() )->set_owner_id( self::OWNER_ID ), AppOwnerScope::CAP_FREE_SITE ) );
	}

	public function test_no_principal_or_owner_gets_not_found() : void {
		$this->assertStatus( 404, ( new AppOwnerScope( new Guard() ) )->authorize( $this->license(), $this->app( self::OWNER_ID ), AppOwnerScope::CAP_FREE_SITE ) );
		$this->assertStatus( 404, $this->scope( new Principal( new User(), $this->role( 'resource_owner' ), null ) )->authorize( $this->license(), $this->app( self::OWNER_ID ), AppOwnerScope::CAP_FREE_SITE ) );
	}

	public function test_admin_path_license_with_missing_app_denies_non_admin() : void {
		$owner = $this->principal( 'resource_owner', Owner::TYPE_INDIVIDUAL, self::OWNER_ID );
		$admin = $this->principal( 'system_admin', Owner::TYPE_INDIVIDUAL, 1 );

		$this->assertStatus( 404, LicenseAppAccess::check( $owner, null ) );
		$this->assertTrue( LicenseAppAccess::check( $admin, null ) );
	}

	public function test_admin_path_ownership() : void {
		$owner = $this->principal( 'resource_owner', Owner::TYPE_INDIVIDUAL, self::OWNER_ID );
		$other = $this->principal( 'resource_owner', Owner::TYPE_INDIVIDUAL, 99 );
		$admin = $this->principal( 'system_admin', Owner::TYPE_INDIVIDUAL, 1 );

		$this->assertTrue( LicenseAppAccess::check( $owner, $this->app( self::OWNER_ID ) ) );
		$this->assertTrue( LicenseAppAccess::check( $admin, $this->app( self::OWNER_ID ) ) );
		$this->assertInstanceOf( RequestException::class, LicenseAppAccess::check( $other, $this->app( self::OWNER_ID ) ) );
		$this->assertInstanceOf( RequestException::class, LicenseAppAccess::check( null, $this->app( self::OWNER_ID ) ) );
	}

	public function test_system_admin_role_inside_organization_grants_nothing() : void {
		$void = $this->principal( 'system_admin', Owner::TYPE_ORGANIZATION, 99 );

		$this->assertStatus( 404, LicenseAppAccess::check( $void, null ) );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	private function scope( Principal $principal ) : AppOwnerScope {
		$guard = new Guard();
		$guard->set_principal( $principal );

		return new AppOwnerScope( $guard );
	}

	private function role( string $slug ) : Role {
		return ( new Role() )->set_slug( $slug )->set_capabilities( DefaultRoles::get( $slug )['capabilities'] );
	}

	private function principal( string $role, string $owner_type, int $owner_id ) : Principal {
		$owner = ( new Owner() )->set_id( $owner_id )->set_type( $owner_type );

		return new Principal( new User(), $this->role( $role ), $owner );
	}

	private function app( int $owner_id ) : Software {
		$app = new Software();
		$app->set_id( 11 );
		$app->set_owner_id( $owner_id );

		return $app;
	}

	private function license() : License {
		return ( new License() )->set_id( 5 );
	}

	private function assertStatus( int $status, mixed $result ) : void {
		$this->assertInstanceOf( RequestException::class, $result );
		$data = $result->get_error_data();
		$this->assertSame( $status, (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 ) );
	}
}
