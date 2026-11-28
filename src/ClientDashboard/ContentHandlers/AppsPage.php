<?php
/**
 * Apps page class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use SmartLicenseServer\Analytics\AppStatsSummary;
use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\FileSystem\WPPluginRepository;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\HostedApps\OwnerAppScope;
use SmartLicenseServer\Utils\Format;

/**
 * Apps: the apps the current owner hosts.
 *
 * The current owner is the user, or the organization they act for; the
 * page lists and manages that owner's apps only, of every type and status
 * (OwnerAppScope). It shows while the user acts for an owner and their
 * role there allows the "view" action of OwnerAppScope::ACTIONS. Each part
 * shows only when that role allows its action:
 *   uploading an app (apps/new)             create
 *   details, app.json, a new version        update
 *   active or inactive                      change_status
 *   the trash                               trash
 *   deleting for good                       delete
 *   adding images / deleting images         upload_assets / delete_assets
 *   adding / editing / deleting artifacts   upload_artifacts / edit_artifacts / delete_artifacts
 *   downloading the package and artifacts   download
 *   downloads and client access figures     view_stats
 *
 * A suspended app is the platform's decision: its owner sees why it is
 * not offered and cannot change its status (HostingController enforces it).
 * Choosing an app's owner, its slug, suspending it, the app storage
 * browser and monetization stay in the admin dashboard.
 *
 * URLs: apps (the list; ?status= and ?type= filter, ?paged= pages),
 * apps?type=&app= (one app), apps/new (upload). The actions are AppActions';
 * the downloads are FileRequestController::get_client_owned_app_file().
 */
final class AppsPage implements ClientPageInterface {

	/**
	 * Apps per page of the list.
	 */
	private const PER_PAGE = 20;

	/**
	 * Core script that mounts the JSON editor on the app.json field.
	 */
	public const MANIFEST_EDITOR_ASSET = 'manifest-editor';

	/**
	 * Core style of the JSON editor.
	 */
	public const JSON_EDITOR_STYLE = 'json-editor';

	/**
	 * @param OwnerAppScope   $scope      The current owner's apps.
	 * @param URLManager      $urlmanager URL API.
	 * @param AppStatsSummary $stats      The apps' figures.
	 */
	public function __construct(
		private OwnerAppScope $scope,
		private URLManager $urlmanager,
		private AppStatsSummary $stats
	) {}

	public function get_menu_key() : string {
		return 'apps';
	}

	public function get_menu_data() : array {
		return [
			'title'      => smliser__( 'Apps' ),
			'slug'       => 'apps',
			'handler'    => $this,
			'icon'       => 'ti ti-apps',
			'visibility' => fn () : bool => $this->scope->can( 'view' ),
		];
	}

	public function get_submenu() : array {
		return [
			[
				'title'      => smliser__( 'Upload an app' ),
				'slug'       => 'new',
				'callback'   => [ $this, 'upload' ],
				'visibility' => fn () : bool => $this->scope->can( 'create' ),
			],
		];
	}

	public function index_page_handler() : callable {
		return [ $this, 'apps' ];
	}

	/*
	|-------
	| PAGES
	|-------
	*/

