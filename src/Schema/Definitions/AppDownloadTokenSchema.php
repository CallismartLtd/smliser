<?php
/**
 * App Download Token Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package Callismart\DBPrism\Schema\Definitions
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

/**
 * Stores download tokens.
 * 
 * @since 0.2.0
 */
class AppDownloadTokenSchema implements DatabaseSchemaInterface {

	/**
	 * @inheritDoc
	 */
	public static function get_label() : string {
		return 'App Download Tokens';
	}

	/**
	 * @inheritDoc
	 */
	public static function get_description() : string {
		return 'Stores download tokens.';
	}

	/**
	 * @inheritDoc
	 */
	public static function get_table_name() : string {
		return TableName::APP_DOWNLOAD_TOKENS->value;
	}

	/**
	 * @inheritDoc
	 */
	public static function get_columns() : array {
		return [
			Column::make( 'id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->auto_increment()
				->required(),

			Column::make( 'app_prop' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->default( null ),

			Column::make( 'license_key' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->default( null ),

			Column::make( 'token' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->default( null ),

			Column::make( 'expiry' )
				->type( ColumnType::INTEGER )
				->required(),

			// The host of the license activation the token was issued to;
			// null for tokens an administrator issues outside an activation.
			Column::make( 'activation_host' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->default( null ),
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function get_constraints() : array {
		$prefx = static::constraintPrefix();
		return [
			Constraint::primary( "{$prefx}primary" )->on( 'id' ),

			Constraint::index( "{$prefx}expiry_index" )->on( 'expiry' ),

			Constraint::index( "{$prefx}dtoken_index" )->on( 'token' ),

			Constraint::index( "{$prefx}activation_index" )->on( 'license_key', 'activation_host' ),
		];
	}

	protected static function constraintPrefix() {
		return 'smliser_app_tokenschema_';
	}
}
