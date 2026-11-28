<?php
/**
 * The signed-in user's settings: language and dashboard theme, one tab each.
 *
 * Both forms are handled by assets/js/dashboard-settings.js, which saves the
 * choice to the user's preferences (json/preferences) with the CSRF token.
 *
 * @var list<string> $tabs The tabs shown: 'language' (when it can be chosen here) and 'theme'.
 * @var string $active_tab
 * @var array<string, string> $languages Installed languages, code => native name.
 * @var string $locale The saved language, '' for automatic.
 * @var string $current_language The language this page is shown in.
 * @var string $theme The saved theme: 'auto', 'light' or 'dark'.
 * @var array<string, string> $themes Theme choices, value => label.
 * @var array<string, string> $actions
 */

use SmartLicenseServer\I18n\Locale;

$tab_labels = [
	'language' => smliser__( 'Language' ),
	'theme'    => smliser_x( 'Theme', 'color scheme' ),
];
?>
<section class="panel panel-wide settings-page" data-settings data-preferences-url="<?php echo escUrl( $actions['preferences'] ); ?>">
	<h1 class="panel-heading"><?php smliser_esc_html_e( 'Settings' ); ?></h1>
	<p class="panel-lead"><?php smliser_esc_html_e( 'Choose how the dashboard looks and which language it speaks to you in. These settings follow your account to every device.' ); ?></p>

	<div class="settings-group" data-settings-tabs data-active-tab="<?php echo escAttr( $active_tab ); ?>">
		<?php if ( count( $tabs ) > 1 ) : ?>
			<div class="security-tablist" role="tablist" aria-label="<?php smliser_esc_attr_e( 'Settings' ); ?>">
				<?php foreach ( $tabs as $tab ) : ?>
					<button class="security-tab" id="settings-tab-<?php echo escAttr( $tab ); ?>" type="button" role="tab" aria-controls="settings-panel-<?php echo escAttr( $tab ); ?>" aria-selected="<?php echo $tab === $active_tab ? 'true' : 'false'; ?>" tabindex="<?php echo $tab === $active_tab ? '0' : '-1'; ?>" data-settings-tab="<?php echo escAttr( $tab ); ?>"><?php echo escHtml( $tab_labels[ $tab ] ?? $tab ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( in_array( 'language', $tabs, true ) ) : ?>
			<section class="security-tab-panel" id="settings-panel-language" role="tabpanel" aria-labelledby="settings-tab-language" tabindex="0" data-settings-panel="language"<?php echo 'language' !== $active_tab ? ' hidden' : ''; ?>>
				<div class="security-method-heading">
					<div>
						<h2><?php smliser_esc_html_e( 'Language' ); ?></h2>
						<p><?php smliser_esc_html_e( 'The language of the dashboards, the pages of this site and the emails sent to you.' ); ?></p>
					</div>
					<span class="security-state"><?php echo escHtml( $current_language ); ?></span>
				</div>

				<form method="post" action="<?php echo escUrl( $actions['preferences'] ); ?>" data-settings-language>
					<div class="notice" data-settings-message hidden></div>
					<label class="field">
						<span class="field-label"><?php smliser_esc_html_e( 'Your language' ); ?></span>
						<select name="value">
							<option value=""<?php echo '' === $locale ? ' selected' : ''; ?>><?php smliser_esc_html_e( 'Automatic (site default)' ); ?></option>
							<?php foreach ( $languages as $code => $name ) : ?>
								<option value="<?php echo escAttr( $code ); ?>" lang="<?php echo escAttr( Locale::parse( $code )->to_bcp47() ); ?>"<?php echo $code === $locale ? ' selected' : ''; ?>><?php echo escHtml( $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="field-hint"><?php smliser_esc_html_e( 'Automatic uses the site language, or your browser\'s language when the site has none.' ); ?></span>
					</label>
					<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Save language' ); ?></button>
				</form>
			</section>
		<?php endif; ?>

		<section class="security-tab-panel" id="settings-panel-theme"<?php echo count( $tabs ) > 1 ? ' role="tabpanel" aria-labelledby="settings-tab-theme" tabindex="0"' : ''; ?> data-settings-panel="theme"<?php echo 'theme' !== $active_tab ? ' hidden' : ''; ?>>
			<div class="security-method-heading">
				<div>
					<h2><?php echo smliser_esc_html_x( 'Theme', 'color scheme' ); ?></h2>
					<p><?php smliser_esc_html_e( 'Light or dark colors for the admin and client dashboards. System follows your device\'s setting.' ); ?></p>
				</div>
			</div>

			<form method="post" action="<?php echo escUrl( $actions['preferences'] ); ?>" data-settings-theme>
				<div class="notice" data-settings-message hidden></div>
				<fieldset class="settings-choices">
					<legend class="field-label"><?php smliser_esc_html_e( 'Dashboard theme' ); ?></legend>
					<?php foreach ( $themes as $value => $label ) : ?>
						<label class="settings-choice">
							<input type="radio" name="value" value="<?php echo escAttr( $value ); ?>"<?php echo $value === $theme ? ' checked' : ''; ?>>
							<span class="settings-choice-swatch settings-choice-swatch-<?php echo escAttr( $value ); ?>" aria-hidden="true"></span>
							<span><?php echo escHtml( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</form>
		</section>
	</div>
</section>
