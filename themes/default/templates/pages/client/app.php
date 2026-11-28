<?php
/**
 * One app of the current owner: its details, manifest, a new version, its
 * images, artifacts, figures and status.
 *
 * @author Callistus Nwachukwu
 * @var array{id: int, type: string, type_label: string, name: string, slug: string, version: string,
 *     status: string, status_label: string, created: string, updated: string, public_url: string,
 *     download_url: string} $app
 * @var list<array{label: string, value: string, copy: bool}> $info
 * @var list<array{name: string, label: string, value: string, type: string, required: bool, hint: string}> $fields
 * @var string $manifest The app.json, pretty-printed.
 * @var array<string, bool> $can Each action of OwnerAppScope::ACTIONS the user may take.
 * @var array<string, string> $statuses Statuses the owner may choose, status => label.
 * @var list<array{type: string, label: string, hint: string, items: list<array{name: string, url: string}>}> $assets
 * @var list<array{filename: string, main: bool, size: string, mime_type: string, updated: string, download_url: string}> $artifacts
 * @var list<array{icon: string, label: string, value: string, color: string, subtitle?: string, highlight?: bool}> $stats
 * @var int $max_size Largest upload, in bytes.
 * @var string $list_url The list of apps.
 * @var array<string, string> $actions
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$tones = [
	'active'    => 'success',
	'suspended' => 'failure',
	'trash'     => 'failure',
];

$in_trash  = 'trash' === $app['status'];
$suspended = 'suspended' === $app['status'];
$hidden    = static function ( array $fields ) : void {
	foreach ( $fields as $name => $value ) {
		printf( '<input type="hidden" name="%s" value="%s">', escAttr( $name ), escAttr( (string) $value ) );
	}
};
$app_ref = [ 'app_type' => $app['type'], 'app_slug' => $app['slug'] ];
?>
<div class="record">
	<div class="record-head">
		<div>
			<h3><?php echo escHtml( $app['name'] ); ?></h3>
			<span class="muted"><?php echo escHtml( $app['type_label'] ); ?></span>
		</div>
		<span class="badge" data-tone="<?php echo escAttr( $tones[ $app['status'] ] ?? 'neutral' ); ?>"><?php echo escHtml( $app['status_label'] ); ?></span>
	</div>

	<dl class="record-facts">
		<div>
			<dt><?php smliser_esc_html_e( 'Slug' ); ?></dt>
			<dd><code><?php echo escHtml( $app['slug'] ); ?></code></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Version' ); ?></dt>
			<dd><?php echo escHtml( '' !== $app['version'] ? $app['version'] : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Uploaded' ); ?></dt>
			<dd><?php echo escHtml( $app['created'] ); ?></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Updated' ); ?></dt>
			<dd><?php echo escHtml( $app['updated'] ); ?></dd>
		</div>
		<?php foreach ( $info as $fact ) : ?>
			<div>
				<dt><?php echo escHtml( $fact['label'] ); ?></dt>
				<dd><?php echo $fact['copy'] ? '<code>' . escHtml( '' !== $fact['value'] ? $fact['value'] : '—' ) . '</code>' : escHtml( '' !== $fact['value'] ? $fact['value'] : '—' ); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>

	<div class="record-actions">
		<a class="btn btn-small" href="<?php echo escUrl( $list_url ); ?>">
			<i class="ti ti-arrow-left" aria-hidden="true"></i>
			<span><?php smliser_esc_html_e( 'Your apps' ); ?></span>
		</a>
		<?php if ( 'active' === $app['status'] ) : ?>
			<a class="btn btn-small" href="<?php echo escUrl( $app['public_url'] ); ?>" target="_blank" rel="noopener">
				<i class="ti ti-external-link" aria-hidden="true"></i>
				<span><?php smliser_esc_html_e( 'Public page' ); ?></span>
			</a>
		<?php endif; ?>
		<?php if ( '' !== $app['download_url'] ) : ?>
			<a class="btn btn-small" href="<?php echo escUrl( $app['download_url'] ); ?>">
				<i class="ti ti-download" aria-hidden="true"></i>
				<span><?php smliser_esc_html_e( 'Download package' ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</div>

<?php if ( $suspended ) : ?>
	<div class="notice" data-state="error" role="status"><?php smliser_esc_html_e( 'This app is suspended by the site\'s administrators, so it is not offered. Contact them to have the suspension lifted.' ); ?></div>
<?php elseif ( $in_trash ) : ?>
	<div class="notice" data-state="error" role="status"><?php smliser_esc_html_e( 'This app is in the trash. It is deleted for good after a while, unless you restore it.' ); ?></div>
<?php endif; ?>

<?php if ( [] !== $stats ) : ?>
	<section class="dash-section" aria-labelledby="stats-title">
		<h2 id="stats-title"><?php smliser_esc_html_e( 'Performance Metrics (30 days)' ); ?></h2>
		<dl class="record-facts app-stats">
			<?php foreach ( $stats as $stat ) : ?>
				<div<?php echo ! empty( $stat['highlight'] ) ? ' class="app-stat-highlight"' : ''; ?>>
					<dt><i class="<?php echo escAttr( $stat['icon'] ); ?>" aria-hidden="true"></i> <?php echo escHtml( $stat['label'] ); ?></dt>
					<dd><?php echo escHtml( $stat['value'] ); ?><?php if ( ! empty( $stat['subtitle'] ) ) : ?> <span class="muted"><?php echo escHtml( $stat['subtitle'] ); ?></span><?php endif; ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</section>
<?php endif; ?>

<?php if ( $can['update'] && ! $in_trash ) : ?>
	<section class="dash-section" aria-labelledby="details-title">
		<h2 id="details-title"><?php smliser_esc_html_e( 'Details' ); ?></h2>
		<p class="muted"><?php smliser_esc_html_e( 'Upload a new zip file to publish a new version; its version and requirements are read from the package.' ); ?></p>
		<form class="dash-form dash-form-wide" method="post" action="<?php echo escUrl( $actions['save'] ); ?>" enctype="multipart/form-data" data-smliser-form>
			<?php $hidden( [ 'app_type' => $app['type'], 'app_id' => $app['id'] ] ); ?>
			<div class="notice" data-smliser-form-message hidden></div>

			<?php foreach ( $fields as $field ) : ?>
				<label class="field">
					<span class="field-label"><?php echo escHtml( $field['label'] ); ?></span>
					<input type="<?php echo escAttr( $field['type'] ); ?>" name="<?php echo escAttr( $field['name'] ); ?>" value="<?php echo escAttr( $field['value'] ); ?>"<?php echo $field['required'] ? ' required' : ''; ?><?php echo 'url' === $field['type'] ? ' placeholder="https://"' : ''; ?>>
					<?php if ( '' !== $field['hint'] ) : ?>
						<span class="field-hint"><?php echo escHtml( $field['hint'] ); ?></span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>

			<div class="field">
				<span class="field-label"><?php smliser_esc_html_e( 'New version' ); ?> <span class="muted"><?php smliser_esc_html_e( '(optional)' ); ?></span></span>
				<input type="file" name="app_zip_file" accept=".zip" data-smliser-uploader data-max-size="<?php echo escAttr( (string) $max_size ); ?>" data-label="<?php smliser_esc_attr_e( 'Drop the new zip file here, or choose it' ); ?>">
				<span class="field-hint"><?php smliser_esc_html_e( 'Leave empty to keep the current version.' ); ?></span>
			</div>

			<div class="field">
				<span class="field-label">app.json <span class="muted"><?php smliser_esc_html_e( '(optional)' ); ?></span></span>
				<textarea class="app-manifest" rows="10" readonly data-smliser-json-editor data-file-input="app-json-file"
					data-title="<?php smliser_esc_attr_e( 'APP JSON Editor' ); ?>"
					data-description="<?php smliser_esc_attr_e( 'Edit the app\'s manifest. Fields read from the package, such as its version, cannot be overridden here.' ); ?>"
					aria-label="<?php smliser_esc_attr_e( 'Current app.json' ); ?>"><?php echo escHtml( $manifest ); ?></textarea>
				<input type="file" id="app-json-file" name="app_json_file" accept=".json,application/json" data-smliser-uploader data-label="<?php smliser_esc_attr_e( 'Drop an app.json file here, or choose it' ); ?>">
				<span class="field-hint"><?php smliser_esc_html_e( 'Replaces the app\'s manifest.' ); ?></span>
			</div>

			<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Save changes' ); ?></button>
		</form>
	</section>
<?php endif; ?>

<?php if ( [] !== $assets && ! $in_trash && ( $can['upload_assets'] || $can['delete_assets'] ) ) : ?>
	<section class="dash-section" aria-labelledby="images-title">
		<h2 id="images-title"><?php smliser_esc_html_e( 'Images' ); ?></h2>

		<?php foreach ( $assets as $group ) : ?>
			<div class="asset-group">
				<h3 class="dash-subheading"><?php echo escHtml( $group['label'] ); ?></h3>

				<?php if ( [] === $group['items'] ) : ?>
					<p class="muted"><?php smliser_esc_html_e( 'None yet.' ); ?></p>
				<?php else : ?>
					<ul class="asset-grid">
						<?php foreach ( $group['items'] as $item ) : ?>
							<li class="asset-tile">
								<a href="<?php echo escUrl( $item['url'] ); ?>" target="_blank" rel="noopener">
									<img src="<?php echo escUrl( $item['url'] ); ?>" alt="<?php echo escAttr( $item['name'] ); ?>" loading="lazy">
								</a>
								<span class="asset-name"><?php echo escHtml( $item['name'] ); ?></span>
								<?php if ( $can['delete_assets'] ) : ?>
									<form method="post" action="<?php echo escUrl( $actions['asset_delete'] ); ?>" data-smliser-form
										data-confirm="<?php /* translators: %s: file name. */ echo escAttr( sprintf( smliser__( 'Delete %s?' ), $item['name'] ) ); ?>" data-confirm-label="<?php smliser_esc_attr_e( 'Delete' ); ?>" data-confirm-danger>
										<?php $hidden( $app_ref + [ 'asset_name' => $item['name'] ] ); ?>
										<div class="notice" data-smliser-form-message hidden></div>
										<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Delete' ); ?></button>
									</form>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $can['upload_assets'] ) : ?>
					<form class="dash-form dash-form-wide" method="post" action="<?php echo escUrl( $actions['asset_upload'] ); ?>" enctype="multipart/form-data" data-smliser-form>
						<?php $hidden( $app_ref + [ 'asset_type' => $group['type'] ] ); ?>
						<div class="notice" data-smliser-form-message hidden></div>
						<div class="field">
							<?php if ( '' !== $group['hint'] ) : ?>
								<span class="field-hint"><?php echo escHtml( $group['hint'] ); ?></span>
							<?php endif; ?>
							<input type="file" name="asset_file[]" accept="image/png,image/jpeg,image/gif,image/webp" multiple required data-smliser-uploader data-max-size="<?php echo escAttr( (string) $max_size ); ?>" data-label="<?php /* translators: %s: kind of image, e.g. icons. */ echo escAttr( sprintf( smliser__( 'Drop %s images here, or choose them' ), strtolower( $group['label'] ) ) ); ?>">
						</div>
						<button type="submit" class="btn btn-small"><?php /* translators: %s: kind of image, e.g. icons. */ echo escHtml( sprintf( smliser__( 'Add %s' ), strtolower( $group['label'] ) ) ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</section>
<?php endif; ?>

<?php if ( ! $in_trash && ( [] !== $artifacts || $can['upload_artifacts'] ) ) : ?>
	<section class="dash-section" aria-labelledby="artifacts-title">
		<h2 id="artifacts-title"><?php smliser_esc_html_e( 'Artifacts' ); ?></h2>
		<p class="muted"><?php smliser_esc_html_e( 'Files distributed beside the main package, such as checksums, signatures or other builds. The main package changes only with a new version.' ); ?></p>

		<ul class="records">
			<?php foreach ( $artifacts as $artifact ) : ?>
				<li class="record record-compact">
					<div class="record-head">
						<div>
							<h3><code><?php echo escHtml( $artifact['filename'] ); ?></code></h3>
							<span class="muted"><?php /* translators: 1: file size, 2: MIME type, 3: date. */ echo escHtml( sprintf( smliser__( '%1$s · %2$s · updated %3$s' ), $artifact['size'], $artifact['mime_type'], $artifact['updated'] ) ); ?></span>
						</div>
						<?php if ( $artifact['main'] ) : ?>
							<span class="badge" data-tone="neutral"><?php smliser_esc_html_e( 'Main package' ); ?></span>
						<?php endif; ?>
					</div>

					<div class="record-actions">
						<?php if ( '' !== $artifact['download_url'] ) : ?>
							<a class="btn btn-small" href="<?php echo escUrl( $artifact['download_url'] ); ?>">
								<i class="ti ti-download" aria-hidden="true"></i>
								<span><?php smliser_esc_html_e( 'Download' ); ?></span>
							</a>
						<?php endif; ?>

						<?php if ( ! $artifact['main'] && $can['delete_artifacts'] ) : ?>
							<form method="post" action="<?php echo escUrl( $actions['artifact_delete'] ); ?>" data-smliser-form
								data-confirm="<?php /* translators: %s: file name. */ echo escAttr( sprintf( smliser__( 'Delete %s?' ), $artifact['filename'] ) ); ?>" data-confirm-label="<?php smliser_esc_attr_e( 'Delete' ); ?>" data-confirm-danger>
								<?php $hidden( $app_ref + [ 'artifact_filename' => $artifact['filename'] ] ); ?>
								<div class="notice" data-smliser-form-message hidden></div>
								<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Delete' ); ?></button>
							</form>
						<?php endif; ?>
					</div>

					<?php if ( ! $artifact['main'] && $can['edit_artifacts'] ) : ?>
						<details class="artifact-edit">
							<summary><?php smliser_esc_html_e( 'Rename or replace' ); ?></summary>

							<form class="inline-form" method="post" data-method="PUT" action="<?php echo escUrl( $actions['artifact_update'] ); ?>" data-smliser-form>
								<?php $hidden( $app_ref + [ 'artifact_filename' => $artifact['filename'] ] ); ?>
								<label>
									<span class="screen-reader-text"><?php smliser_esc_html_e( 'New file name' ); ?></span>
									<input type="text" name="canonical_filename" value="<?php echo escAttr( $artifact['filename'] ); ?>" required autocomplete="off" spellcheck="false">
								</label>
								<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Rename' ); ?></button>
								<div class="notice" data-smliser-form-message hidden></div>
							</form>

							<form class="dash-form dash-form-wide" method="post" data-method="PUT" action="<?php echo escUrl( $actions['artifact_update'] ); ?>" enctype="multipart/form-data" data-smliser-form>
								<?php $hidden( $app_ref + [ 'artifact_filename' => $artifact['filename'], 'canonical_filename' => $artifact['filename'] ] ); ?>
								<div class="notice" data-smliser-form-message hidden></div>
								<div class="field">
									<input type="file" name="artifact_file" required data-smliser-uploader data-max-size="<?php echo escAttr( (string) $max_size ); ?>" data-label="<?php smliser_esc_attr_e( 'Drop the replacement file here, or choose it' ); ?>">
								</div>
								<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Replace file' ); ?></button>
							</form>
						</details>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $can['upload_artifacts'] ) : ?>
			<h3 class="dash-subheading"><?php smliser_esc_html_e( 'Add an artifact' ); ?></h3>
			<form class="dash-form dash-form-wide" method="post" action="<?php echo escUrl( $actions['artifact_upload'] ); ?>" enctype="multipart/form-data" data-smliser-form>
				<?php $hidden( $app_ref ); ?>
				<div class="notice" data-smliser-form-message hidden></div>
				<label class="field">
					<span class="field-label"><?php smliser_esc_html_e( 'File name' ); ?> <span class="muted"><?php smliser_esc_html_e( '(optional)' ); ?></span></span>
					<input type="text" name="canonical_filename" autocomplete="off" spellcheck="false" placeholder="my-app-1.0.0.zip.sha256">
					<span class="field-hint"><?php smliser_esc_html_e( 'The artifact is saved under this exact name, extension included. Leave it empty to use the uploaded file\'s name.' ); ?></span>
				</label>
				<div class="field">
					<input type="file" name="artifact_file" required data-smliser-uploader data-max-size="<?php echo escAttr( (string) $max_size ); ?>" data-label="<?php smliser_esc_attr_e( 'Drop the artifact here, or choose it' ); ?>">
				</div>
				<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Add Artifact' ); ?></button>
			</form>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( ! $suspended && ( $can['change_status'] || $can['delete'] ) ) : ?>
	<section class="dash-section dash-danger" aria-labelledby="status-title">
		<h2 id="status-title"><?php smliser_esc_html_e( 'Status' ); ?></h2>

		<?php if ( $in_trash ) : ?>
			<div class="record-actions">
				<?php if ( $can['trash'] ) : ?>
					<form method="post" action="<?php echo escUrl( $actions['status'] ); ?>" data-smliser-form>
						<?php $hidden( $app_ref + [ 'app_status' => 'active' ] ); ?>
						<div class="notice" data-smliser-form-message hidden></div>
						<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Restore' ); ?></button>
					</form>
				<?php endif; ?>
				<?php if ( $can['delete'] ) : ?>
					<form method="post" action="<?php echo escUrl( $actions['delete'] ); ?>" data-smliser-form
						data-confirm="<?php /* translators: %s: app name. */ echo escAttr( sprintf( smliser__( 'Delete %s for good? Its files go too, and this cannot be undone.' ), $app['name'] ) ); ?>" data-confirm-label="<?php smliser_esc_attr_e( 'Delete for good' ); ?>" data-confirm-danger>
						<?php $hidden( $app_ref ); ?>
						<div class="notice" data-smliser-form-message hidden></div>
						<button type="submit" class="btn"><?php smliser_esc_html_e( 'Delete for good' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php if ( $can['change_status'] ) : ?>
				<p class="muted"><?php smliser_esc_html_e( 'An inactive app is not offered for download or sale.' ); ?></p>
				<form class="inline-form" method="post" action="<?php echo escUrl( $actions['status'] ); ?>" data-smliser-form>
					<?php $hidden( $app_ref ); ?>
					<label>
						<span class="screen-reader-text"><?php smliser_esc_html_e( 'Status' ); ?></span>
						<select name="app_status">
							<?php foreach ( $statuses as $key => $label ) : ?>
								<option value="<?php echo escAttr( $key ); ?>"<?php echo $key === $app['status'] ? ' selected' : ''; ?>><?php echo escHtml( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Change status' ); ?></button>
					<div class="notice" data-smliser-form-message hidden></div>
				</form>
			<?php endif; ?>
			<?php if ( $can['trash'] ) : ?>
				<div class="record-actions">
					<form method="post" action="<?php echo escUrl( $actions['status'] ); ?>" data-smliser-form
						data-confirm="<?php /* translators: %s: app name. */ echo escAttr( sprintf( smliser__( 'Move %s to the trash? It stops being offered, and is deleted for good after a while unless you restore it.' ), $app['name'] ) ); ?>" data-confirm-label="<?php smliser_esc_attr_e( 'Move to trash' ); ?>" data-confirm-danger>
						<?php $hidden( $app_ref + [ 'app_status' => 'trash' ] ); ?>
						<div class="notice" data-smliser-form-message hidden></div>
						<button type="submit" class="btn"><?php smliser_esc_html_e( 'Move to trash' ); ?></button>
					</form>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</section>
<?php endif; ?>
