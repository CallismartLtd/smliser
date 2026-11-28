<?php
/**
 * One license of the current owner's apps: its terms, its sites and its activity.
 *
 * @author Callistus Nwachukwu
 * @var array{id: int, licensee: string, email: string, app: string, tier: string, status: string,
 *     status_label: string, sites: string, expires: string, url: string, partial_key: string,
 *     starts: string, created: string} $license
 * @var list<array{host: string, origin: string, activated: string}> $sites
 * @var list<array{event: string, website: string, comment: string, ip_address: string, date: string}> $history
 * @var array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $history_pagination
 * @var array{free_site: bool, reactivate: bool, suspend: bool, unsuspend: bool} $can
 * @var array<string, string> $actions
 * @var string $list_url
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

$status_form = function ( string $action, string $label, string $question = '' ) use ( $license, $actions ) : void {
	?>
	<form method="post" action="<?php echo escUrl( $actions['status'] ); ?>" data-smliser-form
		<?php if ( '' !== $question ) : ?>data-confirm="<?php echo escAttr( $question ); ?>" data-confirm-label="<?php echo escAttr( $label ); ?>" data-confirm-danger<?php endif; ?>>
		<input type="hidden" name="license_id" value="<?php echo escAttr( (string) $license['id'] ); ?>">
		<input type="hidden" name="license_action" value="<?php echo escAttr( $action ); ?>">
		<div class="notice" data-smliser-form-message hidden></div>
		<button type="submit" class="btn btn-small"><?php echo escHtml( $label ); ?></button>
	</form>
	<?php
};
?>
<div class="record">
	<div class="record-head">
		<div>
			<h3><?php echo escHtml( '' !== $license['licensee'] ? $license['licensee'] : $license['email'] ); ?></h3>
			<span class="muted"><?php echo escHtml( $license['email'] ); ?></span>
		</div>
		<span class="badge" data-tone="<?php echo escAttr( $tones[ $license['status'] ] ?? 'neutral' ); ?>"><?php echo escHtml( $license['status_label'] ); ?></span>
	</div>

	<dl class="record-facts">
		<div>
			<dt><?php smliser_esc_html_e( 'App' ); ?></dt>
			<dd><?php echo escHtml( $license['app'] ); ?></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Tier' ); ?></dt>
			<dd><?php echo escHtml( '' !== $license['tier'] ? $license['tier'] : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'License key' ); ?></dt>
			<dd><code><?php echo escHtml( $license['partial_key'] ); ?></code></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Sites' ); ?></dt>
			<dd><?php echo escHtml( $license['sites'] ); ?></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Starts' ); ?></dt>
			<dd><?php echo escHtml( '' !== $license['starts'] ? $license['starts'] : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php smliser_esc_html_e( 'Expires' ); ?></dt>
			<dd><?php echo escHtml( $license['expires'] ); ?></dd>
		</div>
	</dl>

	<div class="record-actions">
		<a class="btn btn-small" href="<?php echo escUrl( $list_url ); ?>">
			<i class="ti ti-arrow-left" aria-hidden="true"></i>
			<span><?php smliser_esc_html_e( 'App licenses' ); ?></span>
		</a>
	</div>
</div>

<?php if ( 'deactivated' === $license['status'] ) : ?>
	<div class="notice" data-state="error" role="status"><?php smliser_esc_html_e( 'The license holder deactivated this license. It works again only once you reactivate it.' ); ?></div>
<?php endif; ?>

<section class="dash-section" aria-labelledby="sites-title">
	<h2 id="sites-title"><?php smliser_esc_html_e( 'Activated sites' ); ?></h2>
	<?php if ( $can['free_site'] ) : ?>
		<p class="muted"><?php smliser_esc_html_e( 'Free a site to let the license be activated somewhere else. The site loses its download access at once.' ); ?></p>
	<?php endif; ?>

	<?php if ( [] === $sites ) : ?>
		<p class="muted"><?php smliser_esc_html_e( 'The license is not activated on any site.' ); ?></p>
	<?php else : ?>
		<ul class="records">
			<?php foreach ( $sites as $site ) : ?>
				<li class="record record-compact">
					<div class="record-head">
						<div>
							<h3><?php echo escHtml( $site['host'] ); ?></h3>
							<?php if ( '' !== $site['activated'] ) : ?>
								<?php /* translators: %s: the date the site was activated. */ ?>
								<span class="muted"><?php echo escHtml( sprintf( smliser__( 'Activated %s' ), $site['activated'] ) ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $can['free_site'] ) : ?>
							<div class="record-actions">
								<?php /* translators: %s: the site's domain. */ ?>
								<form method="post" action="<?php echo escUrl( $actions['free_site'] ); ?>" data-smliser-form
									data-confirm="<?php echo escAttr( sprintf( smliser__( 'Free %s? It can no longer use this license or download the app, until the license is activated on it again.' ), $site['host'] ) ); ?>"
									data-confirm-label="<?php smliser_esc_attr_e( 'Free site' ); ?>" data-confirm-danger>
									<input type="hidden" name="license_id" value="<?php echo escAttr( (string) $license['id'] ); ?>">
									<input type="hidden" name="domain" value="<?php echo escAttr( $site['host'] ); ?>">
									<div class="notice" data-smliser-form-message hidden></div>
									<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Free site' ); ?></button>
								</form>
							</div>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php if ( $can['reactivate'] || $can['suspend'] || $can['unsuspend'] ) : ?>
	<section class="dash-section dash-danger" aria-labelledby="status-title">
		<h2 id="status-title"><?php smliser_esc_html_e( 'Status' ); ?></h2>
		<p class="muted"><?php smliser_esc_html_e( 'A suspended or deactivated license cannot be validated or used to download the app.' ); ?></p>
		<div class="record-actions">
			<?php
			if ( $can['reactivate'] ) {
				$status_form( 'reactivate', smliser__( 'Reactivate' ) );
			}

			if ( $can['unsuspend'] ) {
				$status_form( 'unsuspend', smliser__( 'Unsuspend' ) );
			}

			if ( $can['suspend'] ) {
				$status_form( 'suspend', smliser__( 'Suspend' ), smliser__( 'Suspend this license? It stops working on every site until you unsuspend it.' ) );
			}
			?>
		</div>
	</section>
<?php endif; ?>

<section class="dash-section" aria-labelledby="history-title">
	<h2 id="history-title"><?php smliser_esc_html_e( 'Activity' ); ?></h2>

	<?php if ( [] === $history ) : ?>
		<p class="muted"><?php smliser_esc_html_e( 'No activity recorded for this license yet.' ); ?></p>
	<?php else : ?>
		<ul class="records">
			<?php foreach ( $history as $entry ) : ?>
				<li class="record record-compact">
					<div class="record-head">
						<div>
							<h3><?php echo escHtml( $entry['event'] ); ?></h3>
							<span class="muted"><?php echo escHtml( implode( ' · ', array_filter( [ $entry['date'], $entry['website'], $entry['ip_address'] ] ) ) ); ?></span>
						</div>
					</div>
					<?php if ( '' !== $entry['comment'] ) : ?>
						<p class="muted"><?php echo escHtml( $entry['comment'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php $this->partial( 'repository.pagination', [ 'pagination' => $history_pagination ] ); ?>
	<?php endif; ?>
</section>
