<?php
/**
 * Admin license page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin\ContentHandlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\Background\Jobs\Purchases\CleanPurchaseReceiptsJob;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\Monetization\PurchaseRequest;
use SmartLicenseServer\Monetization\PurchaseRequestNote;
use SmartLicenseServer\Monetization\PurchaseRequestService;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateLocator;


use function compact;

/**
 * The admin license page class
 */
class LicensePage implements AdminPageInterface {

	public function __construct(
		protected TemplateLocator $locator,
		protected RepositoryAnalytics $repository_analytics,
		protected URLManager $urlmanager,
		protected Settings $settings,
		protected PurchaseRequestService $purchase_requests,
		protected AssetsManager $assets_manager,
		protected FileSystem $fs
	) {
		// The purchase request review page: its forms post through page-forms.js; purchase-review.js shows receipts.
		$this->assets_manager->set_script_category( View::FORMS_SCRIPT, AssetsManager::CATEGORY_ADMIN_DASHBOARD );
		$this->assets_manager->set_script_category( 'purchase-review', AssetsManager::CATEGORY_ADMIN_DASHBOARD );
	}

	/**
	 * The license page dashbard
	 * 
	 * @param Request $request
	 */
	public function dashboard( Request $request ) : void {
		$current_url = smliser_get_current_url();
		$limit       = $request->get( 'limit', 20 );
		$page        = $request->get( 'paged', 1 );

		if ( $search_term = $request->get( 'search_term' ) ) {
			$license_data = License::search( [
				'search_term' => $search_term,
				'limit'       => $limit,
				'page'        => $page
			] );
		} else {
			$license_data = License::get_all( $page, $limit );
		}
		
		$licenses   = $license_data['items'] ?? [];
		$pagination = $license_data['pagination'] ?? [];
		$add_url    = $this->urlmanager->admin_license_page_url( 'add-new' );

		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'urlmanager', 'page_handler', 'request', 'current_url',
			'licenses', 'pagination', 'add_url'
		);
		$this->locator->render( 'admin.contents.license.index', $vars );
	}

	/**
	 * The license page dashbard
	 * 
	 * @param Request $request
	 */
	public function search_page( Request $request ) : void {
		$current_url = smliser_get_current_url();
		$limit       = $request->get( 'limit', 20 );
		$page        = $request->get( 'paged', 1 );
		$search_term = $request->get( 'search_term' );

		$license_data = License::search( [
			'search_term' => $search_term,
			'limit'       => $limit,
			'page'        => $page
		] );
		$licenses     = $license_data['items'] ?? [];
		$pagination   = $license_data['pagination'] ?? [];
		$add_url      = $this->urlmanager->admin_license_page_url( 'add-new' );
		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'urlmanager', 'page_handler', 'request', 'current_url',
			'licenses', 'pagination', 'add_url'
		);
		$this->locator->render( 'admin.contents.license.search', $vars );
	
	}

	/**
	 * Add license page
	 */
	public function add_license_page( Request $request ) : void {
		$form_fields  = $this->get_form_fields();
		$tab          = $request->get( 'tab' ) ?? $request->route_param( 'tab' );
		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'urlmanager', 'page_handler', 'request', 'form_fields', 'tab' );

		$this->locator->render( 'admin.contents.license.form', $vars );
	}

	/**
	 * License edit page
	 */
	public function edit_license_page( Request $request ) : void {

		$license_id = $request->get( 'id' );
		$license    = License::get_by_id( $license_id );

		if ( ! $license ) {
			echo \smliser_not_found_container(
				\sprintf(
					'Invalid or deleted license is "%s". <a href="%s"> <i class="ti ti-arrow-left"></i> Go Back</a>',
					$license_id,
					$this->urlmanager->admin_license_page_url()->url(),
				)
			);

			return;
		}
		$tab = $request->get( 'tab' ) ?? $request->route_param( 'tab' );
		
		$form_fields  = $this->get_form_fields( $license );
		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'urlmanager', 'page_handler', 'request', 'form_fields',
			'tab', 'license', 'license_id'
		);

		$this->locator->render( 'admin.contents.license.form', $vars );
	}

	/**
	 * License view page
	 */
	public function view_license_page( Request $request ) : void {
		$license_id   = (int) $request->get( 'id', 0 );
		$license      = License::get_by_id( $license_id );
		$licensed_app = $license?->get_app();

		$vars = compact( 'request', 'license', 'licensed_app', 'license_id' );
		if ( $license ) {
			$licensee   = $license->get_licensee_fullname();
			$delete_url = $this->urlmanager->url( '', [
				'action'     => 'smliser_delete_license',
				'license_id' => $license_id,
				// 'smliser_nonce' => wp_create_nonce( 'smliser_delete_license_nonce' )
			] );

			$vars['licensee']   = $licensee;
			$vars['delete_url'] = $delete_url;
		}

		$vars['page_handler'] = $this;
		$vars['urlmanager']   = $this->urlmanager;
		$vars['total_logs']   = count( array_filter(
			$this->repository_analytics->get_license_activity_logs(),
			fn ( $log ) : bool => $log['license_id'] === $license_id
		) );

		$this->locator->render( 'admin.contents.license.view', $vars );
   
	}

	/**
	 * License activation log page.
	 */
	public function license_logs_page( Request $request ) : void {
		$logs = $this->repository_analytics->get_license_activity_logs();

		if ( $request->has( 'filterBy' ) ) {
			$logs = array_filter(
				$logs,
				fn( $log ) => ( $log['license_id'] ?? 0 ) === $request->get( 'filterBy' )
			);
		}

		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$log_duration = (int) ( $this->settings->get( Settings::LOG_RETENTION_DAYS, 30 ) ?? 30 );
		$vars         = compact( 'log_duration', 'logs', 'request', 'urlmanager', 'page_handler' );
		
		$this->locator->render( 'admin.contents.license.logs', $vars );
	}

	/**
	 * Purchase requests: buyers' receipts for tiers sold by manual payment.
	 *
	 * All requests are listed by default, newest first; ?status= shows one
	 * status. The reviewer opens one to check the receipt.
	 *
	 * @param Request $request
	 */
	public function purchase_requests_page( Request $request ) : void {
		$status = (string) $request->get( 'status', 'all' );
		$status = in_array( $status, [ PurchaseRequest::STATUS_PENDING, PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_REJECTED, 'all' ], true ) ? $status : 'all';
		$data   = PurchaseRequest::get_all( (int) $request->get( 'paged', 1 ), (int) $request->get( 'limit', 20 ), 'all' === $status ? '' : $status );

		$purchase_requests = $data['items'];
		$pagination        = $data['pagination'];
		$status_counts     = PurchaseRequest::count_by_status();
		$delete_url        = $this->urlmanager->admin_url( 'admin/json/purchase-request-delete' )->url();
		$page_handler      = $this;
		$urlmanager        = $this->urlmanager;
		$vars              = compact( 'request', 'status', 'purchase_requests', 'pagination', 'status_counts', 'delete_url', 'page_handler', 'urlmanager' );

		$this->locator->render( 'admin.contents.license.purchase-requests', $vars );
	}

	/**
	 * One purchase request, with its receipt.
	 *
	 * @param Request $request
	 */
	public function purchase_request_page( Request $request ) : void {
		$request_id = (int) $request->get( 'id', 0 );
		$purchase   = PurchaseRequest::get_by_id( $request_id );
		$tier       = $purchase?->get_tier();
		$app        = $tier ? Monetization::get_by_id( $tier->get_monetization_id() )?->get_app() : null;
		$receipt    = $purchase ? $this->purchase_requests->get_receipt_path( $purchase ) : null;

		$receipt_url   = null !== $receipt ? $this->urlmanager->admin_downloads_url( sprintf( 'purchase-receipt-%d', $request_id ) )->url() : '';
		$receipt_size  = null !== $receipt ? (int) $this->fs->filesize( $receipt ) : 0;
		$page_count    = $purchase ? count( $this->purchase_requests->get_receipt_pages( $purchase ) ) : 0;
		$receipt_pages = array_map(
			fn ( int $page ) : string => $this->urlmanager->admin_downloads_url( sprintf( 'purchase-receipt-%d/page-%d', $request_id, $page ) )->url(),
			$page_count ? range( 1, $page_count ) : []
		);

		$notes = $purchase ? PurchaseRequestNote::get_for_request( $purchase->get_id() ) : [];

		// When a decided request's receipt is deleted (CleanPurchaseReceiptsJob); 0 keeps it.
		$receipt_retention = max( 0, (int) $this->settings->get( Settings::RECEIPT_RETENTION_DAYS, CleanPurchaseReceiptsJob::DEFAULT_RETENTION_DAYS ) );

		// Reviewers' names, for the notes and the decision.
		$author_ids = array_unique( array_filter( array_merge(
			array_map( static fn ( PurchaseRequestNote $note ) : int => $note->get_author_id(), $notes ),
			[ (int) $purchase?->get_reviewed_by() ]
		) ) );
		$authors    = [];

		foreach ( $author_ids as $author_id ) {
			$authors[ $author_id ] = (string) User::get_by_id( $author_id )?->get_display_name() ?: sprintf( 'User #%d', $author_id );
		}

		// The review forms post to PurchaseReview through page-forms.js.
		$actions = [
			'note'    => $this->urlmanager->admin_url( 'admin/json/purchase-request-note' )->url(),
			'approve' => $this->urlmanager->admin_url( 'admin/json/purchase-request-approve' )->url(),
			'reject'  => $this->urlmanager->admin_url( 'admin/json/purchase-request-reject' )->url(),
			'delete'  => $this->urlmanager->admin_url( 'admin/json/purchase-request-delete' )->url(),
		];

		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'request', 'request_id', 'purchase', 'tier', 'app', 'receipt_url', 'receipt_size', 'receipt_pages', 'receipt_retention', 'notes', 'authors', 'actions', 'page_handler', 'urlmanager' );

		$this->locator->render( 'admin.contents.license.purchase-request', $vars );
	}

	/**
	 * Get args for admin menu.
	 * 
	 * @return array
	 */
	public function get_menu_args( Request $request ) : array {
		$tab = $request->get( 'tab' ) ?? $request->route_param( 'tab' );

		$title = match ( $tab ) {
			'logs'              => smliser__( 'License Activity Logs' ),
			'add-new'           => smliser__( 'Add new license' ),
			'edit'              => smliser__( 'Edit license' ),
			'view'              => smliser__( 'License Details' ),
			'search'            => smliser__( 'Search Licenses' ),
			'purchase-requests' => smliser__( 'Purchase Requests' ),
			'purchase-request'  => smliser__( 'Purchase Request' ),
			default             => smliser__( 'Licenses' )
		};

		$args = [
			'breadcrumbs' => [
				[
					'label' => smliser__( 'Licenses' ),
					'url'   => $this->urlmanager->admin_license_page_url(),
					'icon'  => 'ti ti-home'
				],
				[
					'label' => $title,
				]
			],
			'actions' => [
				[
					'title' => smliser__( 'Settings' ),
					'label' => smliser__( 'Settings' ),
					'url'   => $this->urlmanager->admin_options_url(),
					'icon'  => 'ti ti-settings'
				]
			]
		];

		return $args;
	}

	/**
	 * Get license form fields.
	 * 
	 * @param License|null $license
	 * @return array
	 */
	protected function get_form_fields( ?License $license = null ) : array {
		$app_prop          = $license && $license->get_app() ? sprintf( '%s', str_replace( '/', ':', $license->get_app_prop() ) ) : '';
		$_license_statuses = License::get_allowed_statuses();
		$_status_titles    = array_map( 'ucwords', array_values( $_license_statuses ) );
		$_status_keys      = array_values( $_license_statuses );
		$_statuses         = array_combine( $_status_keys, $_status_titles );
		
		return [
			[
				'label' => smliser__( 'License ID' ),
				'input' => [
					'type'  => 'hidden',
					'name'  => 'license_id',
					'value' => $license?->get_id() ?? 0,
				]
			],
			[
				'label' => smliser__( 'Licensee Name' ),
				'input' => [
					'type'  => 'text',
					'name'  => 'licensee_fullname',
					'value' => $license?->get_licensee_fullname() ?? '',
					'attr'  => [
						'aria-label' => smliser__( 'Licensee full name' )
					]

				]
			],

			[
				'label' => smliser__( 'Licensee Email' ),
				'input' => [
					'type'  => 'email',
					'name'  => 'licensee_email',
					'value' => $license?->get_licensee_email() ?? '',
					'attr'  => [
						'aria-label' => smliser__( 'Licensee email address' ),
						'title'      => smliser__( 'The license is delivered to this address. A registered user with this verified address is linked to the license.' ),
						'required'   => 'required',
					]
				]
			],

			[
				'label' => smliser__( 'Service ID' ),
				'input' => [
					'type'  => 'text',
					'name'  => 'service_id',
					'value' => $license?->get_service_id() ?? '',
					'attr'  => [
						'aria-label' => smliser__( 'Enter Service ID' ),
						'title'      => smliser__( 'The ID a client activates the license with, together with the license key. Leave empty to generate one.' ),
					]
				]
			],
			[
				'label' => smliser__( 'Hosted Application' ),
				'input' => [
					'type'  => 'select',
					'name'  => 'app_prop',
					'value' => $app_prop,
					'attr'  => [
						'aria-label' => smliser__( 'Select hosted application.' ),
						'class'      => 'license-app-select'
					],
					'options' => $app_prop ?[$app_prop => $license->get_app()->get_name()] : []
				]
			],
			[
				'label' => smliser__( 'Status' ),
				'input' => [
					'type'  => 'select',
					'name'  => 'status',
					'value' => $license?->get_status( 'edit' ) ?? '',
					'attr'  => [
						'aria-label' => smliser__( 'Select license status.' )
					],
					'options' => ['' => smliser__( 'Automatic Calculation' )] + $_statuses
				]
			],
			[
				'label' => smliser__( 'Maximum Domains' ),
				'input' => [
					'type'  => 'number',
					'name'  => 'max_allowed_domains',
					'value' => $license?->get_max_allowed_domains( 'edit' ) ?? '',
					'attr'  => [
						'aria-label' => smliser__( 'Maximum domains' ),
						'title'      => smliser__( 'Enter the maximum number of domains allowed to install this license.' )
					]
				]
			],
			[
				'label' => smliser__( 'Start Date' ),
				'input' => [
					'type'  => 'datetime-local',
					'name'  => 'start_date',
					'value' => $license?->get_start_date()?->format( 'Y-m-d H:i:s' ) ?? '',
					'attr'  => [
						'aria-label'          => smliser__( 'Enter license start date.' ),
						'smliser-date-picker' => 'datetime'
					]
				]
			],

			[
				'label' => smliser__( 'End Date' ),
				'input' => [
					'type'  => 'datetime-local',
					'name'  => 'end_date',
					'value' => $license?->get_end_date()?->format( 'Y-m-d H:i:s' ) ?? '',
					'attr'  => [
						'aria-label'          => smliser__( 'Enter license end date.' ),
						'smliser-date-picker' => 'datetime'
					]
				]
			],

			[
				'label' => smliser__( 'Features' ),
				'input' => [
					'type'  => 'textarea',
					'name'  => 'features',
					'value' => implode( ', ', $license?->get_features() ?? [] ),
					'attr'  => [
						'aria-label'  => smliser__( 'License features' ),
						'placeholder' => smliser__( 'example: feature1, feature2, feature3' ),
					]
				]
			],
		];
	}
	
	/*
	|---------------------------
	| INTERFACE IMPLEMENTATION
	|---------------------------
	*/

	public function index_page_handler() : callable {
		return [$this, 'dashboard'];
	}

	/**
	 * @inheritdoc
	 */
	public function get_submenu() : array {
		return [
			[
				'title'      => smliser__( 'All licenses' ),
				'slug'       => 'index',
				'callback'   => [$this, 'dashboard'],
				'visibility' => true,
			],
			[
				'title'      => smliser__( 'Add New' ),
				'slug'       => 'add-new',
				'callback'   => [ $this, 'add_license_page'],
				'visibility' => true,
			],
			[
				'title'      => smliser__( 'Activity Logs' ),
				'slug'       => 'logs',
				'callback'   => [$this, 'license_logs_page'],
				'visibility' => true,
			],
			[
				'title'      => smliser__( 'Search Licenses' ),
				'slug'       => 'search',
				'callback'   => [$this, 'search_page'],
				'visibility' => true,
			],
			[
				'title'      => smliser__( 'Purchase Requests' ),
				'slug'       => 'purchase-requests',
				'callback'   => [$this, 'purchase_requests_page'],
				'visibility' => true,
			],
			[
				'title'      => smliser__( 'Purchase Request' ),
				'slug'       => 'purchase-request',
				'callback'   => [$this, 'purchase_request_page'],
				'visibility' => false
			],
			[
				'title'      => smliser__( 'View License' ),
				'slug'       => 'view',
				'callback'   => [$this, 'view_license_page'],
				'visibility' => false
			],
			[
				'title'      => smliser__( 'Edit License' ),
				'slug'       => 'edit',
				'callback'   => [$this, 'edit_license_page'],
				'visibility' => false
			]
		];
	}
	
	public function get_menu_key() : string {
		return 'licenses';
	}

	public function get_menu_data() : array {
		return [
			'title'      => smliser__( 'Licenses' ),
			'slug'       => 'licenses',
			'handler'    => $this,
			'icon'       => 'ti ti-license',
			'visibility' => true
		];
	}
}
