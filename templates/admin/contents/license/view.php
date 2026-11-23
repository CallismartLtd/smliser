<?php
/**
 *  Admin License details page.
 * 
 * @author Callistus.
 * @package Smliser\templates.
 * @since 0.2.0
 * @var \SmartLicenseServer\Monetization\License|null $license
 * @var \SmartLicenseServer\HostedApps\AbstractHostedApp|null $licensed_app
 * @var int $license_id
 * @var string $licensee
 * @var \SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 * @var \SmartLicenseServer\Admin\ContentHandlers\LicensePage $page_handler
 * @var int $total_logs
 */
namespace SmartLicenseServer\Admin\ContentHandlers;

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Monetization\License;

defined( 'SMLISER_ROOT' ) || exit;

$download_url = $urlmanager->admin_document_download_url( $license_id );
$args         = $page_handler->get_menu_args( $request );

if ( $license ) {
	\array_unshift(
		$args['actions'],
		[
			'title' => smliser__( 'View logs for this license' ),
			'label' => smliser__( 'Logs' ),
			'url'   => $urlmanager->admin_license_page_url( 'logs', ['filterBy' => $license_id] ),
			'icon'  => 'ti ti-logs'
		],

		[
			'title' => smliser__( 'Edit License' ),
			'label' => smliser__( 'Edit license' ),
			'url'   => $urlmanager->admin_license_page_url( 'edit', ['id' => $license_id] ),
			'icon'  => 'ti ti-edit'
		],
		
		[
			'title' => smliser__( 'Download License Document' ),
			'label' => smliser__( 'Download Document' ),
			'url'   => $download_url,
			'icon'  => 'ti ti-download'
		],

		[
			'title'      => smliser__( 'Generate license download token' ),
			'label'      => smliser__( 'Generate Token' ),
			'url'        => '#',
			'class'      => 'smliser-generate-download-token-btn',
			'icon'       => 'ti ti-cloud',
			'attributes' => [
				'aria-label'    => smliser__( 'Generate a one-time download token for this license.' ),
				'aria-role'     => 'button',
				'aria-haspopup' => 'dialog',
				'disabled'      => \sprintf( '%s', ! $license->is_issued() ? 'disabled' : '' ),
				'data-args'     => \smliser_json_encode_attr( [
					'license_id' => $license_id,
					'app_name'   => $licensed_app?->get_name() ?? '',
					'app_type'   => $licensed_app?->get_type() ?? '',
					'app_slug'   => $licensed_app?->get_slug() ?? '',
					'is_issued'  => $license->is_issued(),
				] ),
			]
		],

		[
			'title'      => smliser__( 'Delete License' ),
			'label'      => smliser__( 'Delete license' ),
			'url'        => '#',
			'class'      => 'smliser-license-delete-btn',
			'icon'       => 'ti ti-trash',
			'attributes' => [
				'aria-label' => smliser__( 'Delete this license permanently.' ),
				'disabled'   => empty( $license ),
				'id'         => 'smliser-license-delete-button'
			]
		]
	);
}

$public_download_url = $urlmanager->document_download_url( $license ? $license->get_id() : 0 );
?>

