<?php
/**
 * The admin signing key page template.
 *
 * Shows the public key the site signs every hosted app's package with, or
 * what it means that no valid key is set and how to set one. Read-only:
 * the key lives in the .env file, and the private half is never shown.
 *
 * @author Callistus Nwachukwu
 * @var array{
 *     status: string,
 *     env_key: string,
 *     public_key: ?string,
 *     fingerprint: ?string,
 *     public_url: string,
 *     required_types: string[]
 * } $signing
 * @var SmartLicenseServer\Admin\ContentHandlers\ToolsPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

use SmartLicenseServer\HostedApps\HostingAttestation;

$menu_args  = $page_handler->get_top_menu_args( $request );
$configured = HostingAttestation::KEY_CONFIGURED === $signing['status'];
$missing    = HostingAttestation::KEY_MISSING === $signing['status'];
$required   = $signing['required_types'];
$refused    = [] === $required ? '' : sprintf( ' New packages of these types are refused until a valid key is set: %s.', implode( ', ', $required ) );

[ $tone, $icon, $title, $message ] = match ( $signing['status'] ) {
	HostingAttestation::KEY_CONFIGURED => [
		'pass',
		'ti ti-shield-check',
		'Packages are signed',
		'Every app package uploaded from now on is signed with this site\'s key, so apps that trust the public key below can check their updates.',
	],
	HostingAttestation::KEY_INVALID => [
		'critical',
		'ti ti-shield-x',
		'The signing key is not valid',
		sprintf( '%s is set, but it is not a base64 Ed25519 secret key. Packages are stored unsigned.%s', $signing['env_key'], $refused ),
	],
	default => [
		[] === $required ? 'warning' : 'critical',
		'ti ti-shield-off',
		'Packages are not signed',
		sprintf( 'No signing key is set, so apps cannot check that their updates came from this site.%s', $refused ),
	],
};

$generate = 'php -r \'echo base64_encode( sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() ) ), PHP_EOL;\'';
$chevron  = '<svg class="smliser-diagnostics-panel-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9" /></svg>';

/**
 * Print a snippet with a copy button.
 *
 * @param string $value The snippet.
 * @return void
 */
