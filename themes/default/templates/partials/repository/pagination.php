<?php
/**
 * Previous and next links of a paginated list.
 *
 * @author Callistus Nwachukwu
 * @var array{page: int, total_pages: int, total: int, previous_url: string, next_url: string}|null $pagination
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

if ( null === $pagination || $pagination['total_pages'] < 2 ) {
	return;
}
?>
<nav class="pagination" aria-label="<?php smliser_esc_attr_e( 'Pages' ); ?>">
	<?php if ( '' !== $pagination['previous_url'] ) : ?>
		<a class="btn btn-small" href="<?php echo escUrl( $pagination['previous_url'] ); ?>" rel="prev"><?php smliser_esc_html_e( 'Previous' ); ?></a>
	<?php endif; ?>

	<span class="muted"><?php echo escHtml( sprintf( smliser__( 'Page %d of %d' ), $pagination['page'], $pagination['total_pages'] ) ); ?></span>

	<?php if ( '' !== $pagination['next_url'] ) : ?>
		<a class="btn btn-small" href="<?php echo escUrl( $pagination['next_url'] ); ?>" rel="next"><?php smliser_esc_html_e( 'Next' ); ?></a>
	<?php endif; ?>
</nav>