<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $args ); ?>
    <?php if ( empty( $license ) ) : ?>
        
        <?php echo  \smliser_not_found_container(
        	\sprintf(
        		smliser__( 'Invalid or deleted license is "%s". <a href="%s"> <i class="ti ti-arrow-narrow-left"></i> Go Back</a>' ),
        		$license_id,
        		$urlmanager->admin_license_page_url()->url(),
        	)
        );?>
    
    <?php else: ?>
        <div class="smliser-admin-view-page-wrapper">
            <div class="smliser-admin-view-page-header"> 
                <div class="smliser-admin-view-page-header-child">
                    <h2><?php smliser_esc_html_e( 'Overview' ); ?></h2>
                    <p><i class="ti ti-database"></i> <?php smliser_esc_html_e( 'License ID:' ); ?> <?php echo escHtml( intval( $license->get_id() ) ) ?></p>
                    <p><i class="ti ti-circle-check"></i> <?php smliser_esc_html_e( 'Status:' ); ?> <?php echo escHtml( $license->get_status() ) ?></p>
                    <p><i class="ti ti-license"></i> <?php smliser_esc_html_e( 'Licensee:' ); ?> <?php echo escHtml( $licensee ?: 'N/A' ) ?></p>
                </div>

                <div class="smliser-admin-view-page-header-child">
                    <h2><?php smliser_esc_html_e( 'Statistics' ); ?></h2>
                    <p><i class="ti ti-app-window"></i> <?php smliser_esc_html_e( 'Max Allowed Domains:' ); ?> <?php echo escHtml( $license->get_max_allowed_domains() ) ?></p>
                    <p><i class="ti ti-dice-6"></i> <?php smliser_esc_html_e( 'Total Domains Activated:' ); ?> <span id="total-active-domain"><?php echo intval( $license->get_total_active_domains() )?></span></p>
                    <p><i class="ti ti-logs"></i> <?php smliser_esc_html_e( 'Total Logs:' ); ?> <?php echo escHtml( $total_logs ) ?></p>
                </div>
                
                <div class="smliser-admin-view-page-header-child">
                    <h2><?php smliser_esc_html_e( 'Operations' ); ?></h2>
                    <p><i class="ti ti-calendar"></i> <?php smliser_esc_html_e( 'Last Updated:' ); ?> <?php echo escHtml( $license->get_updated_at()?->format( smliser_datetime_format() ) ?? 'N/A' )?></p>
                    <p><i class="ti ti-calendar"></i> <?php smliser_esc_html_e( 'Date Created:' ); ?> <?php echo escHtml( $license->get_created_at()?->format( smliser_datetime_format() ) ?? 'N/A' ) ?></p>
                    <p><i class="ti ti-apps"></i> <?php smliser_esc_html_e( 'App Attribute:' ); ?> <?php echo escHtml( $license->get_app_prop() ?: 'N/A' ) ?></p>
                </div>

            </div>

            <div class="smliser-admin-view-page-body">
                <h3><?php smliser_esc_html_e( 'License Details' ); ?></h3>
                <table class="widefat smliser-license-table">
                    <tbody>
    
                        <tr>
                            <th><?php smliser_esc_html_e( 'Is issued' ); ?></th>
                            <td>
                                <?php if ( $license->is_issued() ) : ?>
                                    <span class="ti ti-circle-check"></span>
                                <?php else: ?>
                                    <span class="ti ti-ban"></span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th><?php smliser_esc_html_e( 'App Info' ); ?></th>
                            <td>
                                <?php if ( $license->is_issued() ) : ?>
                                    <a href="<?php echo escUrl( $urlmanager->admin_repo_url( 'view', ['app_id' => $licensed_app->get_id(), 'type' => $licensed_app->get_type()] )->url() ); ?>">
                                        <?php echo escHtml( $licensed_app->get_name() ); ?> » <?php printf( '%s/%s', escHtml( $licensed_app->get_type() ), escHtml( $licensed_app->get_slug() ) ) ?>
                                    </a>
                                <?php else : ?>
                                    <span>N/L</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th><?php smliser_esc_html_e( 'Document Download URL(public)' ); ?></th>
                            <td>
                                <span><?php echo escHtml( $public_download_url ); ?></span>
                                <i class="ti ti-copy smliser-click-to-copy" title="<?php smliser_esc_attr_e( 'Copy' ); ?>" data-copy-value="<?php echo escAttr( $public_download_url ); ?>"></i>
                            </td>
                        </tr>

                        <tr>
                            <th><?php smliser_esc_html_e( 'Service ID' ); ?></th>
                            <td><span class="smliser-click-to-copy" title="<?php smliser_esc_attr_e( 'Copy' ); ?>" data-copy-value="<?php echo escAttr( $license->get_service_id() ); ?>"><?php echo escHtml( $license->get_service_id() ); ?></span></td>
                        </tr>
                        
                        <tr>
                            <th><?php smliser_esc_html_e( 'License Key' ); ?></th>
                            <td>
                                <div class="smliser-license-obfuscation">
                                    <div class="smliser-license-obfuscation_data">
                                        <span class="smliser-license-input">
                                            <input type="text" 
                                                id="<?php echo escHtml( $license->get_id() ); ?>" 
                                                value="<?php echo escHtml( $license->get_license_key() ); ?>" 
                                                readonly 
                                                class="smliser-license-text"
                                            />
                                            <span class="ti ti-copy copy-key smliser-tooltip" title="<?php smliser_esc_attr_e( 'Copy license key' ); ?>"></span>
                                        </span>

                                        <span class="smliser-obfuscated-license-text">
                                            <?php echo escHtml( $license->get_partial_key() ); ?>
                                        </span>
                                    </div>

                                    <input type="checkbox" 
                                        id="toggle-<?php echo escHtml( $license->get_id() ); ?>" 
                                        class="smliser-licence-key-visibility-toggle smliser-tooltip" 
                                        title="<?php smliser_esc_attr_e( 'Toggle visibility' ); ?>" 
                                    />
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <th><?php smliser_esc_html_e( 'Start Date' ); ?></th>
                            <td><?php echo escHtml( $license->get_start_date()?->format( \smliser_datetime_format() ) ?? 'N/A' ); ?></td>
                        </tr>

                        <tr>
                            <th><?php smliser_esc_html_e( 'End Date' ); ?></th>
                            <td><?php echo escHtml( $license->get_end_date()?->format( \smliser_datetime_format() ) ?? 'N/A' ); ?></td>
                        </tr>

                        <tr>
                            <th><?php smliser_esc_html_e( 'Activated on' ); ?></th>
                            <td><?php format_active_domains( $license ); ?></td>
                        </tr>

                        <tr>
                            <th><?php smliser_esc_html_e( 'Alert' ); ?></th>
                            <td><?php print_license_alert( $license ) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
