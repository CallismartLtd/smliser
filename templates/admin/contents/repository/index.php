<?php
/**
 * The admin repository page template
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 * @var SmartLicenseServer\HostedApps\AbstractHostedApp[] $apps
 * @var array $pagination
 * @var string|null $type
 * @var string|null $status
 * @var \SmartLicenseServer\Core\URL $add_url
 * @var \SmartLicenseServer\Core\URL $current_url
 * @var \SmartLicenseServer\Core\Request $request
 * @var string $page_title
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 * @var array $app_type_actions
 */

use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;

defined( 'SMLISER_ROOT' ) || exit;

$menu_args = [
	'breadcrumbs' => [
		[
			'title' => smliser__( 'Repository' ),
			'label' => smliser__( 'Repository' ),
			'url'   => $urlmanager->admin_repo_url()->url()
		],

		[
			'title' => sprintf( smliser__( '%s Repository' ), HostedAppsRegistry::instance()->get_app_type_label( (string) $type ) ),
			'label' => HostedAppsRegistry::instance()->get_app_type_label( (string) $type, true ),
			'url'   => $urlmanager->admin_repo_url()->add_query_param( 'type', $type )->url()
		],

		[
			'label' => $page_title
		]
	],

	'actions' => [
		[
			'title' => smliser__( 'Upload New Application' ),
			'label' => smliser__( 'Upload New' ),
			'url'   => $add_url->url(),
			'icon'  => 'ti ti-upload',
		],

		...$app_type_actions
	]
];

if ( ! $current_url->has_query_param( 'type' ) && ! $current_url->has_query_param( 'status' ) ) {
	unset( $menu_args['breadcrumbs'][0] ); // Remove the home link on home page.
}

if ( ! $current_url->has_query_param( 'status' ) || ( $current_url->has_query_param( 'status' ) && ! $type ) ) {
	unset( $menu_args['breadcrumbs'][1] );
}
?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>
    <div class="smliser-table-wrapper">
      
        <?php if ( $message = $request->query( 'message' ) ) : ?>
            <div class="notice notice-info is-dismissible"><p><?php echo escHtml( $message ); ?></p></div>
        <?php endif; ?>

        <ul class="subsubsub smliser-status-filter">
            <?php $current_status = $request->query( 'status', '' ); ?>

            <?php foreach ( AbstractHostedApp::STATUSES as $k => $label ) :

            	$is_active = ( $current_status === $k );
            	$count     = HostedApplicationService::count_apps(
            		[
            			'status' => $k,
            			'types'  => $type ?? HostedAppsRegistry::instance()->app_types(),
            		]
            	);
            	?>

                <li class="smliser-status-item">
                    <a 
                        href="<?php echo escUrl( $current_url->add_query_param( 'status', $k )->url() ); ?>"
                        class="smliser-status-link<?php echo $is_active ? ' is-active' : ''; ?>"
                        aria-current="<?php echo $is_active ? 'page' : 'false'; ?>"
                    >
                        <span class="smliser-status-label">
                            <?php echo escHtml( $label ); ?>
                        </span>
                        <span class="smliser-status-count">
                            (<?php echo intval( $count ); ?>)
                        </span>
                    </a>
                </li>

            <?php endforeach; ?>
        </ul>

        <br class="clear" />

        <?php if ( empty( $apps ) ) : ?>
            <?php if ( ! $current_url->has_query_param( 'status' ) ) :
            	$type_name = $type ? HostedAppsRegistry::instance()->get_app_type_label( $type ) : 'application';
            	$message   = sprintf(
            		'No app found in the repository, upload new %1$s <a href="%3$s">here</a>.',
            		escHtml( $type_name ),
            		escHtml( $status ?? '' ),
            		escUrl( $add_url->url() )
            	);
            else:
            	$message = smliser__( 'No app found matching this status' );
            endif; ?>
                <?php echo smliser_not_found_container( $message ); ?>       
        <?php else: ?>

            <form id="smliser-bulk-action-form" method="post">
                <div class="smliser-actions-wrapper">
                    <div class="smliser-bulk-actions">
                        <select name="bulk_action" id="smliser-bulk-action" class="smliser-bulk-action-select" required>
                            <option value=""><?php echo smliser_esc_html__( 'Bulk Actions' ); ?></option>
                            <?php foreach ( AbstractHostedApp::get_statuses() as $status_key => $status_label ) : ?>
                                <?php if ( $status === $status_key ) : continue; endif; ?>
                                <option value="<?php echo escAttr( $status_key ); ?>"><?php echo escHtml( $status_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button action smliser-bulk-action-button"><?php echo smliser_esc_html__( 'Apply' ); ?></button>
                    </div>
                    <a href="<?php echo escUrl( $urlmanager->admin_repo_url( 'search' )->url() ); ?>" class="smliser-btn smliser-surface-btn"><?php smliser_esc_html_e( 'Search Repository' ); ?></a>
                </div>
            
                <input type="hidden" name="action" value="smliser_bulk_action">
                <input type="hidden" name="context" value="repository">

                <table class="smliser-table widefat striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="smliser-select-all"></th>
                            <th><?php echo smliser_esc_html__( 'APP ID' ); ?></th>
                            <th><?php echo smliser_esc_html__( 'App Name' ); ?></th>
                            <th><?php echo smliser_esc_html__( 'App Author' ); ?></th>
                            <th><?php echo smliser_esc_html__( 'App Type' ); ?></th>
                            <th><?php echo smliser_esc_html__( 'Version' ); ?></th>
                            <th><?php echo smliser_esc_html__( 'Slug' ); ?></th>
                            <th><?php echo smliser_esc_html__( 'Status' ); ?></th>
                            <th><?php echo smliser_esc_html__( 'Last Updated' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $apps as $app ) : ?>
                            <tr>
                                <td><input type="checkbox" class="smliser-license-checkbox" name="ids[]" value="<?php printf( '%s:%s', escAttr( $app->get_type() ), escAttr( $app->get_slug() ) ); ?>"> </td>
                                <td class="smliser-edit-row">
                                    <?php echo intval( $app->get_id() ); ?>
                                    <div class="smliser-edit-link">
                                        <a href="<?php echo escUrl( $urlmanager->admin_repo_url( 'edit', [ 'app_id' => $app->get_id(), 'type' => $app->get_type() ] )->url() ); ?>"><?php smliser_esc_html_e( 'edit' ); ?></a> 
                                        |
                                        <a href="<?php echo escUrl( $urlmanager->admin_repo_url( 'view', [ 'app_id' => $app->get_id(), 'type' => $app->get_type() ] )->url() ); ?>"><?php smliser_esc_html_e( 'view' ); ?></a>
                                    </div>
                                </td>
                                <td><?php echo escHtml( $app->get_name() ); ?></td>
                                <td><?php echo $app->get_author(); ?></td>
                                <td><code><?php echo escHtml( $app->get_type() ); ?></code></td>
                                <td><?php echo escHtml( $app->get_version() ); ?></td>
                                <td><?php echo escHtml( $app->get_slug() ); ?></td>
                                <td><?php echo escHtml( $app->get_status() ); ?></td>
                                <td><?php echo escHtml( $app->get_updated_at()->format( smliser_datetime_format() ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

            <?php smliser_render_pagination( $pagination ); ?>
        <?php endif; ?>
    </div>
</div>