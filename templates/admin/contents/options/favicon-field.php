<?php
/**
 * Site favicon field of the general settings form.
 *
 * The file input and the "use the default favicon" flag are posted with the
 * rest of the form; assets/js/admin/core.js (initFaviconField) previews the
 * chosen file and handles the buttons.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 * @var array{
 *     label: string,
 *     help: string,
 *     upload_name: string,
 *     remove_name: string,
 *     url: string,
 *     default_url: string,
 *     is_custom: bool,
 *     accept: string,
 *     max_bytes: int
 * } $field See OptionsPage::favicon_field().
 */

defined( 'SMLISER_ROOT' ) || exit;

$input_id = $field['upload_name'];
?>
<div
	class="smliser-form-label-row smliser-favicon-field"
	data-default-src="<?php echo escUrl( $field['default_url'] ); ?>"
	data-max-size="<?php echo escAttr( (string) $field['max_bytes'] ); ?>"
	role="group"
	aria-labelledby="<?php echo escAttr( $input_id ); ?>-label"
>
	<span class="smliser-field-label-text">
		<span id="<?php echo escAttr( $input_id ); ?>-label"><?php echo escHtml( $field['label'] ); ?></span>
		<?php smliser_render_field_help_icon( $field['help'], $input_id ); ?>
	</span>

	<div class="smliser-favicon-field_body">
		<img
			class="smliser-favicon-field_preview"
			src="<?php echo escUrl( $field['url'] ); ?>"
			alt="<?php smliser_esc_attr_e( 'Site favicon preview' ); ?>"
			width="32"
			height="32"
		>

		<input
			type="file"
			name="<?php echo escAttr( $field['upload_name'] ); ?>"
			id="<?php echo escAttr( $input_id ); ?>"
			accept="<?php echo escAttr( $field['accept'] ); ?>"
			class="smliser-hide"
		>
		<input type="hidden" name="<?php echo escAttr( $field['remove_name'] ); ?>" value="" class="smliser-favicon-field_remove">

		<button type="button" class="button smliser-favicon-field_upload" aria-controls="<?php echo escAttr( $input_id ); ?>">
			<i class="ti ti-upload" aria-hidden="true"></i>
			<span><?php smliser_esc_html_e( 'Upload Favicon' ); ?></span>
		</button>

		<button type="button" class="button smliser-favicon-field_reset<?php echo $field['is_custom'] ? '' : ' smliser-hide'; ?>">
			<i class="ti ti-restore" aria-hidden="true"></i>
			<span><?php smliser_esc_html_e( 'Use Default Favicon' ); ?></span>
		</button>

		<span class="smliser-favicon-field_status" aria-live="polite"></span>
	</div>
</div>
