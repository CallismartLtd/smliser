<?php
/**
 * The home page.
 *
 * @author Callistus Nwachukwu
 * @var string $app_version The running app version.
 * @var string $php_version The running PHP version.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$principal = $this->principal();
$actions   = [ [ 'label' => smliser__( 'API documentation' ), 'url' => $this->url( 'documentation' )->url(), 'accent' => true ] ];

if ( null === $principal ) {
	$actions[] = [ 'label' => smliser__( 'Sign in' ), 'url' => $this->urls()->login_url()->url() ];
} else {
	$actions[] = $principal->is( 'system_admin' )
		? [ 'label' => smliser__( 'Admin' ), 'url' => $this->urls()->admin_url()->url() ]
		: [ 'label' => smliser__( 'Dashboard' ), 'url' => $this->urls()->client_dashboard_url()->url() ];
}

$this->partial(
	'status',
	[
		'diagram' => 'connected',
		'heading' => SMLISER_APP_NAME,
		'message' => $view->get_description(),
		'actions' => $actions,
		'detail'  => sprintf( smliser__( 'v%s · PHP %s' ), $app_version, $php_version ),
	]
);
?>
<div class="cells">
	<div class="cell">
		<h3><?php smliser_esc_html_e( 'Command line' ); ?></h3>
		<p><?php smliser_esc_html_e( 'Manage applications, licenses and the installation from the terminal.' ); ?></p>
		<code>smliser status</code>
	</div>

	<div class="cell">
		<h3><?php smliser_esc_html_e( 'Any database' ); ?></h3>
		<p><?php smliser_esc_html_e( 'MySQL, MariaDB, PostgreSQL and SQLite through one abstraction layer.' ); ?></p>
		<code>callismart/dbprism</code>
	</div>

	<div class="cell">
		<h3><?php smliser_esc_html_e( 'REST API' ); ?></h3>
		<p><?php smliser_esc_html_e( 'Activate licenses, check updates and download releases from your software.' ); ?></p>
		<code>/smliser/v1/</code>
	</div>
</div>