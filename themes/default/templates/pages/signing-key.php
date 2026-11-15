<?php
/**
 * The package signing key page.
 *
 * @author Callistus Nwachukwu
 * @var bool        $signed         Whether this site signs the packages it serves.
 * @var string|null $public_key     Base64 Ed25519 public key; null when not signed.
 * @var string|null $fingerprint    "SHA256:..." fingerprint of the key; null when not signed.
 * @var string      $api_root       This site's REST API root.
 * @var string      $client_package Composer package of the update client.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$example = <<<'PHP'
use Callismart\SmliserUpdateClient\Endpoint;
use Callismart\SmliserUpdateClient\TrustPolicy;
use Callismart\SmliserUpdateClient\UpdateClient;

$updates = new UpdateClient(
	new Endpoint( '%1$s' ),
	'YOUR_APP_TYPE',
	'YOUR_APP_SLUG',
	TrustPolicy::host( [ '%2$s' => '%3$s' ] )
);
PHP;
?>
<div class="page-heading">
	<h1><?php echo escHtml( $view->get_title() ); ?></h1>
	<p><?php echo escHtml( $view->get_description() ); ?></p>
</div>

<?php if ( ! $signed ) : ?>
	<section class="app-section prose">
		<h2>This site does not sign its packages</h2>
		<p>Packages downloaded from this site carry no signature, so an app cannot check that a download came from this site unchanged.</p>
		<p>If you publish apps here and want your users' updates to be verified, ask the site's administrator to set up package signing.</p>
	</section>
<?php else : ?>
	<section class="app-section">
		<h2>Public key</h2>

		<dl class="facts">
			<div><dt>Key</dt><dd class="signing-key-value"><code><?php echo escHtml( (string) $public_key ); ?></code></dd></div>
			<div><dt>Fingerprint</dt><dd class="signing-key-value"><code><?php echo escHtml( (string) $fingerprint ); ?></code></dd></div>
			<div><dt>Algorithm</dt><dd>Ed25519</dd></div>
		</dl>

		<p class="muted">This is a public key: it can only check signatures, not make them, and it is safe to share.</p>
	</section>

	<section class="app-section prose">
		<h2>What the key is for</h2>
		<p>Every app package this site serves is signed with the private half of this key, which only this site has. An app that knows the public key can check, before it installs an update, that the package came from this site and was not changed on the way.</p>
	</section>

	<section class="app-section prose">
		<h2>Using it in your app</h2>
		<ol>
			<li>Copy the key above into your app's code. Do not make your app download the key when it checks for updates: a key fetched from the same place as the update proves nothing.</li>
			<li>Before you ship, check that the fingerprint above matches the one the site's owner publishes elsewhere, such as on their own website. If this site were ever compromised, the key shown here could be replaced too.</li>
			<li>Check for and download updates with <code><?php echo escHtml( $client_package ); ?></code>, which accepts only packages signed by the keys you give it:</li>
		</ol>

		<pre><code><?php echo escHtml( sprintf( $example, $api_root, (string) parse_url( $api_root, PHP_URL_HOST ), (string) $public_key ) ); ?></code></pre>

		<p>Your app's type and slug are in the address of its page in the <a href="<?php echo escUrl( $this->urls()->repository_url()->url() ); ?>">repository</a>: <code>repository/&lt;type&gt;/&lt;slug&gt;</code>.</p>
	</section>

	<section class="app-section prose">
		<h2>If the key changes</h2>
		<p>Apps that know only the old key refuse packages signed with a new one. When the site's owner announces a new key, ship an update of your app that trusts both the old and the new key before the switch, so your users keep receiving updates.</p>
	</section>
<?php endif; ?>