$snippet = static function ( string $value ) : void {
	?>
	<div class="smliser-bg-snippet">
		<pre><code><?php echo escHtml( $value ); ?></code></pre>
		<button type="button" class="smliser-btn smliser-btn-glass smliser-click-to-copy" data-copy-snippet data-copy-value="<?php echo escAttr( $value ); ?>" title="Copy"><i class="ti ti-copy"></i></button>
	</div>
	<?php
};
?>
<div class="smliser-admin-page" id="smliser-signing-key">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<div class="smliser-health-overview smliser-health-tone--<?php echo escAttr( $tone ); ?>">
		<span class="smliser-health-overview-icon"><i class="<?php echo escAttr( $icon ); ?>"></i></span>
		<div class="smliser-health-overview-text">
			<strong class="smliser-health-overview-title"><?php echo escHtml( $title ); ?></strong>
			<p class="smliser-health-overview-message"><?php echo escHtml( $message ); ?></p>
		</div>
	</div>

	<div class="smliser-diagnostics-grid">

		<?php if ( $configured ) : ?>
			<?php /* The public key. */ ?>
			<details class="smliser-diagnostics-panel smliser-health-panel" open>
				<summary class="smliser-diagnostics-panel-summary">
					<span class="smliser-diagnostics-panel-summary-left">
						<span class="smliser-diagnostics-panel-icon"><i class="ti ti-key"></i></span>
						<span class="smliser-diagnostics-panel-title">Public key</span>
					</span>
					<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
				</summary>
				<div class="smliser-diagnostics-panel-body">
					<ul class="smliser-health-checks">
						<li class="smliser-health-check">
							<div class="smliser-health-check-body">
								<div class="smliser-health-check-header"><span class="smliser-health-check-label">Key</span></div>
								<?php $snippet( (string) $signing['public_key'] ); ?>
								<p class="smliser-health-check-message">Developers put this key in their apps; apps then accept only packages this site signed. It cannot sign anything, so it is safe to share.</p>
							</div>
						</li>
						<li class="smliser-health-check">
							<div class="smliser-health-check-body">
								<div class="smliser-health-check-header"><span class="smliser-health-check-label">Fingerprint</span></div>
								<?php $snippet( (string) $signing['fingerprint'] ); ?>
								<p class="smliser-health-check-message">Publish the fingerprint somewhere other than this site, such as your company website, so developers can check that the key they copied was not replaced by someone who broke into this site.</p>
							</div>
						</li>
						<li class="smliser-health-check">
							<div class="smliser-health-check-body">
								<div class="smliser-health-check-header"><span class="smliser-health-check-label">Public page</span></div>
								<p class="smliser-health-check-message">
									Developers find the key, and how to use it, at
									<a href="<?php echo escUrl( $signing['public_url'] ); ?>" target="_blank" rel="noopener"><?php echo escHtml( $signing['public_url'] ); ?></a>.
								</p>
							</div>
						</li>
					</ul>
				</div>
			</details>
		<?php endif; ?>

		<?php /* What signing covers. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel" open>
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="ti ti-info-circle"></i></span>
					<span class="smliser-diagnostics-panel-title">What is signed</span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<ul class="smliser-health-checks">
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">Main packages</span></div>
							<p class="smliser-health-check-message">Each app's main package is signed when it is uploaded, and the signature names the app, so it cannot be reused for another app. Packages uploaded before the key was set stay unsigned until they are uploaded again.</p>
						</div>
					</li>
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">Artifacts</span></div>
							<p class="smliser-health-check-message">Other files of an app are not signed by this site. Developers who want them verified sign them with their own key.</p>
						</div>
					</li>
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">App types that must be signed</span></div>
							<p class="smliser-health-check-message">
								<?php echo escHtml( [] === $required ? 'None. Every app type can be uploaded without a key.' : implode( ', ', $required ) . '. Their packages are refused without a valid key.' ); ?>
							</p>
						</div>
					</li>
				</ul>
			</div>
		</details>

		<?php /* Setting or replacing the key. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel" <?php echo $configured ? '' : 'open'; ?>>
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="ti ti-settings"></i></span>
					<span class="smliser-diagnostics-panel-title"><?php echo escHtml( $configured ? 'Replacing the key' : 'Setting up a key' ); ?></span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<ol class="smliser-health-checks smliser-bg-steps">
					<?php if ( $configured ) : ?>
						<li class="smliser-health-check">
							<div class="smliser-health-check-body">
								<div class="smliser-health-check-header"><span class="smliser-health-check-label">Warn developers first</span></div>
								<p class="smliser-health-check-message">Apps that trust only the current key refuse every package signed with a new one. Publish the new public key first, give developers time to ship app updates that trust both keys, and only then switch.</p>
							</div>
						</li>
					<?php endif; ?>
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">With a terminal</span></div>
							<?php if ( $missing ) : ?>
								<p class="smliser-health-check-message">From the application folder, run the command below. It adds the missing key to the .env file and leaves every value already set untouched:</p>
								<?php $snippet( 'php smliser installer make:dotenv' ); ?>
							<?php else : ?>
								<p class="smliser-health-check-message">Generate a key, then replace the value of <?php echo escHtml( $signing['env_key'] ); ?> in the .env file with it:</p>
								<?php $snippet( $generate ); ?>
							<?php endif; ?>
						</div>
					</li>
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">Without a terminal</span></div>
							<p class="smliser-health-check-message">On any computer with PHP, generate a key:</p>
							<?php $snippet( $generate ); ?>
							<p class="smliser-health-check-message">Then open the .env file in your host's File Manager and set:</p>
							<?php $snippet( $signing['env_key'] . '=<the generated key>' ); ?>
						</div>
					</li>
					<li class="smliser-health-check">
						<div class="smliser-health-check-body">
							<div class="smliser-health-check-header"><span class="smliser-health-check-label">Keep it secret</span></div>
							<p class="smliser-health-check-message">The generated value is the private key. Anyone who has it can sign packages your users' apps will trust. Keep a backup somewhere safe: if it is lost, every app has to be updated to trust a new key.</p>
						</div>
					</li>
				</ol>
			</div>
		</details>
	</div>
</div>