<?php
/**
 * Security notice email background job class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Accounts
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Accounts;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Email\Templates\Accounts\PasswordChangedEmail;
use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Email\Templates\Security\EmailChangedEmail;
use SmartLicenseServer\Email\Templates\Security\PasskeyAddedEmail;
use SmartLicenseServer\Email\Templates\Security\PasskeyRemovedEmail;
use SmartLicenseServer\Email\Templates\Security\TwoFactorDisabledEmail;
use SmartLicenseServer\Email\Templates\Security\TwoFactorEnabledEmail;
use SmartLicenseServer\Security\Actors\User;

/**
 * Asynchronously tells a user that their sign-in security changed.
 *
 * One job covers every security notice; the payload names the template by its
 * template key, and only the keys listed in handle() are accepted.
 */
class SecurityNoticeEmailJob implements JobHandlerInterface {
	public function __construct(
		protected Mailer $mailer,
		protected URLManager $urlmanager
	) {}

	/*
	|----------------------
	| JobHandlerInterface
	|----------------------
	*/

	/**
	 * {@inheritdoc}
	 *
	 * Expected payload keys:
	 *   - user_id     (int)    The user whose account changed.
	 *   - notice      (string) Template key: passkey_added | passkey_removed | two_factor_enabled |
	 *                          two_factor_disabled | password_changed | email_changed.
	 *   - factor      (string) 'email' or 'totp'. Two-factor notices only.
	 *   - old_email   (string) email_changed only: the previous address, which receives the notice.
	 *   - new_email   (string) email_changed only: the new address.
	 *   - ip_address  (string) IP address the change came from.
	 *   - user_agent  (string) User agent the change came from.
	 *   - occurred_at (int)    Unix timestamp of the change.
	 *
	 * @param array<string, mixed> $payload
	 * @return bool|array
	 */
	public function handle( array $payload = [] ) : mixed {
		$user_id = (int) ( $payload['user_id'] ?? 0 );
		$user    = $user_id > 0 ? User::get_by_id( $user_id ) : null;

		if ( ! $user || ! $user->get_email() ) {
			return true;
		}

		$email = $this->make_email( $user, $payload );

		if ( null === $email ) {
			return [ 'success' => false, 'message' => smliser__( 'Unknown security notice.' ) ];
		}

		$message = $email->to_message();

		if ( null === $message ) {
			return [ 'success' => false, 'message' => sprintf( smliser__( 'The "%s" template is disabled.' ), $email->label() ) ];
		}

		return $this->mailer->send( $message )->to_array();
	}

	/**
	 * Build the template for a payload.
	 *
	 * @param User                 $user
	 * @param array<string, mixed> $payload
	 * @return EmailTemplate|null Null when the notice key is not recognised.
	 */
	protected function make_email( User $user, array $payload ) : ?EmailTemplate {
		$notice = (string) ( $payload['notice'] ?? '' );
		$to     = $user->get_email();

		// The owner of the previous address is the one who must hear of the change.
		if ( EmailChangedEmail::template_key() === $notice ) {
			$to = (string) ( $payload['old_email'] ?? '' );

			if ( false === filter_var( $to, FILTER_VALIDATE_EMAIL ) ) {
				return null;
			}
		}

		$args = [
			$user,
			$to,
			(string) ( $payload['ip_address'] ?? 'unknown' ),
			(string) ( $payload['user_agent'] ?? 'unknown' ),
			(string) $this->urlmanager->login_url( 'forgot-password' ),
			isset( $payload['occurred_at'] ) ? (int) $payload['occurred_at'] : null,
		];
		$factor = (string) ( $payload['factor'] ?? 'totp' );

		return match ( $notice ) {
			PasswordChangedEmail::template_key()   => new PasswordChangedEmail( $user, $to ),
			EmailChangedEmail::template_key()      => new EmailChangedEmail( ...$args, new_email: (string) ( $payload['new_email'] ?? '' ) ),
			PasskeyAddedEmail::template_key()      => new PasskeyAddedEmail( ...$args ),
			PasskeyRemovedEmail::template_key()    => new PasskeyRemovedEmail( ...$args ),
			TwoFactorEnabledEmail::template_key()  => new TwoFactorEnabledEmail( ...$args, factor: $factor ),
			TwoFactorDisabledEmail::template_key() => new TwoFactorDisabledEmail( ...$args, factor: $factor ),
			default                                => null,
		};
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name() : string {
		return smliser__( 'Send Security Notice Email' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description() : string {
		return smliser__( 'Asynchronously notify a user when their password or email address changes, or a passkey or two-factor method is added, removed, enabled or disabled.' );
	}
}
