<?php
/**
 * The document of every page.
 *
 * @author Callistus Nwachukwu
 * @var string $content The page's HTML.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

// The theme's own assets: enqueued before head(), which prints them with the page's.
$this->enqueue_style( 'style', 'css/theme.css' );
$this->enqueue_script( 'script', 'js/theme.js', [ 'i18n' ] );

// The client dashboard follows the user's theme (Settings > Theme): 'light' or 'dark'; 'auto' follows the system.
$dashboard_data  = $view->get( 'dashboard' );
$dashboard_theme = is_array( $dashboard_data ) ? (string) ( $dashboard_data['theme'] ?? '' ) : '';
?>
<!DOCTYPE html>
<html <?php echo smliser_language_attributes(); // phpcs:ignore -- escaped. ?><?php echo in_array( $dashboard_theme, [ 'light', 'dark' ], true ) ? ' data-theme="' . escAttr( $dashboard_theme ) . '"' : ''; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<?php $this->head(); ?>
</head>
<body>
	<?php if ( '' === $view->get_frame() ) : // A frame brings its own navigation. ?>
		<?php $this->partial( 'header' ); ?>
	<?php endif; ?>

	<main class="site-main">
		<?php echo $content; // phpcs:ignore -- rendered page. ?>
	</main>

	<?php $this->partial( 'footer' ); ?>

	<?php $this->footer(); ?>
</body>
</html>