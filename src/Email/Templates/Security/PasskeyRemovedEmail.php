<?php
/**
 * Passkey removed email template.
 *
 * Sent after a passkey has been removed from a user's account.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Security;

class PasskeyRemovedEmail extends SecurityEmailTemplate {

	public static function template_key() : string {
		return 'passkey_removed';
	}

	protected function action() : string {
		return smliser__( 'Passkey removed' );
	}

	protected function summary() : string {
		return smliser__( 'A passkey was removed from your account. It can no longer be used to sign in.' );
	}

	public function label() : string {
		return smliser__( 'Passkey Removed' );
	}

	public function description() : string {
		return smliser__( 'Sent after a passkey has been removed from a user\'s account.' );
	}

	public static function preview() : static {
		[ $ip, $agent, $url ] = static::preview_context();

		return new static( static::preview_user(), 'preview@example.com', $ip, $agent, $url );
	}
}
