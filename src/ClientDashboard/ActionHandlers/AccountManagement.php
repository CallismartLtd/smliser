<?php
/**
 * Client account management class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\Background\Jobs\Accounts\EmailOtpJob;
use SmartLicenseServer\Background\Jobs\Accounts\SecurityNoticeEmailJob;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Core\AvatarManager;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\EmailPreferences;
use SmartLicenseServer\Environments\Application\Auth\WebIdentityProvider;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Accounts\AccountDataExport;
use SmartLicenseServer\Security\Accounts\AccountDeletion;
use SmartLicenseServer\Security\Accounts\PasswordConfirmation;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\AuthenticationFactorManager;
use SmartLicenseServer\Security\Authentication\UserAccounts;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\SecurityLogger;

/**
 * The client dashboard's account actions (<client dashboard prefix>/json/account/…),
 * behind ClientAccessMiddleware and CSRFMiddleware:
 *
 *   POST account/profile            Display name ("display_name").
 *   POST account/email/start        Change the email address ("password", "email"): a code goes to the new address.
 *   POST account/email/verify       Confirm it ("challenge", "code"); the old address is told.
 *   POST account/notifications      Optional emails to keep receiving ("receive[]").
 *   POST account/export             Ask for the data export ("password"); answers with a one-time download link.
 *   GET  account/export/download    The export file ("token").
 *   POST account/delete             Delete the account ("password", "confirm_email").
 *
 * Under WordPress, WordPress owns the profile, email address and the
 * account itself: those actions are refused there.
 *
 * Answer: { success, message, redirect? } for page-forms.js.
 */
final class AccountManagement {
	use QueueAwareTrait;

	/** Longest display name. */
	public const MAX_NAME_LENGTH = 100;

	/** Shortest time between two data exports of one user, in seconds. */
	public const EXPORT_INTERVAL = 300;

	/** How long an export download link works, in seconds. */
	public const EXPORT_LINK_TTL = 120;

	/** Challenge purpose of an email change. */
	public const CHANGE_EMAIL = 'change_email';

	public function __construct(
		private Guard $guard,
		private URLManager $urls,
		private UserAccounts $accounts,
		private AuthenticationFactorManager $factors,
		private WebIdentityProvider $identity,
		private AccountDataExport $export,
		private AccountDeletion $deletion,
		private AvatarManager $avatars,
		private SecurityLogger $logger,
		private Cache $cache,
		JobQueue $job_queue
	) {
		$this->job_queue = $job_queue;
	}

	/*
	|---------
	| PROFILE
	|---------
	*/

	/** Change the display name. */
	public function update_profile( Request $request ) : Response {
		$user = $this->current_user();
		if ( ! $user ) {
			return $this->signed_out();
		}
		if ( \is_wp() ) {
			return $this->managed_by_wordpress();
		}

		$name = trim( (string) $request->post( 'display_name', '' ) );
		if ( '' === $name ) {
			return $this->answer( false, smliser__( 'Enter the name to show on your account.' ), 400 );
		}
		if ( mb_strlen( $name ) > self::MAX_NAME_LENGTH ) {
			/* translators: %d: maximum number of characters. */
			return $this->answer( false, sprintf( smliser__( 'The name can be at most %d characters long.' ), self::MAX_NAME_LENGTH ), 400 );
		}

		if ( ! $user->set_display_name( $name )->save() ) {
			return $this->answer( false, smliser__( 'Your profile could not be saved. Please try again.' ), 500 );
		}

		return $this->answer( true, smliser__( 'Your profile was saved.' ), 200, $this->page_url() );
	}

	/*
	|---------------
	| EMAIL ADDRESS
	|---------------
	*/

