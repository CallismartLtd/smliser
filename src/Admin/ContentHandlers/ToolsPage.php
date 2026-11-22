<?php
/**
 * The admin system page handler class.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Admin\ContentHandlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateLocator;
use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Environments\Application\Update\UpdateService;
use SmartLicenseServer\Environments\Application\Health\SystemHealth;
use SmartLicenseServer\Environments\Application\Maintenance\MaintenanceMode;
use SmartLicenseServer\Background\BackgroundSetup;
use SmartLicenseServer\Environments\Application\Theming\CorePages;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\HostedApps\HostingAttestation;

/**
 * The admin system operations page handler.
 */
class ToolsPage implements AdminPageInterface {

	public function __construct(
		protected Scheduler $scheduler,
		protected JobQueue $job_queue,
		protected TemplateLocator $locator,
		protected URLManager $urlmanager,
		protected Settings $settings,
		protected AssetsManager $assets_manager,
		protected UpdateService $updates,
		protected BackgroundSetup $background,
		protected SystemHealth $health,
		protected MaintenanceMode $maintenance,
		protected HostingAttestation $attestation,
		protected HostedAppsRegistry $registry
	) {
		$this->register_assets();
	}

	protected function register_assets() : void {
		$this->assets_manager->set_script_category(
			'site-health',
			AssetsManager::CATEGORY_ADMIN_DASHBOARD
		);

		$this->assets_manager->set_script_category(
			'updates',
			AssetsManager::CATEGORY_ADMIN_DASHBOARD
		);

		$this->assets_manager->set_script_category(
			'background',
			AssetsManager::CATEGORY_ADMIN_DASHBOARD
		);

		$this->assets_manager->set_script_category(
			'support-report',
			AssetsManager::CATEGORY_ADMIN_DASHBOARD
		);

		$this->assets_manager->set_script_category(
			'background-setup',
			AssetsManager::CATEGORY_ADMIN_DASHBOARD
		);

		$this->assets_manager->set_script_category(
			'maintenance',
			AssetsManager::CATEGORY_ADMIN_DASHBOARD
		);
	}

	/*
	|--------------------
	| PAGE HANDLERS
	|--------------------
	*/

