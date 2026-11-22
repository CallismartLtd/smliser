<?php
/**
 * Bind download tokens to activations migration class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Schema\Migrations
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Migrations;

use Callismart\DBPrism\Migrations\AbstractMigration;
use Callismart\DBPrism\Migrations\Contracts\ReversibleMigrationInterface;
use Callismart\DBPrism\Utils\Column;
use SmartLicenseServer\Schema\Definitions\AppDownloadTokenSchema;
use SmartLicenseServer\Schema\TableName;

/**
 * Records which activation each download token was issued to.
 *
 * A token issued at activation or reauthentication belongs to the domain it
 * was issued for, so uninstalling the domain revokes it and only that domain
 * can exchange it for a new one. Tokens issued before this migration name no
 * activation; they are revoked, and their clients activate again for a new
 * one. Tokens are short-lived credentials, so down() does not restore them.
 */
final class BindDownloadTokensToActivations extends AbstractMigration implements ReversibleMigrationInterface {

	/**
	 * The new column.
	 *
	 * @var string
	 */
	private const COLUMN = 'activation_host';

	/**
	 * The new index.
	 *
	 * @var string
	 */
	private const INDEX = 'smliser_app_tokenschema_activation_index';

	public static function get_description() : string {
		return smliser__( 'Bind download tokens to the license activation they were issued to' );
	}

	public function up() : void {
		$table     = TableName::APP_DOWNLOAD_TOKENS->table();
		$snapshots = new MigrationSnapshotStore( $this->database() );

		if ( ! $this->column( $table )->exists( self::COLUMN ) ) {
			// Record ownership before DDL so an interrupted up() can resume safely.
			$snapshots->capture( self::class, 'column:' . self::COLUMN,
				[ 'kind' => 'column_absent', 'name' => self::COLUMN ],
				[ 'kind' => 'column_added', 'name' => self::COLUMN ]
			);

			$this->column( $table )->add( self::schema_column() );
		}

		$this->index( $table )->create( self::INDEX, 'license_key', self::COLUMN );

		// Tokens issued before activations were recorded cannot be bound to one.
		$revoke = $this->query()->delete( $table )->where_null( self::COLUMN );

		if ( false === $this->database()->execute( $revoke->build(), $revoke->get_bindings() ) ) {
			throw new \RuntimeException( 'Could not revoke the download tokens that name no activation.' );
		}

		$snapshots->complete( self::class );
	}

	/** Drop the index, and the column only when this migration added it. */
	public function down() : void {
		$table     = TableName::APP_DOWNLOAD_TOKENS->table();
		$snapshots = new MigrationSnapshotStore( $this->database() );

		$this->index( $table )->drop( self::INDEX );

		foreach ( $snapshots->rows( self::class ) as $snapshot ) {
			if ( 'column_added' === ( $snapshot['after']['kind'] ?? null ) ) {
				$this->column( $table )->drop( self::COLUMN );
			}
		}

		$snapshots->clear( self::class );
	}

	/**
	 * The column as the current schema defines it.
	 *
	 * @return Column
	 * @throws \RuntimeException When the schema no longer defines it.
	 */
	private static function schema_column() : Column {
		foreach ( AppDownloadTokenSchema::get_columns() as $column ) {
			if ( self::COLUMN === $column->name ) {
				return $column;
			}
		}

		throw new \RuntimeException( sprintf( 'The download token schema does not define "%s".', self::COLUMN ) );
	}
}
