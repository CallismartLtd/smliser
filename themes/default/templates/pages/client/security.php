<?php
/**
 * Self-service sign-in security: passkeys, which replace the password, and the
 * second factors (email codes, authenticator app) that add a step to a password sign-in.
 *
 * Every form that asks for the current password carries an unnamed, visually hidden
 * email input. It tells password managers which account the password belongs to,
 * so a re-authentication is not treated as a password change.
 *
 * @var string $email
 * @var bool $email_verified
 * @var bool $email_enabled
 * @var bool $totp_enabled
 * @var array $passkeys
 * @var int $recovery_code_count
 * @var string $email_challenge
 * @var string $email_disable_challenge
 * @var string $totp_challenge
 * @var string $totp_secret
 * @var string $totp_uri
 * @var string $totp_qr_code
 * @var string $active_tab
 * @var bool $wordpress Whether WordPress manages the password and sessions.
 * @var string $wordpress_url The WordPress profile URL.
 * @var array<string,string> $actions
 */
?>
<section class="panel panel-wide security-page">
	<h1 class="panel-heading"><?php smliser_esc_html_e( 'Account security' ); ?></h1>
	<p class="panel-lead"><?php smliser_esc_html_e( 'Choose how you sign in and how your account is protected. Each method is verified before it is enabled.' ); ?></p>

	<?php if ( $wordpress ) : ?>
		<div class="notice" role="status">
			<?php smliser_esc_html_e( 'WordPress manages your password and sessions. Change them from your WordPress profile.' ); ?>
			<?php if ( '' !== $wordpress_url ) : ?>
				<a href="<?php echo escUrl( $wordpress_url ); ?>"><?php smliser_esc_html_e( 'Open your WordPress profile' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<section class="security-tab-panel security-password" aria-labelledby="security-password-heading">
			<div class="security-method-heading">
				<div><h2 id="security-password-heading"><?php smliser_esc_html_e( 'Password' ); ?></h2><p><?php smliser_esc_html_e( 'Changing your password signs you out on every other device. This one stays signed in, and you get an email about the change.' ); ?></p></div>
			</div>
			<form method="post" action="<?php echo escUrl( $actions['password_change'] ); ?>" data-smliser-form>
				<div class="notice" data-smliser-form-message hidden></div>
				<input type="text" class="security-email" value="<?php echo escAttr( $email ); ?>" autocomplete="username" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
				<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span><input type="password" name="current_password" autocomplete="current-password" required></label>
				<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'New password' ); ?></span><input type="password" name="password_1" autocomplete="new-password" minlength="8" data-password-meter required></label>
				<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Type the new password again' ); ?></span><input type="password" name="password_2" autocomplete="new-password" minlength="8" required></label>
				<span data-password-strength></span>
				<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Change password' ); ?></button>
			</form>
		</section>

		<section class="security-tab-panel security-sessions" aria-labelledby="security-sessions-heading">
			<div class="security-method-heading">
				<div><h2 id="security-sessions-heading"><?php smliser_esc_html_e( 'Sessions' ); ?></h2><p><?php smliser_esc_html_e( 'Signed in on a device you no longer use, or a shared computer? Sign out everywhere else. This device stays signed in.' ); ?></p></div>
			</div>
			<form method="post" action="<?php echo escUrl( $actions['sessions_revoke'] ); ?>" data-smliser-form data-confirm="<?php smliser_esc_attr_e( 'Sign out of every other session?' ); ?>" data-confirm-label="<?php smliser_esc_attr_e( 'Sign out' ); ?>">
				<div class="notice" data-smliser-form-message hidden></div>
				<button type="submit" class="btn"><?php smliser_esc_html_e( 'Sign out of all other sessions' ); ?></button>
			</form>
		</section>
	<?php endif; ?>

	<section class="security-tab-panel security-passkeys" aria-labelledby="security-passkeys-heading">
		<div class="security-method-heading">
			<div><h2 id="security-passkeys-heading"><?php smliser_esc_html_e( 'Passkeys' ); ?></h2><p><?php smliser_esc_html_e( 'Sign in with your fingerprint, face, screen lock or a security key instead of a password. A passkey already proves it is you, so signing in with one skips the second step below.' ); ?></p></div>
			<span class="security-state<?php echo ! empty( $passkeys ) ? ' is-enabled' : ''; ?>"><?php echo ! empty( $passkeys ) ? escHtml( sprintf( smliser__( '%d registered' ), count( $passkeys ) ) ) : smliser__( 'None registered' ); ?></span>
		</div>
		<?php if ( empty( $passkeys ) ) : ?>
			<p class="security-help"><?php smliser_esc_html_e( 'No passkeys are registered yet.' ); ?></p>
		<?php else : ?>
			<ul class="security-passkey-list"><?php foreach ( $passkeys as $passkey ) : ?>
				<li>
					<strong><?php echo escHtml( $passkey['label'] ); ?></strong>
					<form method="post" action="<?php echo escUrl( $actions['passkey_revoke'] ); ?>" data-smliser-form>
						<input type="hidden" name="id" value="<?php echo escAttr( (string) $passkey['id'] ); ?>">
						<div class="notice" data-smliser-form-message hidden></div>
						<input type="text" class="security-email" value="<?php echo escAttr( $email ); ?>" autocomplete="current-email" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
						<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span><input type="password" name="password" autocomplete="current-password" required></label>
						<button type="submit" class="btn btn-small"><?php smliser_esc_html_e( 'Remove passkey' ); ?></button>
					</form>
				</li>
			<?php endforeach; ?></ul>
		<?php endif; ?>
		<form method="post" class="security-passkey-form" data-passkey-register data-start-url="<?php echo escUrl( $actions['passkey_start'] ); ?>" data-finish-url="<?php echo escUrl( $actions['passkey_finish'] ); ?>">
			<div class="notice" data-passkey-message hidden></div>
			<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Passkey name' ); ?></span><input type="text" name="label" maxlength="120" placeholder="<?php smliser_esc_attr_e( 'This device' ); ?>"></label>
			<input type="text" class="security-email" value="<?php echo escAttr( $email ); ?>" autocomplete="current-email" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
			<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span><input type="password" name="password" autocomplete="current-password" required></label>
			<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Add a passkey' ); ?></button>
		</form>
	</section>

	<div class="security-group" data-security-tabs data-active-tab="<?php echo escAttr( $active_tab ); ?>">
		<h2 class="security-group-heading"><?php smliser_esc_html_e( '2Factor Authentication setup' ); ?></h2>
		<p class="security-help"><?php smliser_esc_html_e( 'When you sign in with your password, you also confirm with one of these. Passkey sign-in does not ask for it.' ); ?></p>

		<div class="security-tablist" role="tablist" aria-label="<?php smliser_esc_attr_e( 'Account security methods' ); ?>">
			<button class="security-tab" id="security-tab-email" type="button" role="tab" aria-controls="security-panel-email" aria-selected="<?php echo 'email' === $active_tab ? 'true' : 'false'; ?>" tabindex="<?php echo 'email' === $active_tab ? '0' : '-1'; ?>" data-security-tab="email"><?php smliser_esc_html_e( 'Email codes' ); ?></button>
			<button class="security-tab" id="security-tab-authenticator" type="button" role="tab" aria-controls="security-panel-authenticator" aria-selected="<?php echo 'authenticator' === $active_tab ? 'true' : 'false'; ?>" tabindex="<?php echo 'authenticator' === $active_tab ? '0' : '-1'; ?>" data-security-tab="authenticator"><?php smliser_esc_html_e( 'Authenticator app' ); ?></button>
		</div>

		<section class="security-tab-panel" id="security-panel-email" role="tabpanel" aria-labelledby="security-tab-email" tabindex="0" data-security-panel="email"<?php echo 'email' !== $active_tab ? ' hidden' : ''; ?>>
			<div class="security-method-heading">
				<div><h2><?php smliser_esc_html_e( 'Email verification codes' ); ?></h2><p><?php /* translators: %s: email address. */ printf( smliser_esc_html__( 'Receive a one-time code at %s when you sign in.' ), escHtml( $email ) ); ?></p></div>
				<span class="security-state<?php echo $email_enabled ? ' is-enabled' : ''; ?>"><?php echo $email_enabled ? smliser__( 'Enabled' ) : smliser__( 'Not enabled' ); ?></span>
			</div>
			<?php if ( $email_enabled ) : ?>
				<?php if ( '' !== $email_disable_challenge ) : ?>
					<form method="post" action="<?php echo escUrl( $actions['factor_disable'] ); ?>" data-smliser-form>
						<input type="hidden" name="factor" value="email">
						<input type="hidden" name="challenge" value="<?php echo escAttr( $email_disable_challenge ); ?>">
						<div class="notice" data-smliser-form-message hidden></div>
						<input type="text" class="security-email" value="<?php echo escAttr( $email ); ?>" autocomplete="current-email" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
						<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span><input type="password" name="password" autocomplete="current-password" required></label>
						<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Email confirmation code' ); ?></span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
						<button type="submit" class="btn"><?php smliser_esc_html_e( 'Disable email codes' ); ?></button>
					</form>
				<?php else : ?>
					<form method="post" action="<?php echo escUrl( $actions['factor_disable'] ); ?>" data-smliser-form>
						<input type="hidden" name="factor" value="email">
						<div class="notice" data-smliser-form-message hidden></div>
						<input type="text" class="security-email" value="<?php echo escAttr( $email ); ?>" autocomplete="current-email" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
						<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span><input type="password" name="password" autocomplete="current-password" required></label>
						<button type="submit" class="btn"><?php smliser_esc_html_e( 'Send code to disable' ); ?></button>
					</form>
				<?php endif; ?>
			<?php elseif ( $email_verified ) : ?>
				<form method="post" action="<?php echo escUrl( $actions['email_start'] ); ?>" data-smliser-form>
					<div class="notice" data-smliser-form-message hidden></div>
					<input type="text" class="security-email" value="<?php echo escAttr( $email ); ?>" autocomplete="current-email" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
					<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span><input type="password" name="password" autocomplete="current-password" required></label>
					<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Send enrollment code' ); ?></button>
				</form>
			<?php else : ?>
				<p class="security-help"><?php smliser_esc_html_e( 'Verify your email address before enabling email codes.' ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $email_challenge ) : ?>
				<form method="post" action="<?php echo escUrl( $actions['email_verify'] ); ?>" data-smliser-form>
					<input type="hidden" name="challenge" value="<?php echo escAttr( $email_challenge ); ?>">
					<div class="notice" data-smliser-form-message hidden></div>
					<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Email verification code' ); ?></span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
					<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Verify and enable email codes' ); ?></button>
				</form>
			<?php endif; ?>
		</section>

		<section class="security-tab-panel" id="security-panel-authenticator" role="tabpanel" aria-labelledby="security-tab-authenticator" tabindex="0" data-security-panel="authenticator"<?php echo 'authenticator' !== $active_tab ? ' hidden' : ''; ?>>
			<div class="security-method-heading">
				<div><h2><?php smliser_esc_html_e( 'Authenticator app' ); ?></h2><p><?php smliser_esc_html_e( 'Use an app such as 1Password, Google Authenticator, or Microsoft Authenticator.' ); ?></p></div>
				<span class="security-state<?php echo $totp_enabled ? ' is-enabled' : ''; ?>"><?php echo $totp_enabled ? smliser__( 'Enabled' ) : smliser__( 'Not enabled' ); ?></span>
			</div>
			<?php if ( $totp_enabled ) : ?>
				<form method="post" action="<?php echo escUrl( $actions['factor_disable'] ); ?>" data-smliser-form>
					<input type="hidden" name="factor" value="totp">
					<div class="notice" data-smliser-form-message hidden></div>
					<input type="text" class="security-email" value="<?php echo escAttr( $email ); ?>" autocomplete="current-email" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
					<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span><input type="password" name="password" autocomplete="current-password" required></label>
					<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Authenticator or recovery code' ); ?></span><input type="text" name="code" autocomplete="one-time-code" required></label>
					<button type="submit" class="btn"><?php smliser_esc_html_e( 'Disable authenticator app' ); ?></button>
				</form>
			<?php elseif ( '' !== $totp_challenge && '' !== $totp_secret ) : ?>
				<div class="security-enrollment">
					<div class="security-qr-card">
						<img class="security-qr" src="<?php echo escAttr( $totp_qr_code ); ?>" width="240" height="240" alt="<?php smliser_esc_attr_e( 'Scan this QR code with your authenticator app' ); ?>">
						<p><?php smliser_esc_html_e( 'Scan this code with your authenticator app.' ); ?></p>
					</div>
					<div class="security-enrollment-help">
						<p><?php /* translators: 1: opening link tag, 2: closing link tag. */ printf( smliser_esc_html__( 'Or %1$sopen the authenticator app%2$s on this device.' ), '<a href="' . escUrl( $totp_uri ) . '">', '</a>' ); ?></p>
						<details>
							<summary><?php smliser_esc_html_e( 'Enter the setup key manually' ); ?></summary>
							<p class="security-help"><?php smliser_esc_html_e( 'Keep this key private. Anyone with it can generate your sign-in codes.' ); ?></p>
							<code class="security-secret"><?php echo escHtml( $totp_secret ); ?></code>
						</details>
					</div>
				</div>
				<form method="post" action="<?php echo escUrl( $actions['totp_verify'] ); ?>" data-smliser-form>
					<input type="hidden" name="challenge" value="<?php echo escAttr( $totp_challenge ); ?>">
					<div class="notice" data-smliser-form-message style="white-space:pre-wrap" hidden></div>
					<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Authenticator code' ); ?></span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
					<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Verify and enable' ); ?></button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo escUrl( $actions['totp_start'] ); ?>" data-smliser-form>
					<div class="notice" data-smliser-form-message hidden></div>
					<input type="text" class="security-email" value="<?php echo escAttr( $email ); ?>" autocomplete="current-email" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
					<label class="field"><span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span><input type="password" name="password" autocomplete="current-password" required></label>
					<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Set up authenticator app' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( $recovery_code_count > 0 ) : ?>
				<p class="security-help"><?php echo escHtml( sprintf( smliser__( '%d recovery codes remain.' ), $recovery_code_count ) ); ?> <?php smliser_esc_html_e( 'Keep them somewhere private.' ); ?></p>
			<?php endif; ?>
		</section>

	</div>
</section>