<?php
/**
 * Upload an app.
 *
 * @author Callistus Nwachukwu
 * @var array<string, string> $types App types, type => label.
 * @var int $max_size Largest upload, in bytes.
 * @var string $action The save action's URL.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<p class="dash-lead"><?php smliser_esc_html_e( 'Upload the app\'s zip file. Its version and requirements are read from the package; the rest you can edit once it is uploaded.' ); ?></p>

<form class="dash-form dash-form-wide" method="post" action="<?php echo escUrl( $action ); ?>" enctype="multipart/form-data" data-smliser-form>
	<div class="notice" data-smliser-form-message hidden></div>

	<label class="field">
		<span class="field-label"><?php smliser_esc_html_e( 'Type' ); ?></span>
		<select name="app_type" required>
			<?php foreach ( $types as $type => $label ) : ?>
				<option value="<?php echo escAttr( $type ); ?>"><?php echo escHtml( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>

	<label class="field">
		<span class="field-label"><?php smliser_esc_html_e( 'Name' ); ?></span>
		<input type="text" name="app_name" maxlength="200" required>
		<span class="field-hint"><?php smliser_esc_html_e( 'The app\'s address on this site is made from it. Only an administrator can change the address later.' ); ?></span>
	</label>

	<label class="field">
		<span class="field-label"><?php smliser_esc_html_e( 'Author' ); ?></span>
		<input type="text" name="app_author" maxlength="200" required>
	</label>

	<label class="field">
		<span class="field-label"><?php smliser_esc_html_e( 'Author profile URL' ); ?> <span class="muted"><?php smliser_esc_html_e( '(optional)' ); ?></span></span>
		<input type="url" name="app_author_url" placeholder="https://">
	</label>

	<div class="field">
		<span class="field-label"><?php smliser_esc_html_e( 'Zip file' ); ?></span>
		<input type="file" name="app_zip_file" accept=".zip" required data-smliser-uploader data-max-size="<?php echo escAttr( (string) $max_size ); ?>" data-label="<?php smliser_esc_attr_e( 'Drop the zip file here, or choose it' ); ?>">
	</div>

	<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Upload' ); ?></button>
</form>