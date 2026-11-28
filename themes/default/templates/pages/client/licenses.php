<?php
/**
 * My licenses: the licenses linked to the signed-in user.
 *
 * Sites are shown read-only: a license holder cannot free a site.
 *
 * @author Callistus Nwachukwu
 * @var list<array{id: int, app: string, tier: string, status_key: string, status: string,
 *     expires: string, sites: string, site_list: list<array{host: string, activated: string}>,
 *     key: string, partial_key: string, service_id: string, deactivated: bool,
 *     url: string, document_url: string, app_download_url: string,
 *     renew: array{url: string, label: string, external: bool}|null}> $licenses
 * @var array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
 * @var bool $verified Whether the user's email address is verified.
 * @var array{action: string, status: string, app: string, search: string, active: bool,
 *     statuses: array<string, string>, apps: array<string, string>, clear_url: string} $filters
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$tones = [
	'active'      => 'success',
	'lifetime'    => 'success',
	'expired'     => 'failure',
	'revoked'     => 'failure',
	'suspended'   => 'failure',
	'deactivated' => 'failure',
];

// Sites listed inline before the rest is left to the license's page.
$inline_sites = 3;
?>
<p class="dash-lead"><?php smliser_esc_html_e( 'Licenses issued to you. Use the key and service ID on each site you activate.' ); ?></p>

<?php if ( $verified ) : ?>
	<form class="license-filters" role="search" method="get" action="<?php echo escUrl( $filters['action'] ); ?>">
		<label class="license-filter-search">
			<span class="screen-reader-text"><?php smliser_esc_html_e( 'Search by license key or app name' ); ?></span>
			<input type="search" name="search" value="<?php echo escAttr( $filters['search'] ); ?>" placeholder="<?php smliser_esc_attr_e( 'Search by license key or app name' ); ?>">
		</label>
		<label>
			<span class="screen-reader-text"><?php smliser_esc_html_e( 'Status' ); ?></span>
			<select name="status">
				<option value=""><?php smliser_esc_html_e( 'All statuses' ); ?></option>
				<?php foreach ( $filters['statuses'] as $value => $label ) : ?>
					<option value="<?php echo escAttr( $value ); ?>"<?php echo $value === $filters['status'] ? ' selected' : ''; ?>><?php echo escHtml( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php if ( [] !== $filters['apps'] ) : ?>
			<label>
				<span class="screen-reader-text"><?php smliser_esc_html_e( 'App' ); ?></span>
				<select name="app">
					<option value=""><?php smliser_esc_html_e( 'All apps' ); ?></option>
					<?php foreach ( $filters['apps'] as $value => $label ) : ?>
						<option value="<?php echo escAttr( $value ); ?>"<?php echo $value === $filters['app'] ? ' selected' : ''; ?>><?php echo escHtml( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>
		<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Filter' ); ?></button>
		<?php if ( $filters['active'] ) : ?>
			<a class="btn btn-small" href="<?php echo escUrl( $filters['clear_url'] ); ?>"><?php smliser_esc_html_e( 'Clear filters' ); ?></a>
		<?php endif; ?>
	</form>
<?php endif; ?>

<?php if ( [] === $licenses ) : ?>
	<div class="cell">
		<?php if ( $filters['active'] ) : ?>
			<h3><?php smliser_esc_html_e( 'No matching licenses' ); ?></h3>
			<p><?php smliser_esc_html_e( 'No license matches these filters.' ); ?></p>
		<?php else : ?>
			<h3><?php smliser_esc_html_e( 'No licenses yet' ); ?></h3>
			<p>
				<?php echo escHtml( $verified
					? smliser__( 'Licenses you buy appear here once they are issued.' )
					: smliser__( 'Licenses you buy appear here once they are issued. Verify your email address to see licenses bought with it before you signed up.' ) ); ?>
			</p>
		<?php endif; ?>
	</div>
<?php else : ?>
	<ul class="records">
		<?php foreach ( $licenses as $license ) : ?>
			<li class="record">
				<div class="record-head">
					<h3><a href="<?php echo escUrl( $license['url'] ); ?>"><?php echo escHtml( '' !== $license['tier'] ? sprintf( '%s — %s', $license['app'], $license['tier'] ) : $license['app'] ); ?></a></h3>
					<span class="badge" data-tone="<?php echo escAttr( $tones[ $license['status_key'] ] ?? 'neutral' ); ?>"><?php echo escHtml( $license['status'] ); ?></span>
				</div>

				<?php if ( $license['deactivated'] ) : ?>
					<p class="notice" data-state="error"><?php smliser_esc_html_e( 'This license is deactivated and refused on every site. Only the app\'s owner or an administrator can reactivate it.' ); ?></p>
				<?php endif; ?>

				<dl class="record-facts">
					<div>
						<dt><?php smliser_esc_html_e( 'License key' ); ?></dt>
						<dd class="record-key">
							<code><?php echo escHtml( $license['partial_key'] ); ?></code>
							<button type="button" class="btn btn-small" data-copy="<?php echo escAttr( $license['key'] ); ?>"><?php smliser_esc_html_e( 'Copy key' ); ?></button>
						</dd>
					</div>
					<div>
						<dt><?php smliser_esc_html_e( 'Service ID' ); ?></dt>
						<dd class="record-key">
							<code><?php echo escHtml( $license['service_id'] ); ?></code>
							<button type="button" class="btn btn-small" data-copy="<?php echo escAttr( $license['service_id'] ); ?>"><?php smliser_esc_html_e( 'Copy service ID' ); ?></button>
						</dd>
					</div>
					<div>
						<dt><?php smliser_esc_html_e( 'Expires' ); ?></dt>
						<dd><?php echo escHtml( $license['expires'] ); ?></dd>
					</div>
					<div>
						<dt><?php smliser_esc_html_e( 'Sites' ); ?></dt>
						<dd><?php echo escHtml( $license['sites'] ); ?></dd>
					</div>
				</dl>

				<?php if ( [] !== $license['site_list'] ) : ?>
					<ul class="license-sites" aria-label="<?php smliser_esc_attr_e( 'Activated sites' ); ?>">
						<?php foreach ( array_slice( $license['site_list'], 0, $inline_sites ) as $site ) : ?>
							<li><code><?php echo escHtml( $site['host'] ); ?></code></li>
						<?php endforeach; ?>
						<?php if ( count( $license['site_list'] ) > $inline_sites ) : ?>
							<?php $more = count( $license['site_list'] ) - $inline_sites; ?>
							<li>
								<a href="<?php echo escUrl( $license['url'] ); ?>">
									<?php
									/* translators: %d: number of other activated sites not listed. */
									echo escHtml( sprintf( smliser_n( 'and %d more', 'and %d more', $more ), $more ) );
									?>
								</a>
							</li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>

				<div class="record-actions">
					<a class="btn btn-small" href="<?php echo escUrl( $license['url'] ); ?>">
						<i class="ti ti-list-details" aria-hidden="true"></i>
						<span><?php smliser_esc_html_e( 'Details' ); ?></span>
					</a>
					<a class="btn btn-small" href="<?php echo escUrl( $license['document_url'] ); ?>" download>
						<i class="ti ti-file-download" aria-hidden="true"></i>
						<span><?php smliser_esc_html_e( 'Download License' ); ?></span>
					</a>
					<?php if ( '' !== $license['app_download_url'] ) : ?>
						<a class="btn btn-small" href="<?php echo escUrl( $license['app_download_url'] ); ?>" download>
							<i class="ti ti-download" aria-hidden="true"></i>
							<span>
								<?php
								/* translators: %s: app name. */
								echo escHtml( sprintf( smliser__( 'Download %s' ), $license['app'] ) );
								?>
							</span>
						</a>
					<?php endif; ?>
					<?php if ( null !== $license['renew'] ) : ?>
						<a class="btn btn-small btn-accent" href="<?php echo escUrl( $license['renew']['url'] ); ?>"<?php echo $license['renew']['external'] ? ' target="_blank" rel="noopener"' : ''; ?>>
							<i class="ti ti-refresh" aria-hidden="true"></i>
							<span><?php echo escHtml( $license['renew']['label'] ); ?></span>
						</a>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php $this->partial( 'repository.pagination', [ 'pagination' => $pagination ] ); ?>
<?php endif; ?>