function format_active_domains( License $license ) {
	$all_domains = $license->get_active_domains( 'edit' );
	$all_origins = [];

	foreach( $all_domains as $domain ) {
		$all_origins[] = $domain['origin'] ?? '';
	}

	$all_origins = array_filter( $all_origins );


	$html = '<div class="smliser-all-license-domains">';

	if ( empty( $all_origins ) ) {
		$html .= '<span class="not-found">No active domain found.</span>';
	} else {
		foreach ( $all_origins as $origin ) {

			$url = URL::from( $origin );
		   
			$html .= sprintf(
				'<div class="smliser-all-license-domains_domain" data-domain-value="%1$s">
                    <a href="%2$s" target="_blank">%1$s
                        <i class="ti ti-external-link"></i>
                    </a>
                    <span class="domain-actions">
                        <i class="ti ti-trash remove"></i>
                    </span>
                </div>',
				$url->get_host(),
				$url->url()
			);
		}
	}

	$html .= '</div>';
	
	echo $html;
}

/**
 * Print possible issues with a license.
 *
 * @param License $license License instance.
 * @return void
 */
function print_license_alert( License $license ) : void {

	$messages = [];

	// Activation / serving error.
	$activation_error = $license->can_serve_license( $license->get_app_id() );

	if ( $activation_error instanceof Exception ) {
		$messages[] = [
			'type'    => 'error',
			'message' => $activation_error->get_error_message(),
		];
	}

	// Max allowed domains reached.
	if ( $license->has_reached_max_allowed_domains() ) {
		$messages[] = [
			'type'    => 'warning',
			'message' => __( 'Maximum allowed domains has been reached', 'smart-license-server' ),
		];
	}

	// Max allowed domains exceeded.
	$max_allowed_domains = $license->get_max_allowed_domains( 'total' );
	$total_active        = $license->get_total_active_domains();

	if ( -1 !== $max_allowed_domains && $total_active > $max_allowed_domains ) {
		$messages[] = [
			'type'    => 'error',
			'message' => __( 'Maximum allowed domains has been exceeded', 'smart-license-server' ),
		];
	}

	if ( empty( $messages ) ) {
		return;
	}

	printf(
		'<div class="smliser-license-alerts">%s</div>',
		implode(
			'',
			array_map(
				static function ( array $item ) {
					return sprintf(
						'<div class="smliser-alert smliser-alert-%1$s"><span class="smliser-alert-message">%2$s</span></div>',
						escAttr( $item['type'] ),
						escHtml( $item['message'] )
					);
				},
				$messages
			)
		)
	);

}