	/**
	 * Start changing the email address.
	 *
	 * Nothing changes yet: a code goes to the new address, and the address
	 * switches only once that code is entered (verify_email_change()).
	 */
	public function start_email_change( Request $request ) : Response {
		$user = $this->current_user();
		if ( ! $user ) {
			return $this->signed_out();
		}
		if ( \is_wp() ) {
			return $this->managed_by_wordpress();
		}
		if ( ! PasswordConfirmation::matches( $user, (string) $request->post( 'password', '' ) ) ) {
			return $this->wrong_password();
		}

		$email = strtolower( trim( (string) $request->post( 'email', '' ) ) );
		if ( '' === $email || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return $this->answer( false, smliser__( 'Please provide a valid email address.' ), 400 );
		}
		if ( $email === strtolower( $user->get_email() ) ) {
			return $this->answer( false, smliser__( 'This is already your email address.' ), 400 );
		}
		if ( $this->email_taken( $email, $user ) ) {
			return $this->answer( false, smliser__( 'That email address is already used by another account.' ), 409 );
		}
		if ( ! $this->accounts->acquire_verification_resend( $user ) ) {
			return $this->answer( false, smliser__( 'A code was sent a moment ago. Wait a minute before asking for another.' ), 429 );
		}

		try {
			$challenge = $this->factors->create_email_code_challenge( (int) $user->get_id(), self::CHANGE_EMAIL, [ 'email' => $email ] );
			$this->dispatch_job( EmailOtpJob::class, [
				'user_id'     => $user->get_id(),
				'code'        => $this->factors->seal_code( $challenge['code'] ),
				'purpose'     => self::CHANGE_EMAIL,
				'to'          => $email,
				'ip_address'  => $request->ip(),
				'user_agent'  => $request->userAgent(),
				'occurred_at' => time(),
			] );
		} catch ( \Throwable $e ) {
			if ( isset( $challenge['token'] ) ) {
				$this->factors->consume_challenge( $challenge['token'] );
			}
			\smliser_log_error( sprintf( '[Accounts] Could not send an email change code: %s', $e->getMessage() ) );
			return $this->answer( false, smliser__( 'The verification code could not be sent. Please try again.' ), 503 );
		}

		return $this->answer(
			true,
			/* translators: %s: the new email address. */
			sprintf( smliser__( 'A verification code was sent to %s. Enter it to finish the change.' ), $email ),
			200,
			$this->page_url( [ 'email_challenge' => $challenge['token'] ] )
		);
	}

	/**
	 * Finish changing the email address with the code sent to it.
	 *
	 * The new address is verified by the code, so licenses issued to it
	 * that no account holds yet are linked to the user, exactly as when an
	 * address is verified after signup. Licenses already linked stay
	 * linked and keep their licensee email. The old address is told.
	 */
	public function verify_email_change( Request $request ) : Response {
		$user  = $this->current_user();
		$token = trim( (string) $request->post( 'challenge', '' ) );
		if ( ! $user ) {
			return $this->signed_out();
		}
		if ( \is_wp() ) {
			return $this->managed_by_wordpress();
		}

		$pending = $this->factors->get_challenge( $token, self::CHANGE_EMAIL );
		if ( ! $pending || (int) $pending['user_id'] !== (int) $user->get_id() ) {
			return $this->answer( false, smliser__( 'This verification request has expired. Start again.' ), 400 );
		}
		$challenge = $this->factors->verify_email_code( $token, self::CHANGE_EMAIL, (string) $request->post( 'code', '' ) );
		if ( ! $challenge ) {
			return $this->answer( false, smliser__( 'The verification code is incorrect or expired.' ), 400 );
		}

		$new = strtolower( (string) ( $challenge['payload']['email'] ?? '' ) );
		if ( false === filter_var( $new, FILTER_VALIDATE_EMAIL ) ) {
			return $this->answer( false, smliser__( 'This verification request has expired. Start again.' ), 400 );
		}
		// Taken while the code was on its way.
		if ( $this->email_taken( $new, $user ) ) {
			return $this->answer( false, smliser__( 'That email address is already used by another account.' ), 409 );
		}

		$old     = $user->get_email();
		$old_uid = $user->get_unique_identifier();

		$user->set_email( $new )->set_email_verified_at( gmdate( 'Y-m-d H:i:s' ) );

		try {
			if ( ! $user->save() ) {
				throw new \RuntimeException( 'The user could not be saved.' );
			}
			$linked = License::link_to_user( $user );
		} catch ( \Throwable $e ) {
			\smliser_log_error( sprintf( '[Accounts] User #%d could not change their email address: %s', $user->get_id(), $e->getMessage() ) );
			return $this->answer( false, smliser__( 'Your email address could not be changed. Please try again.' ), 500 );
		}

		// The avatar is stored under an ID made from the email address.
		if ( $old_uid !== $user->get_unique_identifier() ) {
			$this->avatars->rename( $user->get_type(), $old_uid, $user->get_unique_identifier() );
		}

		$this->logger->info( 'email_changed', sprintf( 'User #%d changed their email address.', $user->get_id() ), [ 'from' => $old, 'to' => $new, 'licenses_linked' => $linked, 'ip' => $request->ip() ] );

		try {
			$this->dispatch_job( SecurityNoticeEmailJob::class, [
				'user_id'     => $user->get_id(),
				'notice'      => 'email_changed',
				'old_email'   => $old,
				'new_email'   => $new,
				'ip_address'  => $request->ip(),
				'user_agent'  => $request->userAgent(),
				'occurred_at' => time(),
			] );
		} catch ( \Throwable $e ) {
			\smliser_log_error( sprintf( '[Accounts] Could not queue the "email_changed" notice: %s', $e->getMessage() ) );
		}

		return $this->answer( true, smliser__( 'Your email address was changed. We let your previous address know.' ), 200, $this->page_url() );
	}

