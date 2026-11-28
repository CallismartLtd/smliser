<?php
/**
 * Account: profile, email address, email notifications, your data, deleting the account.
 *
 * Forms that ask for the current password carry an unnamed, visually hidden
 * email input, so password managers know which account the password is for.
 *
 * @author Callistus Nwachukwu
 * @var bool $wordpress Whether WordPress manages the profile, email address and account.
 * @var string $wordpress_url The WordPress profile URL.
 * @var string $display_name
 * @var string $email
 * @var bool $email_verified
 * @var string $email_challenge A pending email change, for the code form.
 * @var string $pending_email The address that change goes to.
 * @var list<array{key: string, label: string, description: string, receive: bool}> $notifications
 * @var string[] $deletion_blockers
 * @var array<string, string> $actions
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$hidden_email = '<input type="text" class="security-email" value="' . escAttr( $email ) . '" autocomplete="username" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">';
?>
<section class="panel panel-wide account-page">
	<h1 class="panel-heading"><?php smliser_esc_html_e( 'Your account' ); ?></h1>
	<p class="panel-lead"><?php smliser_esc_html_e( 'Your profile, the emails you receive, and your data.' ); ?></p>

	<?php if ( $wordpress ) : ?>
		<div class="notice" role="status">
			<?php smliser_esc_html_e( 'WordPress manages your name, email address, password and account. Change them from your WordPress profile.' ); ?>
			<?php if ( '' !== $wordpress_url ) : ?>
				<a href="<?php echo escUrl( $wordpress_url ); ?>"><?php smliser_esc_html_e( 'Open your WordPress profile' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<section class="dash-section" aria-labelledby="account-profile">
			<h2 id="account-profile"><?php smliser_esc_html_e( 'Profile' ); ?></h2>
			<form class="dash-form" method="post" action="<?php echo escUrl( $actions['profile'] ); ?>" data-smliser-form>
				<div class="notice" data-smliser-form-message hidden></div>
				<label class="field">
					<span class="field-label"><?php smliser_esc_html_e( 'Display name' ); ?></span>
					<input type="text" name="display_name" value="<?php echo escAttr( $display_name ); ?>" maxlength="100" autocomplete="name" required>
				</label>
				<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Save profile' ); ?></button>
			</form>
		</section>

		<section class="dash-section" aria-labelledby="account-email">
			<h2 id="account-email"><?php smliser_esc_html_e( 'Email address' ); ?></h2>
			<p class="muted">
				<?php /* translators: %s: email address. */ printf( smliser_esc_html__( 'You sign in with %s.' ), '<strong>' . escHtml( $email ) . '</strong>' ); ?>
				<?php echo $email_verified ? smliser_esc_html__( 'It is verified.' ) : smliser_esc_html__( 'It is not verified yet.' ); ?>
			</p>
			<p class="muted"><?php smliser_esc_html_e( 'A code is sent to the new address, and the change is made only once you enter it. Licenses already linked to your account stay linked; licenses issued to the new address are linked once it is verified. Your previous address is told about the change.' ); ?></p>

			<?php if ( '' !== $email_challenge ) : ?>
				<form class="dash-form" method="post" action="<?php echo escUrl( $actions['email_verify'] ); ?>" data-smliser-form>
					<input type="hidden" name="challenge" value="<?php echo escAttr( $email_challenge ); ?>">
					<div class="notice" data-smliser-form-message hidden></div>
					<p><?php /* translators: %s: the new email address. */ printf( smliser_esc_html__( 'Enter the code sent to %s.' ), '<strong>' . escHtml( $pending_email ) . '</strong>' ); ?></p>
					<label class="field">
						<span class="field-label"><?php smliser_esc_html_e( 'Verification code' ); ?></span>
						<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required>
					</label>
					<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Confirm new email address' ); ?></button>
				</form>
			<?php endif; ?>

			<form class="dash-form" method="post" action="<?php echo escUrl( $actions['email_start'] ); ?>" data-smliser-form>
				<div class="notice" data-smliser-form-message hidden></div>
				<label class="field">
					<span class="field-label"><?php smliser_esc_html_e( 'New email address' ); ?></span>
					<input type="email" name="email" autocomplete="email" required>
				</label>
				<label class="field">
					<span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span>
					<input type="password" name="password" autocomplete="current-password" required>
				</label>
				<button type="submit" class="btn"><?php smliser_esc_html_e( 'Send verification code' ); ?></button>
			</form>
		</section>
	<?php endif; ?>

	<section class="dash-section" aria-labelledby="account-notifications">
		<h2 id="account-notifications"><?php smliser_esc_html_e( 'Email notifications' ); ?></h2>
		<p class="muted"><?php smliser_esc_html_e( 'Choose the optional emails you receive. Emails about your account security, purchases, payments, license keys and invitations are always sent.' ); ?></p>
		<?php if ( ! $email_verified ) : ?>
			<p class="muted"><?php smliser_esc_html_e( 'These choices apply once your email address is verified.' ); ?></p>
		<?php endif; ?>
		<form class="dash-form" method="post" action="<?php echo escUrl( $actions['notifications'] ); ?>" data-smliser-form>
			<div class="notice" data-smliser-form-message hidden></div>
			<?php foreach ( $notifications as $notification ) : ?>
				<label class="field field-checkbox">
					<input type="checkbox" name="receive[]" value="<?php echo escAttr( $notification['key'] ); ?>"<?php echo $notification['receive'] ? ' checked' : ''; ?>>
					<span>
						<strong><?php echo escHtml( $notification['label'] ); ?></strong><br>
						<span class="muted"><?php echo escHtml( $notification['description'] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
			<button type="submit" class="btn btn-accent"><?php smliser_esc_html_e( 'Save email preferences' ); ?></button>
		</form>
	</section>

	<section class="dash-section" aria-labelledby="account-data">
		<h2 id="account-data"><?php smliser_esc_html_e( 'Your data' ); ?></h2>
		<p class="muted"><?php smliser_esc_html_e( 'Download a JSON file with your profile, preferences, licenses, purchases, organizations and apps. It never contains passwords, keys or other secrets.' ); ?></p>
		<form class="dash-form" method="post" action="<?php echo escUrl( $actions['export'] ); ?>" data-smliser-form>
			<div class="notice" data-smliser-form-message hidden></div>
			<?php echo $hidden_email; // phpcs:ignore -- built from escaped values above. ?>
			<label class="field">
				<span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span>
				<input type="password" name="password" autocomplete="current-password" required>
			</label>
			<button type="submit" class="btn"><?php smliser_esc_html_e( 'Download my data' ); ?></button>
		</form>
	</section>

	<?php if ( ! $wordpress ) : ?>
		<section class="dash-section" aria-labelledby="account-delete">
			<h2 id="account-delete"><?php smliser_esc_html_e( 'Delete account' ); ?></h2>
			<p class="muted"><?php smliser_esc_html_e( 'Deleting your account signs you out and removes your profile, preferences, sign-in methods and organization memberships for good. Licenses and purchases are kept for the seller\'s records, without the link to your account, and are linked again if you sign up later with the same verified email address.' ); ?></p>

			<?php if ( [] !== $deletion_blockers ) : ?>
				<div class="notice" data-state="error" role="alert">
					<p><?php smliser_esc_html_e( 'Your account cannot be deleted yet:' ); ?></p>
					<ul>
						<?php foreach ( $deletion_blockers as $reason ) : ?>
							<li><?php echo escHtml( $reason ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php else : ?>
				<form class="dash-form" method="post" action="<?php echo escUrl( $actions['delete'] ); ?>" data-smliser-form
					data-confirm="<?php smliser_esc_attr_e( 'Delete your account for good? This cannot be undone.' ); ?>"
					data-confirm-label="<?php smliser_esc_attr_e( 'Delete account' ); ?>" data-confirm-danger>
					<div class="notice" data-smliser-form-message hidden></div>
					<label class="field">
						<?php /* translators: %s: the user's email address. */ ?>
						<span class="field-label"><?php echo escHtml( sprintf( smliser__( 'Type %s to confirm' ), $email ) ); ?></span>
						<input type="text" name="confirm_email" autocomplete="off" required>
					</label>
					<label class="field">
						<span class="field-label"><?php smliser_esc_html_e( 'Current password' ); ?></span>
						<input type="password" name="password" autocomplete="current-password" required>
					</label>
					<button type="submit" class="btn btn-danger"><?php smliser_esc_html_e( 'Delete my account' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</section>