	/**
	 * The list, or one app when the URL names it (?type=&app=).
	 *
	 * View data (template client.apps):
	 *   list<array{name: string, type: string, slug: string, status: string,
	 *       status_label: string, updated: string, url: string}> $apps
	 *   list<array{label: string, url: string, current: bool}> $filters By status.
	 *   list<array{label: string, url: string, current: bool}> $type_filters By type; empty with one type.
	 *   string $status The status shown; "" for every status.
	 *   string $type The type shown; "" for every type.
	 *   array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
	 *   string $new_url The upload page; "" without the create action.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function apps( Request $request ) : View {
		if ( $request->hasValue( 'app' ) ) {
			return $this->app( $request );
		}

		$registry = HostedAppsRegistry::instance();
		$statuses = AbstractHostedApp::get_statuses();
		$status   = (string) $request->get( 'status', '' );
		$status   = array_key_exists( $status, $statuses ) ? $status : '';
		$type     = $registry->normalize_app_type( (string) $request->get( 'type', '' ) );
		$type     = $registry->is_app_type_registered( $type ) ? $type : '';
		$page     = max( 1, (int) $request->get( 'paged', 1 ) );
		$args     = [ 'status' => $status, 'page' => $page, 'limit' => self::PER_PAGE ];

		if ( '' !== $type ) {
			$args['types'] = [ $type ];
		}

		$result   = HostedApplicationService::get_apps_for_owner( (int) $this->scope->owner_id(), $args );
		$list_url = fn ( array $query ) : string => $this->urlmanager->client_dashboard_url( 'apps', array_filter( $query ) )->url();

		$apps = array_map(
			fn ( AbstractHostedApp $app ) : array => [
				'name'         => $app->get_name(),
				'type'         => $registry->get_app_type_label( $app->get_type() ),
				'slug'         => $app->get_slug(),
				'status'       => $app->get_status(),
				'status_label' => $statuses[ $app->get_status() ] ?? $app->get_status(),
				'updated'      => $app->get_updated_at()->format( \smliser_datetime_format() ),
				'url'          => $this->app_url( $app ),
			],
			$result['items']
		);

		$filters = [ [ 'label' => smliser__( 'All' ), 'url' => $list_url( [ 'type' => $type ] ), 'current' => '' === $status ] ];

		foreach ( $statuses as $key => $label ) {
			$filters[] = [ 'label' => $label, 'url' => $list_url( [ 'status' => $key, 'type' => $type ] ), 'current' => $key === $status ];
		}

		$type_filters = [];

		if ( count( $registry->app_types() ) > 1 ) {
			$type_filters[] = [ 'label' => smliser__( 'All types' ), 'url' => $list_url( [ 'status' => $status ] ), 'current' => '' === $type ];

			foreach ( $registry->app_types() as $key ) {
				$type_filters[] = [ 'label' => $registry->get_app_type_label( $key, true ), 'url' => $list_url( [ 'status' => $status, 'type' => $key ] ), 'current' => $key === $type ];
			}
		}

		$pagination = $result['pagination'];
		$current    = [ 'status' => $status, 'type' => $type ];

		return View::make(
			'client.apps',
			[
				'apps'         => $apps,
				'filters'      => $filters,
				'type_filters' => $type_filters,
				'status'       => $status,
				'type'         => $type,
				'pagination'   => [
					'page'         => $pagination['page'],
					'total_pages'  => $pagination['total_pages'],
					'total'        => $pagination['total'],
					'previous_url' => $pagination['page'] > 1 ? $list_url( $current + [ 'paged' => $pagination['page'] - 1 ] ) : '',
					'next_url'     => $pagination['page'] < $pagination['total_pages'] ? $list_url( $current + [ 'paged' => $pagination['page'] + 1 ] ) : '',
				],
				'new_url'      => $this->scope->can( 'create' ) ? $this->urlmanager->client_dashboard_url( 'apps/new' )->url() : '',
			]
		);
	}

	/**
	 * Upload an app.
	 *
	 * View data (template client.app-new):
	 *   array<string, string> $types App types, type => label.
	 *   string $type The type chosen first (?type=); "" for the first one.
	 *   int $max_size Largest upload, in bytes.
	 *   string $action The save action's URL.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function upload( Request $request ) : View {
		$registry = HostedAppsRegistry::instance();
		$types    = [];

		foreach ( $registry->app_types() as $type ) {
			$types[ $type ] = $registry->get_app_type_label( $type );
		}

		$chosen = $registry->normalize_app_type( (string) $request->get( 'type', '' ) );

		return View::make(
			'client.app-new',
			[
				'types'        => $types,
				'type'         => isset( $types[ $chosen ] ) ? $chosen : '',
				'max_size'     => \smliser_max_upload_size(),
				'action'       => $this->action_url( 'apps/save' ),
			]
		)
			->add_script( View::UPLOADER_ASSET )
			->add_style( View::UPLOADER_ASSET );
	}

	/**
	 * One app.
	 *
	 * View data (template client.app):
	 *   array{id: int, type: string, type_label: string, name: string, slug: string, version: string,
	 *       status: string, status_label: string, created: string, updated: string, public_url: string,
	 *       download_url: string} $app download_url: the owner's package download; "" without the download action.
	 *   list<array{label: string, value: string, copy: bool}> $info Facts about the package.
	 *   list<array{name: string, label: string, value: string, type: string, required: bool, hint: string}> $fields
	 *       The details form's fields, the app type's included.
	 *   string $manifest The app.json, pretty-printed, for the JSON editor.
	 *   array<string, bool> $can Each action of OwnerAppScope::ACTIONS the user may take.
	 *   array<string, string> $statuses Statuses the owner may choose, status => label.
	 *   list<array{type: string, label: string, hint: string, items: list<array{name: string, url: string}>}> $assets
	 *   list<array{filename: string, main: bool, size: string, mime_type: string, updated: string,
	 *       download_url: string}> $artifacts The main package first; download_url "" without the download action.
	 *   list<array{icon: string, label: string, value: string, color: string, subtitle?: string, highlight?: bool}> $stats
	 *       The last 30 days' figures; empty without the view_stats action.
	 *   int $max_size Largest upload, in bytes.
	 *   string $list_url The list.
	 *   array<string, string> $actions Action URLs: save, status, delete, asset_upload, asset_delete,
	 *       artifact_upload, artifact_update, artifact_delete.
	 *
	 * @param Request $request
	 * @return View
	 */
	private function app( Request $request ) : View {
		$app = $this->scope->find( (string) $request->get( 'type', '' ), (int) $request->get( 'app', 0 ) );

		if ( null === $app ) {
			return View::make(
				'client.not-found',
				[
					'message'    => smliser__( 'This app does not exist, or it belongs to someone else. Switch to its owner to manage it.' ),
					'back_url'   => $this->urlmanager->client_dashboard_url( 'apps' )->url(),
					'back_label' => smliser__( 'Your apps' ),
				]
			)
				->set_status_code( 404 )
				->set_title( smliser__( 'App not found' ) );
		}

		$registry = HostedAppsRegistry::instance();
		$statuses = AbstractHostedApp::get_statuses();
		$repo     = $registry->get_app_type_directory_class( $app->get_type() );
		$labels   = $repo ? $repo->asset_types() : [];
		$assets   = [];
		$can      = [];

		foreach ( array_keys( OwnerAppScope::ACTIONS ) as $action ) {
			$can[ $action ] = $this->scope->can( $action );
		}

		// How each kind of image must be named, where the name decides it.
		$names    = static fn ( array $stems ) : string => sprintf( smliser__( 'Name each file %s, with its image extension.' ), implode( ', ', $stems ) );
		$numbered = smliser__( 'Numbered in the order added; name a file screenshot-2 to give it that place.' );
		$single   = smliser__( 'One image; delete the current one to upload another.' );
		$hints    = [
			'icon'        => $names( Repository::ALLOWED_ICON_NAMES ),
			'banner'      => $names( array_values( WPPluginRepository::ALLOWED_BANNER_NAMES ) ),
			'cover'       => $single,
			'screenshot'  => 'wptheme' === $app->get_type() ? $single : $numbered,
			'screenshots' => $numbered,
		];

		foreach ( $repo ? $repo->assets_by_type( $app->get_slug() ) : [] as $type => $items ) {
			$assets[] = [ 'type' => $type, 'label' => $labels[ $type ] ?? $type, 'hint' => $hints[ $type ] ?? '', 'items' => $items ];
		}

		$artifacts = [];

		foreach ( $repo ? $repo->get_artifacts( $app->get_slug() ) : [] as $artifact ) {
			$main        = 'main' === $artifact['slug'];
			$artifacts[] = [
				'filename'     => $artifact['filename'],
				'main'         => $main,
				'size'         => Format::bytes( (int) $artifact['size'] ),
				'mime_type'    => (string) $artifact['mime_type'],
				'updated'      => date( \smliser_datetime_format(), (int) $artifact['mtime'] ),
				'download_url' => $can['download'] ? $this->download_url( $app, $main ? '' : $artifact['filename'] ) : '',
			];
		}

		// An unset URL reads as an empty field, not as the "/" an empty URL builds.
		$string = static fn ( mixed $value ) : string => match ( true ) {
			$value instanceof URL => $value->is_valid() ? $value->url() : '',
			is_string( $value )   => $value,
			default               => '',
		};

		$fields = [
			[ 'name' => 'app_name', 'label' => smliser__( 'Name' ), 'value' => $app->get_name(), 'type' => 'text', 'required' => true, 'hint' => '' ],
			[ 'name' => 'app_author', 'label' => smliser__( 'Author' ), 'value' => $app->get_author(), 'type' => 'text', 'required' => true, 'hint' => '' ],
			[ 'name' => 'app_author_url', 'label' => smliser__( 'Author profile URL' ), 'value' => $string( $app->get_author_profile() ), 'type' => 'url', 'required' => false, 'hint' => '' ],
			[ 'name' => 'app_homepage_url', 'label' => smliser__( 'Homepage URL' ), 'value' => $string( $app->get_homepage() ), 'type' => 'url', 'required' => false, 'hint' => '' ],
		];

		// The app type's own fields: what HostingController::update_{type}() reads.
		$extra = match ( $app->get_type() ) {
			'wptheme'  => [
				'app_preview_url'             => [ smliser__( 'Preview URL' ), $app->get_meta( 'preview_url' ) ],
				'app_external_repository_url' => [ smliser__( 'External repository URL' ), $app->get_meta( 'external_repository_url' ) ],
			],
			'wpplugin' => [],
			// Software and registered types: HostingController::update_generic() reads it too.
			default    => [
				'app_documentation_url' => [ smliser__( 'Documentation URL' ), $app->get_meta( 'documentation_url' ) ],
			],
		};

		$extra['app_support_url'] = [ smliser__( 'Support URL' ), $app->get_support_url() ];

		foreach ( $extra as $name => [ $label, $value ] ) {
			$fields[] = [ 'name' => $name, 'label' => $label, 'value' => $string( $value ), 'type' => 'url', 'required' => false, 'hint' => '' ];
		}

		// Only a link elsewhere is stored; empty means this site serves the download.
		$fields[] = [
			'name'     => 'app_download_url',
			'label'    => smliser__( 'Download URL' ),
			'value'    => $string( $app->get_custom_download_url() ),
			'type'     => 'url',
			'required' => false,
			'hint'     => sprintf( smliser__( 'Leave empty to serve downloads from this site, at %s' ), $this->urlmanager->app_downloads_url( $app->get_type(), $app->get_slug() )->url() ),
		];

		$license = $app->get_license();
		$info    = [
			[ 'label' => smliser__( 'License' ), 'value' => (string) ( $license['license'] ?? '' ), 'copy' => false ],
			[ 'label' => smliser__( 'File Size' ), 'value' => Format::bytes( HostedApplicationService::get_package_size( $app ) ), 'copy' => false ],
			[ 'label' => smliser__( 'Monetization' ), 'value' => $app->is_monetized() ? smliser__( 'Monetized' ) : smliser__( 'Free' ), 'copy' => false ],
			[ 'label' => smliser__( 'Public Download URL' ), 'value' => (string) ( $app->is_monetized() ? $app->monetized_url_sample() : $app->get_download_url()->url() ), 'copy' => true ],
		];

		$view = View::make(
			'client.app',
			[
				'app'       => [
					'id'           => $app->get_id(),
					'type'         => $app->get_type(),
					'type_label'   => $registry->get_app_type_label( $app->get_type() ),
					'name'         => $app->get_name(),
					'slug'         => $app->get_slug(),
					'version'      => $app->get_version(),
					'status'       => $app->get_status(),
					'status_label' => $statuses[ $app->get_status() ] ?? $app->get_status(),
					'created'      => $app->get_created_at()->format( \smliser_datetime_format() ),
					'updated'      => $app->get_updated_at()->format( \smliser_datetime_format() ),
					'public_url'   => $app->get_url()->url(),
					'download_url' => $can['download'] && null !== $app->get_package() ? $this->download_url( $app ) : '',
				],
				'info'      => $info,
				'fields'    => $fields,
				'manifest'  => (string) \smliser_safe_json_encode( $app->get_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
				'can'       => $can,
				'statuses'  => [
					AbstractHostedApp::STATUS_ACTIVE   => $statuses[ AbstractHostedApp::STATUS_ACTIVE ],
					AbstractHostedApp::STATUS_INACTIVE => $statuses[ AbstractHostedApp::STATUS_INACTIVE ],
				],
				'assets'    => $assets,
				'artifacts' => $artifacts,
				'stats'     => $can['view_stats'] ? AppStatsSummary::stats( $this->stats->analytics( $app ) ) : [],
				'max_size'  => \smliser_max_upload_size(),
				'list_url'  => $this->urlmanager->client_dashboard_url( 'apps' )->url(),
				'actions'   => [
					'save'            => $this->action_url( 'apps/save' ),
					'status'          => $this->action_url( 'apps/status' ),
					'delete'          => $this->action_url( 'apps/delete' ),
					'asset_upload'    => $this->action_url( 'apps/assets/upload' ),
					'asset_delete'    => $this->action_url( 'apps/assets/delete' ),
					'artifact_upload' => $this->action_url( 'apps/artifacts/upload' ),
					'artifact_update' => $this->action_url( 'apps/artifacts/update' ),
					'artifact_delete' => $this->action_url( 'apps/artifacts/delete' ),
				],
			]
		)
			->set_title( $app->get_name() )
			->add_script( View::UPLOADER_ASSET )
			->add_style( View::UPLOADER_ASSET );

		if ( $can['update'] ) {
			$view->add_script( self::MANIFEST_EDITOR_ASSET )->add_style( self::JSON_EDITOR_STYLE );
		}

		return $view;
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * The app's page.
	 *
	 * @param AbstractHostedApp $app
	 * @return string
	 */
	private function app_url( AbstractHostedApp $app ) : string {
		return $this->urlmanager->client_dashboard_url( 'apps', [ 'type' => $app->get_type(), 'app' => $app->get_id() ] )->url();
	}

	/**
	 * The owner's download of the app's package, or of one of its artifacts.
	 *
	 * @param AbstractHostedApp $app
	 * @param string            $artifact The artifact's file name; "" for the package.
	 * @return string
	 */
	private function download_url( AbstractHostedApp $app, string $artifact = '' ) : string {
		$path = sprintf( 'download/app/%s/%d', $app->get_type(), $app->get_id() );

		if ( '' !== $artifact ) {
			$path .= '/artifacts/' . rawurlencode( $artifact );
		}

		return $this->urlmanager->client_dashboard_url( $path )->url();
	}

	/**
	 * An action's URL.
	 *
	 * @param string $path
	 * @return string
	 */
	private function action_url( string $path ) : string {
		return $this->urlmanager->client_dashboard_url( 'json/' . $path )->url();
	}
}