	/*
	|---------------------
	| EMAIL NOTIFICATIONS
	|---------------------
	*/

	/** Save which optional emails the user keeps receiving. */
	public function save_notifications( Request $request ) : Response {
		$user = $this->current_user();
		if ( ! $user ) {
			return $this->signed_out();
		}

		$receive = $request->post( 'receive', [] );

		try {
			$saved = EmailPreferences::save( $user, is_array( $receive ) ? $receive : [] );
		} catch ( \Throwable ) {
			$saved = false;
		}

		return $saved
			? $this->answer( true, smliser__( 'Your email preferences were saved.' ), 200, $this->page_url() )
			: $this->answer( false, smliser__( 'Your email preferences could not be saved. Please try again.' ), 500 );
	}

	/*
	|-------------
	| DATA EXPORT
	|-------------
	*/

	/**
	 * Ask for the data export, after password confirmation.
	 *
	 * Answers with a one-time link that works for EXPORT_LINK_TTL seconds;
	 * a user gets at most one export every EXPORT_INTERVAL seconds.
	 */
	public function request_export( Request $request ) : Response {
		$user = $this->current_user();
		if ( ! $user ) {
			return $this->signed_out();
		}
		if ( ! PasswordConfirmation::matches( $user, (string) $request->post( 'password', '' ) ) ) {
			return $this->wrong_password();
		}

		$throttle = sprintf( 'account_export_%d', (int) $user->get_id() );
		if ( $this->cache->has( $throttle ) ) {
			return $this->answer( false, smliser__( 'You downloaded your data a few minutes ago. Please wait before asking again.' ), 429 );
		}

		$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		$this->cache->set( $throttle, time(), self::EXPORT_INTERVAL );
		$this->cache->set( self::export_key( $token ), (int) $user->get_id(), self::EXPORT_LINK_TTL );

		return $this->answer(
			true,
			smliser__( 'Your data is ready. The download starts now.' ),
			200,
			$this->urls->client_dashboard_url( 'json/account/export/download', [ 'token' => $token ] )->url()
		);
	}

