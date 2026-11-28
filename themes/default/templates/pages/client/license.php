<?php
/**
 * One license of the signed-in user: its terms, credentials, activated
 * sites (read-only) and activation history, and the way to deactivate it.
 *
 * A license holder cannot free a site nor reactivate a license; only the
 * app's owner or an administrator can.
 *
 * @author Callistus Nwachukwu
 * @var array{id: int, app: string, tier: string, status_key: string, status: string,
 *     expires: string, sites: string, site_list: list<array{host: string, activated: string}>,
 *     key: string, partial_key: string, service_id: string, deactivated: bool,
 *     url: string, document_url: string, app_download_url: string,
 *     renew: array{url: string, label: string, external: bool}|null,
 *     features: list<string>, start: string, created: string, source: string, reference: string,
 *     app_url: string, can_deactivate: bool} $license
 * @var list<array{event: string, event_type: string, site: string, date: string}> $history
 * @var array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $history_pagination
 * @var string $list_url
 * @var string $deactivate_url
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
?>
<div class="record">
	<div class="record-head">
		<div>
			<h3><?php echo escHtml( $license['app'] ); ?></h3>
			<?php if ( '' !== $license['tier'] ) : ?>
				<span class="muted"><?php echo escHtml( $license['tier'] ); ?></span>
			<?php endif; ?>
		</div>
		<span class="badge" data-tone="<?php echo escAttr( $tones[ $license['status_key'] ] ?? 'neutral' ); ?>"><?php echo escHtml( $license['status'] ); ?></span>
	</div>

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
			<dt><?php smliser_esc_html_e( 'Starts' ); ?></dt>
			<dd><?php echo escHtml( $license['start'] ); ?></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Expires' ); ?></dt>
			<dd><?php echo escHtml( $license['expires'] ); ?></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Sites' ); ?></dt>
			<dd><?php echo escHtml( $license['sites'] ); ?></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Source' ); ?></dt>
			<dd>
				<?php echo escHtml( $license['source'] ); ?>
				<?php if ( '' !== $license['reference'] ) : ?>
					<span class="muted">
						<?php
						/* translators: %s: the seller's reference for the sale, e.g. an order number. */
						echo escHtml( sprintf( smliser__( 'Reference: %s' ), $license['reference'] ) );
						?>
					</span>
				<?php endif; ?>
			</dd>
		</div>
		<?php if ( '' !== $license['created'] ) : ?>
			<div>
				<dt><?php smliser_esc_html_e( 'Issued' ); ?></dt>
				<dd><?php echo escHtml( $license['created'] ); ?></dd>
			</div>
		<?php endif; ?>
	</dl>

	<?php if ( [] !== $license['features'] ) : ?>
		<h4 class="dash-subheading"><?php smliser_esc_html_e( 'Features' ); ?></h4>
		<ul class="tags">
			<?php foreach ( $license['features'] as $feature ) : ?>
				<li><?php echo escHtml( $feature ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<div class="record-actions">
		<a class="btn btn-small" href="<?php echo escUrl( $list_url ); ?>">
			<i class="ti ti-arrow-left" aria-hidden="true"></i>
			<span><?php smliser_esc_html_e( 'My licenses' ); ?></span>
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
		<?php if ( '' !== $license['app_url'] ) : ?>
			<a class="btn btn-small" href="<?php echo escUrl( $license['app_url'] ); ?>">
				<i class="ti ti-external-link" aria-hidden="true"></i>
				<span><?php smliser_esc_html_e( 'App page' ); ?></span>
			</a>
		<?php endif; ?>
		<?php if ( null !== $license['renew'] ) : ?>
			<a class="btn btn-small btn-accent" href="<?php echo escUrl( $license['renew']['url'] ); ?>"<?php echo $license['renew']['external'] ? ' target="_blank" rel="noopener"' : ''; ?>>
				<i class="ti ti-refresh" aria-hidden="true"></i>
				<span><?php echo escHtml( $license['renew']['label'] ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</div>

<?php if ( $license['deactivated'] ) : ?>
	<div class="notice" data-state="error" role="status"><?php smliser_esc_html_e( 'This license is deactivated, so it is refused on every site. Only the app\'s owner or an administrator can reactivate it: contact them if it was deactivated by mistake or once the key is safe again.' ); ?></div>
<?php endif; ?>

<section class="dash-section" aria-labelledby="sites-title">
	<h2 id="sites-title"><?php smliser_esc_html_e( 'Activated sites' ); ?></h2>
	<p class="muted"><?php smliser_esc_html_e( 'The sites this license is activated on. To free a site, ask the app\'s owner.' ); ?></p>

	<?php if ( [] === $license['site_list'] ) : ?>
		<p class="muted"><?php smliser_esc_html_e( 'The license is not activated on any site yet.' ); ?></p>
	<?php else : ?>
		<ul class="records">
			<?php foreach ( $license['site_list'] as $site ) : ?>
				<li class="record record-compact">
					<div class="record-head">
						<code><?php echo escHtml( $site['host'] ); ?></code>
						<span class="muted">
							<?php
							/* translators: %s: date and time the license was activated on the site. */
							echo escHtml( sprintf( smliser__( 'Activated %s' ), $site['activated'] ) );
							?>
						</span>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<section class="dash-section" aria-labelledby="history-title">
	<h2 id="history-title"><?php smliser_esc_html_e( 'Activation history' ); ?></h2>

	<?php if ( [] === $history ) : ?>
		<p class="muted"><?php smliser_esc_html_e( 'No activity recorded yet.' ); ?></p>
	<?php else : ?>
		<ul class="records">
			<?php foreach ( $history as $entry ) : ?>
				<li class="record record-compact">
					<div class="record-head">
						<div>
							<strong><?php echo escHtml( $entry['event'] ); ?></strong>
							<?php if ( '' !== $entry['site'] ) : ?>
								<code><?php echo escHtml( $entry['site'] ); ?></code>
							<?php endif; ?>
						</div>
						<span class="muted"><?php echo escHtml( $entry['date'] ); ?></span>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php $this->partial( 'repository.pagination', [ 'pagination' => $history_pagination ] ); ?>
	<?php endif; ?>
</section>

<?php if ( $license['can_deactivate'] ) : ?>
	<section class="dash-section dash-danger" aria-labelledby="deactivate-title">
		<h2 id="deactivate-title"><?php smliser_esc_html_e( 'Report as compromised' ); ?></h2>
		<p class="muted"><?php smliser_esc_html_e( 'If someone else has your license key, deactivate the license. It is then refused on every site, yours included. No site is freed, and you cannot reactivate it yourself: only the app\'s owner or an administrator can.' ); ?></p>
		<form method="post" action="<?php echo escUrl( $deactivate_url ); ?>" data-smliser-form
			data-confirm="<?php /* translators: %s: app name. */ echo escAttr( sprintf( smliser__( 'Deactivate the license for %s on every site? Only the app\'s owner or an administrator can reactivate it.' ), $license['app'] ) ); ?>"
			data-confirm-label="<?php smliser_esc_attr_e( 'Deactivate license' ); ?>" data-confirm-danger>
			<input type="hidden" name="license_id" value="<?php echo escAttr( (string) $license['id'] ); ?>">
			<div class="notice" data-smliser-form-message hidden></div>
			<label class="check">
				<input type="checkbox" name="confirm" value="1" required>
				<span><?php smliser_esc_html_e( 'I understand that the license stops working on every site until the app\'s owner or an administrator reactivates it.' ); ?></span>
			</label>
			<button type="submit" class="btn"><?php smliser_esc_html_e( 'Deactivate license' ); ?></button>
		</form>
	</section>
<?php endif; ?>
