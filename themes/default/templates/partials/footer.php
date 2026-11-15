<?php
/**
 * The site footer.
 *
 * @author Callistus Nwachukwu
 */

?>
<footer class="site-footer">
	<div class="site-footer-inner">
		<span><?php echo escHtml( sprintf( '%s v%s', SMLISER_APP_NAME, SMLISER_VER ) ); ?></span>
		<a href="<?php echo escUrl( $this->url( SmartLicenseServer\Environments\Application\Theming\CorePages::SIGNING_KEY_PATH )->url() ); ?>">Package signing key</a>
		<span>&copy; <?php echo escHtml( gmdate( 'Y' ) ); ?> Callismart</span>
	</div>
</footer>