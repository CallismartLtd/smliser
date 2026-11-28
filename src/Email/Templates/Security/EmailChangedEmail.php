<?php
/**
 * Email address changed email template.
 *
 * Sent to the previous address after a user changed their account's email
 * address, so the owner of the old address learns of it.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Security;

use SmartLicenseServer\Security\Actors\User;

class EmailChangedEmail extends SecurityEmailTemplate {

	/**
	 * @param User     $user        The user whose account changed.
	 * @param string   $to          The previous email address (the recipient).
	 * @param string   $ip_address  IP address the change came from.
	 * @param string   $user_agent  User agent the change came from.
	 * @param string   $secure_url  Where the "wasn't you?" button leads.
	 * @param int|null $occurred_at Unix timestamp of the change.
	 * @param string   $new_email   The new email address; shown partly hidden.
	 */
	public function __construct(
		User $user,
		string $to,
		string $ip_address = 'unknown',
		string $user_agent = 'unknown',
		string $secure_url = '',
		?int $occurred_at = null,
		private readonly string $new_email = ''
	) {
		parent::__construct( $user, $to, $ip_address, $user_agent, $secure_url, $occurred_at );
	}

	public static function template_key() : string {
		return 'email_changed';
	}

	protected function action() : string {
		return smliser__( 'Email address changed' );
	}

	protected function summary() : string {
		return sprintf(
			/* translators: %s: the new email address, partly hidden. */
			smliser__( 'The email address of your account was changed to %s. Emails about your account now go to that address, and this one can no longer be used to sign in.' ),
			static::mask( $this->new_email )
		);
	}

	public function label() : string {
		return smliser__( 'Email Address Changed' );
	}

	public function description() : string {
		return smliser__( 'Sent to the previous address after a user changes the email address of their account.' );
	}

	public static function preview() : static {
		[ $ip, $agent, $url ] = static::preview_context();

		return new static( static::preview_user(), 'preview@example.com', $ip, $agent, $url, null, 'jane.doe@example.org' );
	}

	/**
	 * An email address with most of its local part hidden: "ja***@example.org".
	 *
	 * @param string $email
	 * @return string
	 */
	public static function mask( string $email ) : string {
		$at = strrpos( $email, '@' );

		if ( false === $at || $at < 1 ) {
			return '***';
		}

		$local = substr( $email, 0, $at );
		$shown = mb_substr( $local, 0, min( 2, max( 1, mb_strlen( $local ) - 1 ) ) );

		return $shown . '***' . substr( $email, $at );
	}
}