	/** The export file, for a link from request_export(). */
	public function download_export( Request $request ) : Response {
		$user  = $this->current_user();
		$token = (string) $request->get( 'token', '' );
		$key   = 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/', $token ) ? self::export_key( $token ) : '';
		$owner = '' !== $key ? $this->cache->get( $key ) : null;

		if ( '' !== $key ) {
			// One use only.
			$this->cache->delete( $key );
		}

		if ( ! $user || ! is_int( $owner ) || $owner !== (int) $user->get_id() ) {
			return Response::make( \escHtml( smliser__( 'This download link has expired. Ask for your data again from your account page.' ) ), 403 )
				->set_header( 'Content-Type', 'text/html; charset=utf-8' );
		}

		try {
			$json = $this->export->to_json( $user );
		} catch ( \Throwable $e ) {
			\smliser_log_error( sprintf( '[Accounts] The data export of user #%d failed: %s', $user->get_id(), $e->getMessage() ) );
			return Response::make( \escHtml( smliser__( 'Your data could not be exported. Please try again.' ) ), 500 )
				->set_header( 'Content-Type', 'text/html; charset=utf-8' );
		}

		$this->logger->info( 'account_exported', sprintf( 'User #%d downloaded their account data.', $user->get_id() ), [ 'ip' => $request->ip() ] );

		return Response::make( $json, 200, [
			'Content-Type'           => 'application/json; charset=utf-8',
			'Content-Disposition'    => sprintf( 'attachment; filename="%s"', AccountDataExport::filename( $user ) ),
			'Cache-Control'          => 'no-store',
			'X-Content-Type-Options' => 'nosniff',
		] );
	}

	/*
	|----------------
	| ACCOUNT DELETE
	|----------------
	*/

	/**
	 * Delete the account (AccountDeletion), then sign out.
	 *
	 * Needs the current password and the account's email address typed out.
	 */
	public function delete_account( Request $request ) : Response {
		$user = $this->current_user();
		if ( ! $user ) {
			return $this->signed_out();
		}
		if ( \is_wp() ) {
			return $this->managed_by_wordpress();
		}
		if ( ! PasswordConfirmation::matches( $user, (string) $request->post( 'password', '' ) ) ) {
			return $this->wrong_password();
		}
		if ( strtolower( trim( (string) $request->post( 'confirm_email', '' ) ) ) !== strtolower( $user->get_email() ) ) {
			return $this->answer( false, smliser__( 'Type your email address exactly as shown to confirm.' ), 400 );
		}

		$id    = (int) $user->get_id();
		$email = $user->get_email();

		try {
			$this->deletion->delete( $user );
		} catch ( Exception $e ) {
			$data = $e->get_error_data();
			return $this->answer( false, $e->get_error_message(), (int) ( is_array( $data ) ? ( $data['status'] ?? 400 ) : 400 ) );
		}

		$this->logger->info( 'account_deleted', sprintf( 'User #%d deleted their own account.', $id ), [ 'email' => $email, 'ip' => $request->ip() ] );
		$this->identity->logout();
		$this->guard->set_principal( null );

		return $this->answer( true, smliser__( 'Your account was deleted. Goodbye.' ), 200, (string) $this->urls->url( '/' ) );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	private function current_user() : ?User {
		$actor = $this->guard->get_principal()?->get_actor();

		return $actor instanceof User ? $actor : null;
	}

	/** Whether another account uses the address. */
	private function email_taken( string $email, User $user ) : bool {
		$other = User::get_by_email( $email );

		return $other instanceof User && (int) $other->get_id() !== (int) $user->get_id();
	}

	private static function export_key( string $token ) : string {
		return 'account_export_link_' . hash( 'sha256', $token );
	}

	/** @param array<string, string> $args */
	private function page_url( array $args = [] ) : string {
		return $this->urls->client_dashboard_url( 'account', $args )->url();
	}

	private function signed_out() : Response {
		return $this->answer( false, smliser__( 'Sign in with a user account to manage your account.' ), 403 );
	}

	private function wrong_password() : Response {
		return $this->answer( false, smliser__( 'Your current password is incorrect.' ), 403 );
	}

	private function managed_by_wordpress() : Response {
		return $this->answer( false, smliser__( 'WordPress manages this account. Make this change from your WordPress profile.' ), 409 );
	}

	private function answer( bool $success, string $message, int $status, string $redirect = '' ) : Response {
		$body = [ 'success' => $success, 'message' => $message ];
		if ( '' !== $redirect ) {
			$body['redirect'] = $redirect;
		}

		return Response::json( $body, $status );
	}
}
