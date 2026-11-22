<?php
/**
 * Software Monetization admin page template
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Admin
 * @since 0.0.5 
 * @var SmartLicenseServer\Core\URL $url
 * @var SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Monetization\Monetization|null $monetization
 * @var array<int|string, class-string<\SmartLicenseServer\Monetization\Providers\MonetizationProviderInterface>|\SmartLicenseServer\Monetization\Providers\MonetizationProviderInterface> $providers
 * @var \SmartLicenseServer\HostedApps\AbstractHostedApp|null $app
 * @var \SmartLicenseServer\Admin\ContentHandlers\RepositoryPage $repo_page
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 * @var array<string, array{name: string, fields: array<string, array<string, mixed>>}> $provider_fields
 *      Each provider's tier fields for this app, keyed by provider ID.
 */

defined( 'SMLISER_ROOT' ) || exit; ?>

<div class="smliser-admin-repository-template repo-page">
    <?php smliser_print_admin_content_header( $repo_page->get_menu_args( $request, $app ) ); ?>
    <?php if ( empty( $app ) ) : ?>
        <?php echo
			smliser_not_found_container(
				sprintf(
					smliser__( 'This app does not exist in the repository <a href="%s">Back</a>' ),
					$urlmanager->admin_repo_url()
				)
			);
    	?>
    <?php else : ?>
        <div class="smliser-monetization-ui">
            <div class="smliser-monetization-ui__software-info">

                <div class="smliser-monetization-ui__software-essentials">
                    <h2><?php smliser_esc_html_e( 'Application Details' ); ?></h2>
                    <table class="widefat striped">
                        <tr>
                            <th><?php smliser_esc_html_e( 'App Name:' ); ?></th>
                            <td><?php echo escHtml( $app->get_name() ); ?></td>
                        </tr>
                        
                        <tr>
                            <th><?php smliser_esc_html_e( 'App Type:' ); ?></th>
                            <td><?php echo escHtml( \SmartLicenseServer\HostedApps\HostedAppsRegistry::instance()->get_app_type_label( $app->get_type() ) ); ?></td>
                        </tr>

                        <tr>
                            <th><?php smliser_esc_html_e( 'Version:' ); ?></th>
                            <td><?php echo escHtml( $app->get_version() ); ?></td>
                        </tr>
                        <tr>
                            <th><?php smliser_esc_html_e( 'Description:' ); ?></th>
                            <td><?php echo sanitize_html( $app->get_short_description() ); ?></td>
                        </tr>
                        <tr>
                            <th><?php smliser_esc_html_e( 'Author:' ); ?></th>
                            <td><?php echo escHtml( $app->get_author() ); ?></td>
                        </tr>
                        <tr>
                            <th><?php smliser_esc_html_e( 'Monetization Status:' ); ?></th>
                            <td>
                                <?php
    							smliser_render_toggle_switch( [
    								'id'                   => 'monetization_enabled',
    								'name'                 => 'enabled',
    								'value'                => $monetization->is_enabled() ? 1 : 0,
    								'data-action'          => 'toggleMonetization',
    								'data-monetization-id' => intval( $monetization->get_id() ),
    							] );
    	?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="smliser-monetization-ui__software-tiers">
                    <h2><?php smliser_esc_html_e( 'Tiers' ); ?></h2>
                    <table class="tier-list widefat striped">
                        <?php if ( empty( $monetization->get_tiers() ) ) : ?>
                            <tr>
                                <td><?php smliser_esc_html_e( 'No pricing tiers has been set' ); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ( $monetization->get_tiers() as $tier ) : ?>
                                <?php
    							$features_str = implode( ', ', $tier->get_features() );
                            	$max_sites    = $tier->get_max_sites();

                            	// Encode the tier into JSON for JS handling
                            	$tier_json = smliser_safe_json_encode( [
                            		'id'              => $tier->get_id(),
                            		'name'            => $tier->get_name(),
                            		'term_unit'       => $tier->get_term_unit(),
                            		'term_length'     => $tier->get_term_length(),
                            		'max_sites'       => $max_sites,
                            		'features'        => $tier->get_features(),
                            		'provider_id'     => \SmartLicenseServer\Monetization\MonetizationRegistry::parse_provider_id( $tier->get_provider_id() )['provider'],
                            		'provider_data'   => $tier->get_provider_data(),
                            		'status'          => $tier->get_status(),
                            		'monetization_id' => $tier->get_monetization_id(),
                            	] );
                            	?>
                                <tr class="tier-row-<?php echo escAttr( $tier->get_id() ) ?>">
                                    <th>
                                        <?php echo escHtml( $tier->get_name() ); ?>
                                        <?php if ( ! $tier->is_active() ) : ?>
                                            <br><small><?php smliser_esc_html_e( '(Archived)' ); ?></small>
                                        <?php endif; ?>
                                    </th>
                                    <td>
                                        <div class="smliser-pricing-tier-info" data-json='<?php echo escAttr( $tier_json ); ?>'>
                                            <p><strong><?php smliser_esc_html_e( 'Term:' ); ?></strong> <?php echo escHtml( $tier->get_term_label() ); ?></p>
                                            <p><strong><?php smliser_esc_html_e( 'Sites:' ); ?></strong> <?php echo escHtml( \SmartLicenseServer\Monetization\PricingTier::UNLIMITED_SITES === $max_sites ? 'Unlimited' : (string) $max_sites ); ?></p>
                                            <p><strong><?php smliser_esc_html_e( 'Features:' ); ?></strong> <?php echo escHtml( $features_str ?: 'None' ); ?></p>
                                            <?php
                            				$source   = \SmartLicenseServer\Monetization\MonetizationRegistry::parse_provider_id( $tier->get_provider_id() );
                            	$provider = $provider_fields[ $source['provider'] ]['name'] ?? $source['provider'];
                            	?>
                                            <p><strong><?php smliser_esc_html_e( 'Provider:' ); ?></strong> <?php echo escHtml( '' === $source['provider'] ? smliser__( 'None (issued by an administrator)' ) : $provider . ( '' !== $source['channel'] ? ' » ' . $source['channel'] : '' ) ); ?></p>

                                            <div class="smliser-pricing-tier-actions">
                                                <button type="button" class="button smliser-tier-edit smliser-nav-btn" data-action="editTier">
                                                    <span class="ti ti-edit"></span> <?php smliser_esc_html_e( 'Edit' ); ?>
                                                </button>

                                                <button type="button" class="button smliser-tier-delete smliser-nav-btn" data-action="deleteTier">
                                                    <span class="ti ti-trash"></span> <?php smliser_esc_html_e( 'Delete' ); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>
                    </table>

                </div>
            </div>

            <div class="smliser-monetization-ui__monetization-providers">
                <div class="smliser-monetization-ui__providiers-list">
                    <div class="monetization-providers_header">
                        <h2><?php smliser_esc_html_e( 'Monetization Providers' ); ?></h2>
                        <button type="button" class="button action smliser-nav-btn" id="add-pricing-tier" data-command="addNewTier"><span class="ti ti-plus"></span> <?php smliser_esc_html_e( 'Pricing Tier' ); ?></button>
                    </div>
                    <?php if ( empty( $providers ) ) : ?>
                        <?php echo smliser_not_found_container( smliser__( 'No monetization provider found' ) ); ?>
                    <?php else: ?>
                        <?php foreach( $provider_fields as $provider_id => $provider ): ?>
                            <div class="smliser-monetization-ui__monetization-provider">
                                <p><?php smliser_esc_html_e( 'Name:' ); ?> <strong><?php echo escHtml( $provider['name'] ); ?></strong></p>
                                <p><?php smliser_esc_html_e( 'ID:' ); ?> <code><?php echo escHtml( $provider_id ); ?></code></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <div id="smliser-app-monetization-editor" class="smliser-hide">
                <em><?php smliser_esc_html_e( 'A pricing tier represents a specific license option for a hosted application.' ); ?></em>
                <form id="tier-form" class="smliser-admin-modal_content-form">
                    <input type="hidden" name="action" value="">
                    <input type="hidden" name="monetization_id" value="<?php echo intval( $monetization->get_id() ); ?>">
                    <input type="hidden" name="app_id" value="<?php echo intval( $app->get_id() ); ?>">
                    <input type="hidden" name="app_type" value="<?php echo escAttr( $monetization->get_app_type() ); ?>">
                    <input type="hidden" name="tier_id">
                    <label for="tier_name"><?php smliser_esc_html_e( 'Tier Name:' ); ?>
                        <input type="text" name="tier_name" id="tier_name" field-name="Tier Name">
                    </label>
                    <label for="term_unit"><?php smliser_esc_html_e( 'License Term:' ); ?>
                        <select name="term_unit" id="term_unit" field-name="License Term">
                            <?php foreach ( \SmartLicenseServer\Monetization\PricingTier::get_term_units() as $unit ) : ?>
                                <option value="<?php echo escAttr( $unit ); ?>"><?php echo escHtml( \SmartLicenseServer\Monetization\PricingTier::TERM_LIFETIME === $unit ? 'Lifetime' : ucfirst( $unit ) . '(s)' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label for="term_length"><?php smliser_esc_html_e( 'Term Length:' ); ?>
                        <input type="number" name="term_length" id="term_length" min="1" value="1" placeholder="<?php smliser_esc_attr_e( 'Number of days, months or years' ); ?>">
                    </label>
                    <label for="max_sites"><?php smliser_esc_html_e( 'Maximum Websites:' ); ?>
                        <input type="number" name="max_sites" id="max_sites" min="1" placeholder="<?php smliser_esc_attr_e( 'Leave empty for unlimited' ); ?>">
                    </label>
                    <label for="features"><?php smliser_esc_html_e( 'Features:' ); ?>
                        <textarea name="features" id="features" placeholder="<?php smliser_esc_attr_e( 'example: feature1, feature2, feature3' ); ?>"></textarea>
                    </label>
                    <label for="provider_id"><?php smliser_esc_html_e( 'Monetization Provider:' ); ?>
                        <select name="provider_id" id="provider_id">
                            <option value=""><?php smliser_esc_html_e( 'None (licenses issued by an administrator)' ); ?></option>
                            <?php foreach( $provider_fields as $provider_id => $provider ) : ?>
                                <option value="<?php echo escAttr( $provider_id ) ?>"><?php echo escHtml( $provider['name'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php foreach ( $provider_fields as $provider_id => $provider ) : ?>
                        <div class="smliser-tier-provider-fields smliser-hide" data-provider="<?php echo escAttr( $provider_id ); ?>">
                            <?php foreach ( $provider['fields'] as $key => $field ) :
                            	$field_id = sprintf( 'provider_%s_%s', $provider_id, $key );
                            	$name     = sprintf( 'provider_data[%s][%s]', $provider_id, $key );
                            	$required = ! empty( $field['required'] ) ? ' data-required="1"' : '';
                            	?>
                                <label for="<?php echo escAttr( $field_id ); ?>"><?php echo escHtml( $field['label'] ?? $key ); ?>:
                                    <?php if ( 'select' === ( $field['type'] ?? 'text' ) ) : ?>
                                        <select name="<?php echo escAttr( $name ); ?>" id="<?php echo escAttr( $field_id ); ?>" data-key="<?php echo escAttr( $key ); ?>" field-name="<?php echo escAttr( $field['label'] ?? $key ); ?>"<?php echo $required; ?>>
                                            <option value=""><?php smliser_esc_html_e( '-- Choose --' ); ?></option>
                                            <?php foreach ( (array) ( $field['options'] ?? [] ) as $value => $label ) : ?>
                                                <option value="<?php echo escAttr( (string) $value ); ?>"><?php echo escHtml( (string) $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ( 'textarea' === ( $field['type'] ?? 'text' ) ) : ?>
                                        <textarea name="<?php echo escAttr( $name ); ?>" id="<?php echo escAttr( $field_id ); ?>" data-key="<?php echo escAttr( $key ); ?>" field-name="<?php echo escAttr( $field['label'] ?? $key ); ?>" rows="5"<?php echo $required; ?>></textarea>
                                    <?php else : ?>
                                        <input type="<?php echo escAttr( $field['type'] ?? 'text' ); ?>" name="<?php echo escAttr( $name ); ?>" id="<?php echo escAttr( $field_id ); ?>" data-key="<?php echo escAttr( $key ); ?>" field-name="<?php echo escAttr( $field['label'] ?? $key ); ?>"<?php echo $required; ?>>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $field['description'] ) ) : ?>
                                        <small><?php echo escHtml( $field['description'] ); ?></small>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </form>
                <div class="smliser-spinner"></div>
            </div>
        </div>
    <?php endif; ?>
</div>