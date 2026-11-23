<?php
/**
 * Edit app artifacts.
 * 
 * @author Callistus Nwachukwu
 * 
 * @see \SmartLicenseServer\Admin\ContentHandlers\RepositoryPage::upload_page()
 * @see \SmartLicenseServer\Admin\ContentHandlers\RepositoryPage::edit_page()
 * @var \SmartLicenseServer\Core\Request $request
 * @var array $essential_fields
 * @var string $type
 * @var string $type_title
 * @var \SmartLicenseServer\HostedApps\AbstractHostedApp $app
 * @var array{slug: string, path: string, size: int, mtime: int, mime_type: string|null, filename: string}[] $app_files
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 * @var \SmartLicenseServer\Admin\ContentHandlers\RepositoryPage $repo_page
 */

use SmartLicenseServer\Utils\Format;

$args = $repo_page->get_menu_args( $request, isset( $app ) ? $app : null );
?>

<div class="application-uploader-page artifact-editor">
    <?php smliser_print_admin_content_header( $args ); ?>
 

    <div class="smliser-app-files-list">
        <div class="notice notice-info">
            <h2>
                <?php printf( smliser__( 'Manage all distributable artifacts for <a href="%s">%s</a>' ),
                	$urlmanager->admin_repo_url( 'view', ['app_id' => $app->get_id(), 'type' => $app->get_type()] ), $app->get_name() ); ?>
            </h2>
            <em>
                <?php /* translators: %s: the app type, e.g. plugin. */ printf( smliser_esc_html__( 'A distributable artifact is a packaged version of your %s that is ready for deployment or sharing.' ), '<strong>' . escHtml( $type ) . '</strong>' ); ?> 
            </em>
        </div>

        <ul class="smliser-app-artifacts">
            <?php
			$new_upload_config = rawurlencode( smliser_json_encode_attr( [
				'app_slug' => $app->get_slug(),
				'app_type' => $app->get_type(),
				'isNew'    => true
			] ) );
foreach( $app_files as $file_data ) :
	$download_url = 'main' === $file_data['slug'] ? $app->get_download_url() : $urlmanager->app_artifact_download_url( $app->get_type(), $app->get_slug(), $file_data['filename'] );
	$config       = rawurlencode( smliser_json_encode_attr( [
		'filename' => $file_data['filename'],
		'slug'     => $file_data['slug'],
		'size'     => $file_data['size'],
		'app_slug' => $app->get_slug(),
		'app_type' => $app->get_type(),
		'isNew'    => false,
		'url'      => $download_url
	] ) );
	?>
                <li class="smliser-app-artifacts_item <?php echo escHtml( str_replace( '.', '-', $file_data['slug'] ) ); ?>">
                    <div class="smliser-app-artifacts_item-heading">
                        <span>
                            <strong><?php smliser_esc_html_e( 'Name:' ); ?></strong> <?php echo escHtml( $file_data['filename'] ); ?>
                        </span>
                        
                        <div class="smliser-app-artifacts_item-buttons">
                            <?php if ( 'main' === $file_data['slug'] ) : ?>
                                <span class="smliser-provider-card__badge"><?php printf( smliser__( 'Main %s file' ), $app->get_type() ) ?></span>
                            <?php else: ?>     
                                <button title="<?php smliser_esc_attr_e( 'Edit artifact' ); ?>" class="smliser-edit-artifact" data-config="<?php echo escAttr( $config ); ?>"> <i class="ti ti-edit"></i></button>
                                <button title="<?php smliser_esc_attr_e( 'Delete artifact' ); ?>" class="smliser-delete-artifact" data-config="<?php echo escAttr( $config ); ?>"> <i class="ti ti-trash"></i></button>
                            <?php endif; ?>
                            <button title="<?php smliser_esc_attr_e( 'Download artifact' ); ?>" class="smliser-download-artifact" data-config="<?php echo escAttr( $config ); ?>"> <i class="ti ti-download"></i></button>
                        </div>
                    </div>
                    
                    <div class="smliser-app-artifacts_item-info">
                        <table class="striped">
                            <tbody>
                                <tr>
                                    <th><?php smliser_esc_html_e( 'Size:' ); ?></th>
                                    <td><?php echo escHtml( Format::bytes( $file_data['size'] ) ) ?></td>
                                </tr>
                                <tr>
                                    <th><?php smliser_esc_html_e( 'Mime Type:' ); ?></th>
                                    <td><?php echo escHtml( $file_data['mime_type'] ) ?></td>
                                </tr>
                                <tr>
                                    <th><?php smliser_esc_html_e( 'Download URL:' ); ?></th>
                                    <td>
                                        <span title="<?php echo escAttr( $download_url ) ?>">
                                            <?php echo escHtml( Format::truncate( $download_url->url(), 30 ) ) ?>    
                                        </span>
                                        <i class="ti ti-copy smliser-click-to-copy" title="<?php smliser_esc_attr_e( 'Click to copy' ); ?>" data-copy-value="<?php echo escUrl( $download_url->url() ) ?>"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php smliser_esc_html_e( 'Last Updated:' ); ?></th>
                                    <td><?php echo escHtml( Format::time_ago( $file_data['mtime'] ) ) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <button 
            class="smliser-nav-btn button smliser-add-new-artifact"
            title="<?php smliser_esc_attr_e( 'Add new artifact' ); ?>" data-config="<?php echo escAttr( $new_upload_config ); ?>"
            >
            <i class="ti ti-add"></i> <?php smliser_esc_html_e( 'Add Artifact' ); ?>
        </button>
    </div>
</div>