<?php
/**
 * App licenses: the licenses issued for the current owner's apps.
 *
 * @author Callistus Nwachukwu
 * @var list<array{id: int, licensee: string, email: string, app: string, tier: string,
 *     status: string, status_label: string, sites: string, expires: string, url: string}> $licenses
 * @var array<string, string> $apps The owner's apps to filter by, "type/slug" => name.
 * @var array<string, string> $statuses Statuses to filter by, status => label.
 * @var array{app: string, status: string, q: string} $filters The filters applied.
 * @var string $list_url The unfiltered list.
 * @var array{page: int, total_pages: int, total: int, previous_url: string, next_url: string} $pagination
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

$filtered = '' !== $filters['app'] || '' !== $filters['status'] || '' !== $filters['q'];
?>
<p class="dash-lead"><?php smliser_esc_html_e( 'Licenses issued for the apps you host here, for whoever you act for.' ); ?></p>

<form class="dash-search" method="get" action="<?php echo escUrl( $list_url ); ?>" role="search">
	<label class="field">
		<span class="field-label"><?php smliser_esc_html_e( 'App' ); ?></span>
		<select name="app">
			<option value=""><?php smliser_esc_html_e( 'All apps' ); ?></option>
			<?php foreach ( $apps as $value => $name ) : ?>
				<option value="<?php echo escAttr( $value ); ?>"<?php echo $value === $filters['app'] ? ' selected' : ''; ?>><?php echo escHtml( $name ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label class="field">
		<span class="field-label"><?php smliser_esc_html_e( 'Status' ); ?></span>
		<select name="status">
			<option value=""><?php smliser_esc_html_e( 'All statuses' ); ?></option>
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo escAttr( $value ); ?>"<?php echo $value === $filters['status'] ? ' selected' : ''; ?>><?php echo escHtml( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label class="field">
		<span class="field-label"><?php smliser_esc_html_e( 'Search' ); ?></span>
		<input type="search" name="q" value="<?php echo escAttr( $filters['q'] ); ?>" placeholder="<?php smliser_esc_attr_e( 'License key or licensee email' ); ?>">
	</label>
	<div class="dash-search-actions">
		<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Filter' ); ?></button>
		<?php if ( $filtered ) : ?>
			<a class="btn btn-small" href="<?php echo escUrl( $list_url ); ?>"><?php smliser_esc_html_e( 'Clear' ); ?></a>
		<?php endif; ?>
	</div>
</form>

<?php if ( [] === $licenses ) : ?>
	<div class="cell">
		<h3><?php echo escHtml( $filtered ? smliser__( 'No licenses match' ) : smliser__( 'No licenses yet' ) ); ?></h3>
		<p><?php echo escHtml( $filtered ? smliser__( 'Change the filters to see more licenses.' ) : smliser__( 'Licenses sold or issued for your apps appear here.' ) ); ?></p>
	</div>
<?php else : ?>
	<ul class="records">
		<?php foreach ( $licenses as $license ) : ?>
			<li class="record">
				<div class="record-head">
					<div>
						<h3><a href="<?php echo escUrl( $license['url'] ); ?>"><?php echo escHtml( '' !== $license['licensee'] ? $license['licensee'] : $license['email'] ); ?></a></h3>
						<?php if ( '' !== $license['licensee'] ) : ?>
							<span class="muted"><?php echo escHtml( $license['email'] ); ?></span>
						<?php endif; ?>
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
						<dt><?php smliser_esc_html_e( 'Sites' ); ?></dt>
						<dd><?php echo escHtml( $license['sites'] ); ?></dd>
					</div>
					<div>
						<dt><?php smliser_esc_html_e( 'Expires' ); ?></dt>
						<dd><?php echo escHtml( $license['expires'] ); ?></dd>
					</div>
				</dl>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php $this->partial( 'repository.pagination', [ 'pagination' => $pagination ] ); ?>
<?php endif; ?>
