<?php
/**
 * The sign-up page.
 *
 * @author Callistus Nwachukwu
 * @var string $action      The URL the form posts to.
 * @var string $email       Address to fill in (?email=); may be empty.
 * @var string $terms_url   Terms URL; empty when not set.
 * @var string $privacy_url Privacy policy URL; empty when not set.
 * @var string $redirect_url Where to return after signing up (?redirect_url=, this site only); may be empty.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

if ( null !== $this->principal() ) {
	$this->partial( 'auth.signed-in' );
	return;
}
?>
<div class="panel">
	<h1 class="panel-heading"><?php smliser_esc_html_e( 'Create an account' ); ?></h1>
	<p class="panel-lead"><?php smliser_esc_html_e( 'Get access to your licenses and downloads.' ); ?></p>

	<form method="post" action="<?php echo escUrl( $action ); ?>" data-smliser-form data-pass-redirect>
		<div class="notice" data-smliser-form-message hidden></div>
		<label class="field">
			<span class="field-label"><?php smliser_esc_html_e( 'Full name' ); ?></span>
			<input type="text" name="full_name" autocomplete="name" required autofocus>
		</label>
		<label class="field">
			<span class="field-label"><?php smliser_esc_html_e( 'Email' ); ?></span>
			<input type="email" name="email" autocomplete="email" value="<?php echo escAttr( $email ?? '' ); ?>" required>
		</label>
		<label class="field">
			<span class="field-label"><?php smliser_esc_html_e( 'Password' ); ?></span>
			<input type="password" name="password_1" autocomplete="new-password" minlength="8" required data-password-meter>
			<span class="strength" data-password-strength></span>
			<span class="field-hint"><?php smliser_esc_html_e( 'At least 8 characters, with upper and lower case letters and numbers.' ); ?></span>
		</label>
		<label class="field">
			<span class="field-label"><?php smliser_esc_html_e( 'Confirm password' ); ?></span>
			<input type="password" name="password_2" autocomplete="new-password" minlength="8" required>
		</label>
		<label class="field">
			<span class="field-label"><?php smliser_esc_html_e( 'Account type' ); ?></span>
			<select name="account_type">
				<option value="viewer"><?php smliser_esc_html_e( 'Licensee (download access)' ); ?></option>
				<option value="resource_owner"><?php smliser_esc_html_e( 'Resource owner' ); ?></option>
			</select>
		</label>
		<label class="check">
			<input type="checkbox" name="agree_terms" value="1" required>
			<span>
				<?php
				$terms   = '' !== $terms_url ? sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', escUrl( $terms_url ), smliser_esc_html__( 'terms' ) ) : smliser_esc_html__( 'terms' );
				$privacy = '' !== $privacy_url ? sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', escUrl( $privacy_url ), smliser_esc_html__( 'privacy policy' ) ) : smliser_esc_html__( 'privacy policy' );
				/* translators: 1: "terms" (linked), 2: "privacy policy" (linked). */
				printf( smliser_esc_html__( 'I agree to the %1$s and the %2$s.' ), $terms, $privacy ); // phpcs:ignore -- escaped above.
				?>
			</span>
		</label>
		<button type="submit" class="btn btn-accent btn-block"><?php smliser_esc_html_e( 'Create account' ); ?></button>
	</form>

	<div class="panel-links">
		<p><?php smliser_esc_html_e( 'Have an account?' ); ?> <a href="<?php echo escUrl( $this->urls()->login_url( '', '' !== $redirect_url ? [ 'redirect_url' => $redirect_url ] : [] )->url() ); ?>"><?php smliser_esc_html_e( 'Sign in' ); ?></a></p>
	</div>
</div>