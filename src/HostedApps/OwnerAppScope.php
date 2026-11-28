<?php
/**
 * Owner app scope class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Security\Context\Guard;

/**
 * The apps of the owner the signed-in user acts for, and what their role
 * there lets them do with those apps.
 *
 * The client dashboard reads and changes apps only through this scope, so
 * every page, action and download is held to the same rules:
 *
 *   - The owner is the principal's current owner: the user, or the
 *     organization they switched to. Without one (or the platform, ID 0)
 *     there are no apps.
 *   - An app is in scope only when that owner owns it. An app ID or slug
 *     from a request is looked up and then checked here; an app of any
 *     other owner is not found, even for a system administrator, who
 *     manages other owners' apps in the admin dashboard.
 *   - Each action needs the capabilities of ACTIONS in the role the user
 *     holds where they act.
 *
 * HostingController checks capabilities and ownership again when it
 * carries an action out; this scope keeps the client dashboard from
 * offering, or reaching, anything outside the owner's apps.
 */
final class OwnerAppScope {

	/**
	 * Every action, with the capabilities it needs: "any" of them, or "all".
	 *
	 * They match what HostingController (and FileRequestController for
	 * downloads) checks for the same operation.
	 *
	 * @var array<string, array{0: 'any'|'all', 1: list<string>}>
	 */
	public const ACTIONS = [
		// The apps list and an app's page.
		'view'             => [ 'any', [
			'hosted_apps.create',
			'hosted_apps.update',
			'hosted_apps.change_status',
			'hosted_apps.delete',
			'hosted_apps.upload_assets',
			'hosted_apps.edit_assets',
			'hosted_apps.delete_assets',
			'hosted_apps.access_files',
			'repository.view',
			'analytics.view',
		] ],
		'create'           => [ 'any', [ 'hosted_apps.create' ] ],
		'update'           => [ 'any', [ 'hosted_apps.update' ] ],
		'change_status'    => [ 'any', [ 'hosted_apps.change_status' ] ],
		// Into or out of the trash: a status change that also moves the files.
		'trash'            => [ 'all', [ 'hosted_apps.change_status', 'hosted_apps.delete' ] ],
		'delete'           => [ 'any', [ 'hosted_apps.delete' ] ],
		'upload_assets'    => [ 'any', [ 'hosted_apps.upload_assets' ] ],
		'delete_assets'    => [ 'any', [ 'hosted_apps.delete_assets' ] ],
		'upload_artifacts' => [ 'any', [ 'hosted_apps.upload_assets' ] ],
		'edit_artifacts'   => [ 'any', [ 'hosted_apps.edit_assets' ] ],
		'delete_artifacts' => [ 'any', [ 'hosted_apps.delete_assets', 'hosted_apps.edit_assets' ] ],
		'download'         => [ 'any', [ 'hosted_apps.access_files', 'repository.download' ] ],
		'view_stats'       => [ 'any', [ 'analytics.view' ] ],
	];

	/**
	 * @param Guard $guard The signed-in principal.
	 */
	public function __construct( private Guard $guard ) {}

	/**
	 * The ID of the owner the user acts for; null without one.
	 *
	 * @return int|null
	 */
	public function owner_id() : ?int {
		$id = (int) $this->guard->get_principal()?->get_owner()?->get_id();

		return $id > 0 ? $id : null;
	}

	/**
	 * Whether the user acts for an owner and may take an action on its apps.
	 *
	 * An unknown action is refused.
	 *
	 * @param string $action A key of ACTIONS.
	 * @return bool
	 */
	public function can( string $action ) : bool {
		$principal = $this->guard->get_principal();

		if ( null === $principal || null === $this->owner_id() || ! isset( self::ACTIONS[ $action ] ) ) {
			return false;
		}

		[ $mode, $capabilities ] = self::ACTIONS[ $action ];

		return 'all' === $mode ? $principal->can_all( $capabilities ) : $principal->can_any( $capabilities );
	}

	/**
	 * Whether an app is the current owner's and the user may see it.
	 *
	 * @param HostedAppsInterface|null $app
	 * @return bool
	 */
	public function allows( ?HostedAppsInterface $app ) : bool {
		$owner_id = $this->owner_id();

		return null !== $app
			&& null !== $owner_id
			&& $app->exists()
			&& $app->get_owner_id() === $owner_id
			&& $this->can( 'view' );
	}

	/**
	 * An app of the current owner, by type and ID; null for any other.
	 *
	 * @param string $type
	 * @param int    $id
	 * @return AbstractHostedApp|null
	 */
	public function find( string $type, int $id ) : ?AbstractHostedApp {
		$registry = HostedAppsRegistry::instance();
		$type     = $registry->normalize_app_type( $type );

		if ( $id <= 0 || ! $this->can( 'view' ) || ! $registry->is_app_type_registered( $type ) ) {
			return null;
		}

		$app = HostedApplicationService::get_app_by_id( $type, $id );

		return $app instanceof AbstractHostedApp && $this->allows( $app ) ? $app : null;
	}

	/**
	 * An app of the current owner, by type and slug; null for any other.
	 *
	 * @param string $type
	 * @param string $slug
	 * @return AbstractHostedApp|null
	 */
	public function find_by_slug( string $type, string $slug ) : ?AbstractHostedApp {
		$registry = HostedAppsRegistry::instance();
		$type     = $registry->normalize_app_type( $type );

		if ( '' === $slug || ! $this->can( 'view' ) || ! $registry->is_app_type_registered( $type ) ) {
			return null;
		}

		$app = HostedApplicationService::get_app_by_slug( $type, $slug );

		return $app instanceof AbstractHostedApp && $this->allows( $app ) ? $app : null;
	}
}
