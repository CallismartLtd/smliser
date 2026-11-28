<?php
/**
 * App owner scope class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard;

use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Scopes the client dashboard to the current owner's apps, and to the
 * licenses issued for them.
 *
 * The current owner is the user, or the organization they act for. An app
 * of any other owner is not found here, even for a system administrator,
 * who manages other owners' apps and licenses in the admin dashboard
 * (the same rule as AppActions). A license is the owner's to manage when
 * its app is one of theirs; a license whose app does not resolve belongs
 * to no one here (fails closed).
 *
 * Nothing is taken from the request: an ID only names what to look up,
 * and the owner and the role come from the session's principal.
 *
 * The answers:
 *   no owner, no license, no app, another owner's app   404 (nothing is revealed)
 *   the owner's app, but the role lacks the capability  403
 */
final class AppOwnerScope {

	/**
	 * Free an activated site from a license.
	 */
	public const CAP_FREE_SITE = 'monetization.license.uninstall_domain';

	/**
	 * Reactivate, suspend or unsuspend a license.
	 */
	public const CAP_CHANGE_STATUS = 'monetization.license.update';

	/**
	 * Capabilities any of which shows the licenses of the owner's apps.
	 */
	public const LICENSE_CAPABILITIES = [ self::CAP_CHANGE_STATUS, self::CAP_FREE_SITE ];

	/**
	 * @param Guard $guard The signed-in principal.
	 */
	public function __construct( private Guard $guard ) {}

	/**
	 * The ID of the owner the user acts for.
	 *
	 * @return int|null
	 */
	public function owner_id() : ?int {
		$id = (int) $this->guard->get_principal()?->get_owner()?->get_id();

		return $id > 0 ? $id : null;
	}

	/**
	 * Whether the user's role where they act holds a capability.
	 *
	 * @param string $capability
	 * @return bool
	 */
	public function can( string $capability ) : bool {
		return (bool) $this->guard->get_principal()?->can( $capability );
	}

	/**
	 * Whether the user may see the licenses of the owner's apps.
	 *
	 * @return bool
	 */
	public function can_view_licenses() : bool {
		return null !== $this->owner_id() && (bool) $this->guard->get_principal()?->can_any( self::LICENSE_CAPABILITIES );
	}

	/**
	 * Whether the current owner owns an app.
	 *
	 * @param AbstractHostedApp|null $app
	 * @return bool
	 */
	public function owns( ?AbstractHostedApp $app ) : bool {
		$owner_id = $this->owner_id();

		return null !== $app && $app->exists() && null !== $owner_id && $app->get_owner_id() === $owner_id;
	}

	/**
	 * Every app of the current owner, whatever its type or status, keyed
	 * by "type/slug" (the form licenses name their app in).
	 *
	 * @return array<string, AbstractHostedApp>
	 */
	public function owned_apps() : array {
		$owner_id = $this->owner_id();
		$apps     = [];

		if ( null === $owner_id ) {
			return $apps;
		}

		$page = 1;

		do {
			$result = HostedApplicationService::get_apps_for_owner( $owner_id, [ 'status' => '', 'page' => $page, 'limit' => 100 ] );

			foreach ( $result['items'] as $app ) {
				if ( $this->owns( $app ) ) {
					$apps[ sprintf( '%s/%s', $app->get_type(), $app->get_slug() ) ] = $app;
				}
			}

			$page++;
		} while ( $page <= (int) ( $result['pagination']['total_pages'] ?? 0 ) );

		return $apps;
	}

	/**
	 * A license of one of the current owner's apps, by ID.
	 *
	 * The app is looked up again by its own type and slug, not taken from
	 * the request.
	 *
	 * @param int         $license_id
	 * @param string|null $capability The capability the action needs; null to only read it.
	 * @return License|RequestException
	 */
	public function license( int $license_id, ?string $capability = null ) : License|RequestException {
		$license = $license_id > 0 ? License::get_by_id( $license_id ) : null;
		$app     = null;

		if ( $license ) {
			$prop     = (array) $license->get_app_prop( 'edit' );
			$registry = HostedAppsRegistry::instance();
			$type     = $registry->normalize_app_type( (string) ( $prop['type'] ?? '' ) );
			$slug     = (string) ( $prop['slug'] ?? '' );
			$app      = '' !== $slug && $registry->is_app_type_registered( $type ) ? HostedApplicationService::get_app_by_slug( $type, $slug ) : null;
		}

		return $this->authorize( $license, $app, $capability );
	}

	/**
	 * The rule: the license, when its app is the current owner's and the
	 * role holds the capability.
	 *
	 * @param License|null           $license
	 * @param AbstractHostedApp|null $app        The license's app, as looked up; null when it does not resolve.
	 * @param string|null            $capability Null to only read the license.
	 * @return License|RequestException
	 */
	public function authorize( ?License $license, ?AbstractHostedApp $app, ?string $capability = null ) : License|RequestException {
		if ( null === $license || ! $license->exists() || ! $this->owns( $app ) ) {
			return new RequestException( 'resource_not_found', smliser__( 'This license does not exist.' ), [ 'status' => 404 ] );
		}

		$allowed = null === $capability ? $this->can_view_licenses() : $this->can( $capability );

		if ( ! $allowed ) {
			return new RequestException( 'forbidden', smliser__( 'Your role does not allow managing the licenses of this app.' ), [ 'status' => 403 ] );
		}

		$license->set_app( $app );

		return $license;
	}
}
