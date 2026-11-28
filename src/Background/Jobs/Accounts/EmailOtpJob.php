<?php
/**
 * Email one-time code background job class file.
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
use SmartLicenseServer\Email\Templates\Security\EmailOtpEmail;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\AuthenticationFactorManager;

/**
 * Asynchronously sends a one-time code by email.
 *
 * The code travels in the payload sealed (see AuthenticationFactorManager::seal_code()),
 * because job payloads are stored as readable JSON while challenges keep only a
 * digest of their code.
 */
class EmailOtpJob implements JobHandlerInterface {
	public function __construct(
		protected Mailer $mailer,
		protected AuthenticationFactorManager $factors,
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
	 *   - user_id    (int)    The user the code was issued to.
	 *   - code       (string) The code, sealed by AuthenticationFactorManager::seal_code().
	 *   - purpose    (string) Challenge purpose: login_email | enroll_email | disable_email | change_email.
	 *   - to         (string) change_email only: the new address being verified, which receives the code.
	 *   - ip_address (string) IP address the code was requested from.
	 *   - user_agent (string) User agent the code was requested from.
	 *   - occurred_at (int)   Unix timestamp of the request.
	 *
	 * @param array<string, mixed> $payload
	 * @return bool|array
	 * @throws \RuntimeException When the email could not be delivered, so the worker retries.
	 */
	public function handle( array $payload = [] ) : mixed {
		$user_id = (int) ( $payload['user_id'] ?? 0 );
		$user    = $user_id > 0 ? User::get_by_id( $user_id ) : null;

		if ( ! $user || ! $user->get_email() ) {
			return true;
		}

		$code = $this->factors->open_code( (string) ( $payload['code'] ?? '' ) );

		if ( null === $code ) {
			return [ 'success' => false, 'message' => smliser__( 'The verification code could not be read.' ) ];
		}

		$purpose   = (string) ( $payload['purpose'] ?? '' );
		$recipient = $user->get_email();

		// A new address proves it is reachable by receiving the code itself.
		if ( 'change_email' === $purpose ) {
			$recipient = (string) ( $payload['to'] ?? '' );

			if ( false === filter_var( $recipient, FILTER_VALIDATE_EMAIL ) ) {
				return [ 'success' => false, 'message' => smliser__( 'The new email address is not valid.' ) ];
			}
		}

		$email = new EmailOtpEmail(
			$user,
			$recipient,
			$code,
			// The label is part of the email, so it is in the user's language too.
			smliser_with_locale( smliser_user_locale( $user ), static fn () : string => static::purpose_label( $purpose ) ),
			intdiv( AuthenticationFactorManager::CHALLENGE_TTL, 60 ),
			(string) ( $payload['ip_address'] ?? 'unknown' ),
			(string) ( $payload['user_agent'] ?? 'unknown' ),
			(string) $this->urlmanager->login_url( 'forgot-password' ),
			isset( $payload['occurred_at'] ) ? (int) $payload['occurred_at'] : null
		);

		$message = $email->to_message();

		if ( null === $message ) {
			return [ 'success' => false, 'message' => smliser__( 'The "Email Verification Code" template is disabled.' ) ];
		}

		$response = $this->mailer->send( $message );

		// The request that queued this code already answered "sent", so a
		// failed delivery has to surface here and be retried.
		if ( $response->is_failure() ) {
			throw new \RuntimeException( 'The verification code email could not be delivered.' );
		}

		return $response->to_array();
	}

	/**
	 * What the code is for, as shown in the email.
	 *
	 * @param string $purpose Challenge purpose.
	 * @return string
	 */
	protected static function purpose_label( string $purpose ) : string {
		return match ( $purpose ) {
			'login_email'   => smliser__( 'Sign-in verification code' ),
			'enroll_email'  => smliser__( 'Enable email two-factor authentication' ),
			'disable_email' => smliser__( 'Disable email two-factor authentication' ),
			'change_email'  => smliser__( 'Confirm your new email address' ),
			default         => smliser__( 'Verification code' ),
		};
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name() : string {
		return smliser__( 'Send Email Verification Code' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description() : string {
		return smliser__( 'Asynchronously send a one-time sign-in or two-factor confirmation code by email.' );
	}
}
