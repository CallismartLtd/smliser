<?php
/**
 * Capability registry.
 *
 * Canonical definition of everything that can be done in the system.
 * Capabilities are system-defined and MUST NOT be user-generated.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security\Permission;

use InvalidArgumentException;

final class Capability {

	/**
	 * Capability definitions grouped by domain.
	 *
	 * @return array<string, array<string, string>>
	 */
	protected static function definitions() : array {
		return [

			/**
			 * Hosted Applications (Plugin, Theme, Software).
			 */
			'hosted_apps' => [
				'hosted_apps.create'        => smliser__( 'Create hosted applications' ),
				'hosted_apps.update'        => smliser__( 'Update hosted application details' ),
				'hosted_apps.delete'        => smliser__( 'Delete hosted applications' ),
				'hosted_apps.change_status' => smliser__( 'Change hosted application status' ),
				'hosted_apps.upload_assets' => smliser__( 'Upload application assets' ),
				'hosted_apps.edit_assets'   => smliser__( 'Edit application assets' ),
				'hosted_apps.delete_assets' => smliser__( 'Delete application assets' ),
				'hosted_apps.access_files'  => smliser__( 'Access application package files' ),
			],

			/**
			 * Monetization & Licensing
			 */
			'monetization' => [
				'monetization.create'         => smliser__( 'Create app monetization' ),
				'monetization.update'         => smliser__( 'Update app monetization' ),
				'monetization.delete'         => smliser__( 'Delete app monetization' ),
				'monetization.change_status'  => smliser__( 'Change the status of app monetization' ),
				'monetization.pricing.create' => smliser__( 'Create monetization pricing tiers' ),
				'monetization.pricing.update' => smliser__( 'Update monetization pricing tiers' ),
				'monetization.pricing.delete' => smliser__( 'Delete monetization pricing tiers' ),

				'monetization.license.create'           => smliser__( 'Create licenses' ),
				'monetization.license.update'           => smliser__( 'Update licenses' ),
				'monetization.license.revoke'           => smliser__( 'Revoke licenses' ),
				'monetization.license.deactivate'       => smliser__( 'Deactivate licenses' ),
				'monetization.license.delete'           => smliser__( 'Delete licenses' ),
				'monetization.license.issue'            => smliser__( 'Issue licenses' ),
				'monetization.license.uninstall_domain' => smliser__( 'Uninstall domain from license' ),
			],

			/**
			 * Repository & Downloads
			 */
			'repository' => [
				'repository.view'     => smliser__( 'View repository contents' ),
				'repository.download' => smliser__( 'Download application packages' ),
			],

			/**
			 * Analytics & Reporting
			 */
			'analytics' => [
				'analytics.view' => smliser__( 'View analytics data' ),
			],

			/**
			 * Messaging
			 */
			'messaging' => [
				'messaging.send_bulk' => smliser__( 'Send bulk messages' ),
			],

			/**
			 * Security & Identity Management
			 */
			'security' => [
				'security.owner.create' => smliser__( 'Create owners' ),
				'security.owner.update' => smliser__( 'Update owners' ),
				'security.owner.delete' => smliser__( 'Delete owners' ),
				'security.owner.view'   => smliser__( 'View owners' ),

				'security.organization.create'         => smliser__( 'Create organizations' ),
				'security.organization.update'         => smliser__( 'Update organizations' ),
				'security.organization.delete'         => smliser__( 'Delete organizations' ),
				'security.organization.view'           => smliser__( 'View organizations' ),
				'security.organization.add_members'    => smliser__( 'Add members to organizations' ),
				'security.organization.update_members' => smliser__( 'Update organization members' ),
				'security.organization.remove_members' => smliser__( 'Remove members from organizations' ),

				'security.user.create' => smliser__( 'Create users' ),
				'security.user.update' => smliser__( 'Update users' ),
				'security.user.delete' => smliser__( 'Delete users' ),
				'security.user.view'   => smliser__( 'View users' ),
				'security.user.invite' => smliser__( 'Invite users' ),

				'security.service_account.create' => smliser__( 'Create service accounts' ),
				'security.service_account.update' => smliser__( 'Update service accounts' ),
				'security.service_account.delete' => smliser__( 'Delete service accounts' ),
				'security.service_account.view'   => smliser__( 'View service accounts' ),

				'security.role.create' => smliser__( 'Create roles' ),
				'security.role.update' => smliser__( 'Update roles' ),
				'security.role.delete' => smliser__( 'Delete roles' ),
				'security.role.assign' => smliser__( 'Assign roles' ),

				'security.capability.assign' => smliser__( 'Assign capabilities to roles' ),
			],
		];
	}

	/**
	 * Cached flattened capability list.
	 *
	 * @var array<string, string>|null
	 */
	private static array $flatCache = [];

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}

	/**
	 * Get all registered capabilities flattened into a key-value array.
	 *
	 * @return array<string, string> Flat list [ capability_slug => description ]
	 */
	public static function all() : array {
		// Descriptions follow the current language, so cache per locale.
		$locale = smliser_get_locale();

		return self::$flatCache[ $locale ] ??= array_merge( ...array_values( self::definitions() ) );
	}

	/**
	 * Check whether a capability exists.
	 *
	 * @param string $capability
	 * @return bool
	 */
	public static function exists( string $capability ) : bool {
		return isset( self::all()[ $capability ] );
	}

	/**
	 * Assert that a capability exists.
	 *
	 * @param string $capability
	 * @throws InvalidArgumentException
	 */
	public static function assert_exists( string $capability ) : void {
		if ( ! self::exists( $capability ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Unknown capability "%s".', $capability )
			);
		}
	}

	/**
	 * Get capabilities by domain.
	 *
	 * @param string $domain
	 * @return array<string, string>
	 */
	public static function by_domain( string $domain ) : array {
		return self::definitions()[ $domain ] ?? [];
	}

	/**
	 * Get all capabilities grouped by domain.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_caps() : array {
		return self::definitions();
	}

	/**
	 * Get all capability domains.
	 *
	 * @return string[]
	 */
	public static function domains() : array {
		return array_keys( self::definitions() );
	}
}
