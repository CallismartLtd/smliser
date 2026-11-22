<?php
/**
 * The site footer.
 *
 * @author Callistus Nwachukwu
 */

?>
<footer class="site-footer">
	<div class="site-footer-inner">
		<span><?php
		/* translators: 1: application name, 2: version number. */
		echo escHtml( sprintf( smliser__( '%1$s v%2$s' ), SMLISER_APP_NAME, SMLISER_VER ) );
		?></span>
		<a href="<?php echo escUrl( $this->url( SmartLicenseServer\Environments\Application\Theming\CorePages::SIGNING_KEY_PATH )->url() ); ?>"><?php smliser_esc_html_e( 'Package signing key' ); ?></a>
		<span>&copy; <?php echo escHtml( gmdate( 'Y' ) ); ?> <?php smliser_esc_html_e( 'Callismart' ); ?></span>
	</div>
</footer>