<?php
/**
 * Grant license site removal migration class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Schema\Migrations
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Migrations;

use Callismart\DBPrism\Migrations\AbstractMigration;
use Callismart\DBPrism\Migrations\Contracts\ReversibleMigrationInterface;
use SmartLicenseServer\Schema\TableName;

/**
 * Lets app owners free the sites a license of their app is activated on.
 *
 * The default roles that manage licenses (resource_owner,
 * organization_admin, resource_admin, license_manager) get
 * monetization.license.uninstall_domain, as DefaultRoles now defines them;
 * before, only a system administrator held it. A role that already has it
 * is left alone. down() takes it away only from the roles this migration
 * gave it to.
 */
final class GrantLicenseSiteRemoval extends AbstractMigration implements ReversibleMigrationInterface {

	/**
	 * The capability granted.
	 */
	private const CAPABILITY = 'monetization.license.uninstall_domain';

	/**
	 * The roles that get it.
	 */
	private const ROLES = [ 'resource_owner', 'organization_admin', 'resource_admin', 'license_manager' ];

	public static function get_description() : string {
		return smliser__( 'Let app owners free the sites a license of their app is activated on' );
	}

	public function up() : void {
		$snapshots = new MigrationSnapshotStore( $this->database() );

		foreach ( $this->role_capabilities() as $row ) {
			$capabilities = $this->decode( $row['capabilities'] );

			if ( in_array( self::CAPABILITY, $capabilities, true ) ) {
				continue;
			}

			$snapshots->capture( self::class, 'role:' . $row['slug'],
				[ 'caps_id' => (int) $row['caps_id'], 'granted' => false ],
				[ 'caps_id' => (int) $row['caps_id'], 'granted' => true ]
			);

			$capabilities[] = self::CAPABILITY;
			$this->save( (int) $row['caps_id'], $capabilities );
		}

		$snapshots->complete( self::class );
	}

	public function down() : void {
		$snapshots = new MigrationSnapshotStore( $this->database() );

		foreach ( $snapshots->rows( self::class ) as $snapshot ) {
			$caps_id = (int) ( $snapshot['before']['caps_id'] ?? 0 );

			if ( $caps_id < 1 ) {
				continue;
			}

			$select = $this->query()->select( 'capabilities' )->from( TableName::ROLE_CAPS->table() )->where( 'id', '=', $caps_id )->limit( 1 );
			$row    = $this->database()->get_row( $select->build(), $select->get_bindings() );

			if ( ! $row ) {
				continue;
			}

			$capabilities = array_values( array_diff( $this->decode( $row['capabilities'] ?? '' ), [ self::CAPABILITY ] ) );
			$this->save( $caps_id, $capabilities );
		}

		$snapshots->clear( self::class );
	}

	/**
	 * The capability rows of the roles that get the capability.
	 *
	 * @return list<array{caps_id: int|string, slug: string, capabilities: string|null}>
	 */
	private function role_capabilities() : array {
		$roles  = $this->query()->select( 'id', 'slug' )->from( TableName::ROLES->table() )->where_in( 'slug', self::ROLES );
		$result = [];

		foreach ( $this->database()->get_results( $roles->build(), $roles->get_bindings() ) ?: [] as $role ) {
			$caps = $this->query()->select( 'id', 'capabilities' )->from( TableName::ROLE_CAPS->table() )->where( 'role_id', '=', (int) $role['id'] )->limit( 1 );
			$row  = $this->database()->get_row( $caps->build(), $caps->get_bindings() );

			if ( $row ) {
				$result[] = [ 'caps_id' => $row['id'], 'slug' => (string) $role['slug'], 'capabilities' => $row['capabilities'] ];
			}
		}

		return $result;
	}

	/**
	 * @param mixed $value The stored JSON list.
	 * @return list<string>
	 */
	private function decode( mixed $value ) : array {
		$decoded = is_string( $value ) && '' !== $value ? json_decode( $value, true ) : [];

		return is_array( $decoded ) ? array_values( array_map( 'strval', $decoded ) ) : [];
	}

	/**
	 * @param int          $caps_id
	 * @param list<string> $capabilities
	 * @return void
	 */
	private function save( int $caps_id, array $capabilities ) : void {
		$update = $this->query()->update( TableName::ROLE_CAPS->table() )
			->set( [ 'capabilities' => json_encode( array_values( array_unique( $capabilities ) ), JSON_THROW_ON_ERROR ) ] )
			->where( 'id', '=', $caps_id );

		if ( false === $this->database()->execute( $update->build(), $update->get_bindings() ) ) {
			throw new \RuntimeException( sprintf( 'Could not update the capabilities of role capability row %d.', $caps_id ) );
		}
	}
}