	/**
	 * Render the scheduled tasks page.
	 *
	 * @param Request $request
	 * @return void
	 */
	public function schedules_page( Request $request ) : void {
		$tasks        = $this->scheduler->get_tasks_with_state();
		$page_handler = $this;

		$vars = \compact( 'tasks', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.index', $vars );
	}

	/**
	 * Render the queue monitor page (active jobs and failed jobs archive).
	 *
	 * @param Request $request
	 * @return void
	 */
	public function queues_page( Request $request ) : void {
		$section = $request->query( 'section' );
		$page    = (int) $request->get( 'page', 1 );
		$limit   = (int) $request->get( 'limit', 25 );
		$queue   = $request->get( 'queue', null );
		$status  = $request->get( 'status', null );
		$jobs    = 'failed' === $section
			? $this->job_queue->get_failed_jobs( $page, $limit, $queue )
			: $this->job_queue->get_jobs( $page, $limit, $queue, $status );
		$log_rentention = (int) $this->settings->get( Settings::LOG_RETENTION_DAYS, 30 );
		$queue_stats    = $this->job_queue->get_status_counts();

		// The status filter lists the queue; failed jobs are archived out of it and have their own tab.
		$failed_count = $queue_stats[ JobDTO::STATUS_FAILED ];
		unset( $queue_stats[ JobDTO::STATUS_FAILED ] );

		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = \compact(
			'jobs', 'page_handler', 'request', 'urlmanager', 'queue_stats', 'failed_count',
			'log_rentention'
		);
		$this->locator->render( 'admin.contents.system.queues', $vars );
	}

	/**
	 * Render the system diagnostics page.
	 *
	 * Read-only reference data about the running environment, grouped
	 * by category — PHP, database, directories, background processing.
	 * Nothing here is a pass/fail judgment; that's what site_health_page()
	 * is for. The data comes from SystemHealth::diagnostics().
	 *
	 * @param Request $request
	 * @return void
	 */
	public function system_diagnostics_page( Request $request ) : void {
		$diagnostics  = $this->health->diagnostics();
		$report_meta  = $this->report_meta();
		$page_handler = $this;

		$vars = \compact( 'diagnostics', 'report_meta', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.diagnostics', $vars );
	}

	/**
	 * Render the site health page.
	 *
	 * A checklist of pass/warning/critical checks against the running
	 * environment, each with a plain-English explanation and — for
	 * anything not passing — a concrete recommendation. The checks come
	 * from SystemHealth::checks().
	 *
	 * @param Request $request
	 * @return void
	 */
	public function site_health_page( Request $request ) : void {
		$checks       = $this->health->checks();
		$report_meta  = $this->report_meta();
		$page_handler = $this;
		$vars         = \compact( 'checks', 'report_meta', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.health', $vars );
	}

	/**
	 * Render the updates page.
	 *
	 * Shows the last update check without contacting the update server;
	 * update.js checks, runs dry runs and queues installs through
	 * admin/json/update/*.
	 *
	 * @param Request $request
	 * @return void
	 */
	public function updates_page( Request $request ) : void {
		$overview     = $this->updates->overview();
		$page_handler = $this;

		$vars = \compact( 'overview', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.updates', $vars );
	}
	/**
	 * Render the signing key page.
	 *
	 * Shows the public key the site signs every hosted app's package with,
	 * its fingerprint and the public page developers copy it from; or, when
	 * no valid key is set, what that means and how to set one. Read-only:
	 * the key lives in the .env file, and the private half is never shown.
	 *
	 * @param Request $request
	 * @return void
	 */
	public function signing_key_page( Request $request ) : void {
		$signing = [
			'status'         => $this->attestation->key_status(),
			'env_key'        => HostingAttestation::ENV_KEY,
			'public_key'     => $this->attestation->public_key(),
			'fingerprint'    => $this->attestation->fingerprint(),
			'public_url'     => $this->urlmanager->url( CorePages::SIGNING_KEY_PATH )->url(),
			'required_types' => array_map(
				fn( string $type ) : string => $this->registry->get_app_type_label( $type, true ),
				$this->registry->attestation_required_types()
			),
		];
		$page_handler = $this;

		$vars = \compact( 'signing', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.signing-key', $vars );
	}

	/**
	 * Render the maintenance page.
	 *
	 * Shows whether the site is in maintenance and why, and switches
	 * operator maintenance on and off through admin/json/maintenance/*
	 * (maintenance.js). While maintenance is on, this page is reached
	 * through the bypass (see MaintenanceBypass).
	 *
	 * @param Request $request
	 * @return void
	 */
	public function maintenance_page( Request $request ) : void {
		$status       = $this->maintenance->status();
		$page_handler = $this;

		$vars = \compact( 'status', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.maintenance', $vars );
	}


	/**
	 * Render the background processing page.
	 *
	 * Shows what runs scheduled tasks and queued jobs now (from the
	 * heartbeats), and the configuration of each way to run them, written
	 * for this server. The mode picker only switches what is shown: which
	 * mode runs is whatever actually calls the application.
	 *
	 * @param Request $request
	 * @return void
	 */
	public function background_page( Request $request ) : void {
		$modes    = $this->background->modes();
		$detected = $this->background->detect();
		$selected = (string) $request->get( 'mode', $this->background->suggested_mode() );
		$selected = isset( $modes[ $selected ] ) ? $selected : $this->background->suggested_mode();
		$configs  = [];

		foreach ( \array_keys( $modes ) as $mode ) {
			$configs[ $mode ] = $this->background->config( $mode );
		}

		$runners      = $this->background->runners();
		$overview     = $this->background->overview( $detected );
		$page_handler = $this;

		$vars = \compact( 'modes', 'detected', 'selected', 'configs', 'runners', 'overview', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.background', $vars );
	}

	/*
	|--------------------
	| MENU REGISTRATION
	|--------------------
	*/

	public function get_menu_key() : string {
		return 'tools';
	}

	public function get_menu_data() : array {
		return [
			'title'      => smliser__( 'Tools' ),
			'icon'       => 'ti ti-tool',
			'handler'    => $this,
			'slug'       => 'tools',
			'visibility' => true
		];
	}

	public function get_submenu() : array {
		return [
			[
				'title'      => smliser__( 'Schedules' ),
				'slug'       => 'schedules',
				'callback'   => [$this, 'schedules_page'],
				'visibility' => true
			],
			[
				'title'      => smliser__( 'Queue Monitor' ),
				'slug'       => 'queues',
				'callback'   => [$this, 'queues_page'],
				'visibility' => true
			],
			[
				'title'      => smliser__( 'Background Processing' ),
				'slug'       => 'background',
				'callback'   => [$this, 'background_page'],
				'visibility' => true
			],
			[
				'title'      => smliser__( 'System Diagnostics' ),
				'slug'       => 'diagnostics',
				'callback'   => [$this, 'system_diagnostics_page'],
				'visibility' => true
			],
			[
				'title'      => smliser__( 'Site Health' ),
				'slug'       => 'health',
				'callback'   => [$this, 'site_health_page'],
				'visibility' => true
			],
			[
				'title'      => smliser__( 'Updates' ),
				'slug'       => 'updates',
				'callback'   => [$this, 'updates_page'],
				'visibility' => true
			],
			[
				'title'      => smliser__( 'Maintenance' ),
				'slug'       => 'maintenance',
				'callback'   => [$this, 'maintenance_page'],
				'visibility' => true
			],
			[
				'title'      => smliser__( 'Signing Key' ),
				'slug'       => 'signing-key',
				'callback'   => [$this, 'signing_key_page'],
				'visibility' => true
			],
		];
	}

	public function index_page_handler() : callable {
		return [$this, 'schedules_page'];
	}

	/**
	 * Get menu args.
	 *
	 * @return array<string, mixed>
	 */
	public function get_top_menu_args( Request $request ) : array {
		$tab   = $request->get( 'tab' ) ?? $request->route_param( 'tab' );
		$title = match( $tab ) {
			'queues'      => smliser__( 'Queue Monitor' ),
			'background'  => smliser__( 'Background Processing' ),
			'diagnostics' => smliser__( 'System Diagnostics' ),
			'health'      => smliser__( 'Site Health' ),
			'updates'     => smliser__( 'Updates' ),
			'maintenance' => smliser__( 'Maintenance' ),
			'signing-key' => smliser__( 'Signing Key' ),
			default       => ''
		};

		return [
			'breadcrumbs' => [
				[
					'label' => smliser__( 'Schedules' ),
					'url'   => $this->urlmanager->admin_tools_page_url(),
					'icon'  => 'ti ti-home',
				],
				[
					'label' => $title,
				],
			],
			'actions' => [],
		];
	}
	/*
	|----------------
	| SUPPORT REPORT
	|----------------
	*/

	/**
	 * GET site-health-check/diagnostics
	 *
	 * The System Diagnostics as data, for the site health page's support
	 * report. Fetched only when the report is copied: collecting them walks
	 * the installation folder for its size, which is too slow for every
	 * page load.
	 *
	 * Response: { diagnostics: { section: { label: value } } }
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function diagnostics_data( Request $request ) : Response {
		return Response::json( data: [ 'diagnostics' => $this->health->diagnostics() ], status_code: 200 );
	}

	/**
	 * GET background/status
	 *
	 * What runs background work now, for the Background Processing page's
	 * live verification (background-setup.js polls it).
	 *
	 * Response: { mode, overview: { tone, icon, title, message }, runners: [ runner ] }
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function background_status_data( Request $request ) : Response {
		$detected = $this->background->detect();

		return Response::json(
			data: [
				'mode'     => $detected['mode'],
				'overview' => $this->background->overview( $detected ),
				'runners'  => $this->background->runners(),
			],
			status_code: 200
		);
	}

	/**
	 * GET site-health-check/database
	 *
	 * The database connection check, run by health.js with the other
	 * additional checks rather than during page load.
	 *
	 * Response: { checks: [ check ] }
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function database_check_data( Request $request ) : Response {
		return Response::json( data: [ 'checks' => [ $this->health->database_check() ] ], status_code: 200 );
	}

	/**
	 * The header of a support report: what is installed, where, and when.
	 *
	 * Rendered into the copy buttons; support-report.js formats the rest
	 * from what the page shows.
	 *
	 * @return array{product: string, version: string, schema: string, site: string}
	 */
	public function report_meta() : array {
		return $this->health->report_meta();
	}
}
