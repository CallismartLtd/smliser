<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\ClientDashboard;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SmartLicenseServer\ClientDashboard\ActionHandlers\LicenseActions;
use SmartLicenseServer\ClientDashboard\LicenseHolderPolicy;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Actors\User;

/**
 * What a license holder may do in the client dashboard.
 *
 * Product rule: a license holder never frees an activated site and never
 * reactivates a license; only the app's owner and administrators can.
 */
final class LicenseHolderPolicyTest extends TestCase {

	private function user( int $id, bool $verified = true ) : User {
		return ( new User() )
			->set_id( $id )
			->set_email_verified_at( $verified ? new DateTimeImmutable( '2026-01-01 00:00:00' ) : null );
	}

	private function license( int $id, int $user_id, string $status = License::STATUS_ACTIVE ) : License {
		return ( new License() )->set_id( $id )->set_user_id( $user_id )->set_status( $status );
	}

	/**
	 * A loader standing in for License::get_by_id().
	 *
	 * @param License ...$licenses
	 * @return callable(int): ?License
	 */
	private function loader( License ...$licenses ) : callable {
		$by_id = [];

		foreach ( $licenses as $license ) {
			$by_id[ (int) $license->get_id() ] = $license;
		}

		return static fn ( int $id ) : ?License => $by_id[ $id ] ?? null;
	}

	public function test_holder_finds_own_license() : void {
		$license = $this->license( 10, 7 );

		$this->assertSame( $license, LicenseHolderPolicy::find( 10, $this->user( 7 ), $this->loader( $license ) ) );
	}

	public function test_another_users_license_is_not_found() : void {
		$loader = $this->loader( $this->license( 10, 8 ) );

		// Not found, exactly like a license that does not exist (the page answers 404).
		$this->assertNull( LicenseHolderPolicy::find( 10, $this->user( 7 ), $loader ) );
		$this->assertNull( LicenseHolderPolicy::find( 99, $this->user( 7 ), $loader ) );
	}

	public function test_unlinked_license_is_not_found() : void {
		$this->assertNull( LicenseHolderPolicy::find( 10, $this->user( 7 ), $this->loader( $this->license( 10, 0 ) ) ) );
	}

	public function test_unverified_email_sees_nothing() : void {
		$license = $this->license( 10, 7 );

		$this->assertFalse( LicenseHolderPolicy::owns( $license, $this->user( 7, false ) ) );
		$this->assertNull( LicenseHolderPolicy::find( 10, $this->user( 7, false ), $this->loader( $license ) ) );
	}

	public function test_guest_sees_nothing() : void {
		$license = $this->license( 10, 7 );

		$this->assertFalse( LicenseHolderPolicy::owns( $license, null ) );
		$this->assertNull( LicenseHolderPolicy::find( 10, null, $this->loader( $license ) ) );
		$this->assertFalse( LicenseHolderPolicy::can_deactivate( $license, null ) );
	}

	public function test_holder_can_deactivate_a_working_license_only() : void {
		$user = $this->user( 7 );

		foreach ( [ License::STATUS_ACTIVE, License::STATUS_LIFETIME, License::STATUS_PENDING, License::STATUS_INACTIVE ] as $status ) {
			$this->assertTrue( LicenseHolderPolicy::can_deactivate( $this->license( 10, 7, $status ), $user ), $status );
		}

		foreach ( [ License::STATUS_DEACTIVATED, License::STATUS_EXPIRED, License::STATUS_REVOKED, License::STATUS_SUSPENDED ] as $status ) {
			$this->assertFalse( LicenseHolderPolicy::can_deactivate( $this->license( 10, 7, $status ), $user ), $status );
		}

		$this->assertFalse( LicenseHolderPolicy::can_deactivate( $this->license( 10, 8 ), $user ) );
	}

	public function test_holder_cannot_reactivate() : void {
		$user = $this->user( 7 );

		foreach ( License::get_allowed_statuses() as $status ) {
			$this->assertFalse( LicenseHolderPolicy::can_reactivate( $this->license( 10, 7, $status ), $user ), $status );
		}
	}

	public function test_holder_cannot_free_sites() : void {
		$user = $this->user( 7 );

		foreach ( License::get_allowed_statuses() as $status ) {
			$this->assertFalse( LicenseHolderPolicy::can_release_sites( $this->license( 10, 7, $status ), $user ), $status );
		}
	}

	public function test_license_actions_offer_deactivation_only() : void {
		$methods = array_map(
			static fn ( \ReflectionMethod $method ) : string => $method->getName(),
			( new \ReflectionClass( LicenseActions::class ) )->getMethods( \ReflectionMethod::IS_PUBLIC )
		);

		sort( $methods );

		$this->assertSame( [ '__construct', 'deactivate' ], $methods );
	}

	public function test_no_client_route_frees_sites_or_reactivates() : void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Environments/Application/Web/HttpDispatcher.php' );
		$start  = strpos( $source, 'CLIENT DASHBOARD ACTIONS' );

		$this->assertNotFalse( $start );

		$end   = strpos( $source, 'Uploads directory serving', (int) $start );
		$group = substr( $source, (int) $start, false === $end ? null : $end - (int) $start );

		preg_match_all( "/(?:pattern:\s*|post\(\s*)'([^']+)'/", $group, $matches );

		$license_routes = array_values( array_filter( $matches[1], static fn ( string $route ) : bool => str_starts_with( $route, 'licenses' ) ) );

		$this->assertSame( [ 'licenses/deactivate' ], $license_routes );
		$this->assertSame( 0, preg_match( '/uninstall|remove|reset|reactivat|transfer|release/i', implode( ' ', $license_routes ) ) );
		$this->assertStringNotContainsString( 'remove_activated_domain', (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/ClientDashboard/ActionHandlers/LicenseActions.php' ) );
	}

	public function test_every_status_has_a_label() : void {
		$labels = License::get_status_labels();

		$this->assertSame( License::get_allowed_statuses(), array_keys( $labels ) );
		$this->assertSame( 'Deactivated', License::get_status_label( 'DEACTIVATED' ) );
		$this->assertSame( 'custom', License::get_status_label( 'custom' ) );
	}
}
