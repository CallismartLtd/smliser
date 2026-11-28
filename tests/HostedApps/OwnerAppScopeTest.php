<?php
/**
 * Authorization rules of the client dashboard's apps (OwnerAppScope).
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\HostedApps;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmartLicenseServer\HostedApps\OwnerAppScope;
use SmartLicenseServer\HostedApps\WPPlugin;
use SmartLicenseServer\HostedApps\WPTheme;
use SmartLicenseServer\Security\Actors\ActorInterface;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\Capability;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

final class OwnerAppScopeTest extends TestCase {

	private const OWNER_ID = 5;

	/**
	 * A scope for a principal with these capabilities, acting for an owner.
	 *
	 * @param list<string> $capabilities
	 * @param int|null     $owner_id Null: acting for no owner.
	 * @param string       $owner_type
	 * @param string       $role_slug
	 */
	private function scope( array $capabilities, ?int $owner_id = self::OWNER_ID, string $owner_type = Owner::TYPE_INDIVIDUAL, string $role_slug = 'custom' ) : OwnerAppScope {
		$role  = ( new Role() )->set_slug( $role_slug )->set_capabilities( $capabilities );
		$owner = null === $owner_id ? null : ( new Owner() )->set_id( $owner_id )->set_type( $owner_type );
		$guard = new Guard();
		$guard->set_principal( new Principal( $this->createStub( ActorInterface::class ), $role, $owner ) );

		return new OwnerAppScope( $guard );
	}

	private function app( int $owner_id, int $id = 3 ) : WPPlugin {
		return ( new WPPlugin() )->set_id( $id )->set_owner_id( $owner_id );
	}

	public function test_every_action_names_known_capabilities() : void {
		foreach ( OwnerAppScope::ACTIONS as $action => [ $mode, $capabilities ] ) {
			$this->assertContains( $mode, [ 'any', 'all' ], $action );
			$this->assertNotEmpty( $capabilities, $action );

			foreach ( $capabilities as $capability ) {
				$this->assertTrue( Capability::exists( $capability ), "{$action}: {$capability}" );
			}
		}
	}

	public function test_the_owner_sees_their_own_app() : void {
		$this->assertTrue( $this->scope( [ 'hosted_apps.update' ] )->allows( $this->app( self::OWNER_ID ) ) );
	}

	public function test_another_owners_app_is_out_of_scope() : void {
		$scope = $this->scope( DefaultRoles::get( 'resource_owner' )['capabilities'] );

		$this->assertFalse( $scope->allows( $this->app( 6 ) ) );
		$this->assertFalse( $scope->allows( ( new WPTheme() )->set_id( 9 )->set_owner_id( 6 ) ) );
	}

	public function test_a_platform_app_is_out_of_scope() : void {
		$this->assertFalse( $this->scope( [ 'hosted_apps.update' ] )->allows( $this->app( 0 ) ) );
	}

	public function test_a_system_administrator_gets_no_other_owners_apps() : void {
		$scope = $this->scope( [], 1, Owner::TYPE_INDIVIDUAL, 'system_admin' );

		$this->assertTrue( $scope->can( 'delete' ) );
		$this->assertFalse( $scope->allows( $this->app( self::OWNER_ID ) ) );
		$this->assertTrue( $scope->allows( $this->app( 1 ) ) );
	}

	public function test_system_admin_held_in_an_organization_grants_nothing() : void {
		$scope = $this->scope( [], self::OWNER_ID, Owner::TYPE_ORGANIZATION, 'system_admin' );

		$this->assertFalse( $scope->can( 'view' ) );
		$this->assertFalse( $scope->allows( $this->app( self::OWNER_ID ) ) );
	}

	public function test_nothing_without_an_owner() : void {
		$scope = $this->scope( DefaultRoles::get( 'resource_owner' )['capabilities'], null );

		$this->assertNull( $scope->owner_id() );
		$this->assertFalse( $scope->can( 'view' ) );
		$this->assertFalse( $scope->can( 'create' ) );
		$this->assertFalse( $scope->allows( $this->app( self::OWNER_ID ) ) );
	}

	public function test_nothing_without_a_principal() : void {
		$scope = new OwnerAppScope( new Guard() );

		$this->assertFalse( $scope->can( 'view' ) );
		$this->assertFalse( $scope->allows( $this->app( self::OWNER_ID ) ) );
	}

	public function test_an_unsaved_app_is_out_of_scope() : void {
		$this->assertFalse( $this->scope( [ 'hosted_apps.update' ] )->allows( ( new WPPlugin() )->set_owner_id( self::OWNER_ID ) ) );
	}

	public function test_a_role_without_app_capabilities_sees_no_apps() : void {
		$scope = $this->scope( [ 'monetization.license.issue' ] );

		$this->assertFalse( $scope->can( 'view' ) );
		$this->assertFalse( $scope->allows( $this->app( self::OWNER_ID ) ) );
	}

	public function test_an_unknown_action_is_refused() : void {
		$this->assertFalse( $this->scope( [], 1, Owner::TYPE_INDIVIDUAL, 'system_admin' )->can( 'rename_slug' ) );
	}

	/**
	 * @return iterable<string, array{0: string, 1: list<string>, 2: bool}>
	 */
	public static function actions() : iterable {
		yield 'update needs update'                 => [ 'update', [ 'hosted_apps.upload_assets' ], false ];
		yield 'update with update'                  => [ 'update', [ 'hosted_apps.update' ], true ];
		yield 'trash needs delete too'              => [ 'trash', [ 'hosted_apps.change_status' ], false ];
		yield 'trash with both'                     => [ 'trash', [ 'hosted_apps.change_status', 'hosted_apps.delete' ], true ];
		yield 'delete needs delete'                 => [ 'delete', [ 'hosted_apps.change_status' ], false ];
		yield 'editing artifacts needs edit_assets' => [ 'edit_artifacts', [ 'hosted_apps.upload_assets' ], false ];
		yield 'deleting artifacts with edit_assets' => [ 'delete_artifacts', [ 'hosted_apps.edit_assets' ], true ];
		yield 'download with access_files'          => [ 'download', [ 'hosted_apps.access_files' ], true ];
		yield 'download with repository.download'   => [ 'download', [ 'repository.download' ], true ];
		yield 'download needs a download cap'       => [ 'download', [ 'hosted_apps.update' ], false ];
		yield 'stats need analytics.view'           => [ 'view_stats', [ 'hosted_apps.update' ], false ];
		yield 'analysts see stats'                  => [ 'view_stats', [ 'analytics.view' ], true ];
		yield 'analysts see the apps'               => [ 'view', [ 'analytics.view' ], true ];
		yield 'analysts change nothing'             => [ 'change_status', [ 'analytics.view' ], false ];
	}

	/**
	 * @param list<string> $capabilities
	 */
	#[DataProvider( 'actions' )]
	public function test_each_action_needs_its_capabilities( string $action, array $capabilities, bool $allowed ) : void {
		$this->assertSame( $allowed, $this->scope( $capabilities )->can( $action ) );
	}

	public function test_app_manager_role_matches_its_capabilities() : void {
		$scope = $this->scope( DefaultRoles::get( 'app_manager' )['capabilities'], self::OWNER_ID, Owner::TYPE_ORGANIZATION, 'app_manager' );

		$this->assertTrue( $scope->can( 'update' ) );
		$this->assertTrue( $scope->can( 'upload_artifacts' ) );
		$this->assertFalse( $scope->can( 'delete' ) );
		$this->assertFalse( $scope->can( 'trash' ) );
		$this->assertFalse( $scope->can( 'delete_artifacts' ) );
		$this->assertFalse( $scope->can( 'download' ) );
	}
}
