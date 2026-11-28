<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\Accounts;

use PHPUnit\Framework\TestCase;
use SmartLicenseServer\Email\EmailPreferences;
use SmartLicenseServer\Email\Templates\EmailTemplate;

/**
 * Optional emails are dropped at EmailTemplate::to_message() when their
 * recipient turned the category off; mandatory ones never are.
 */
final class EmailPreferencesTest extends TestCase {

	protected function tearDown() : void {
		EmailPreferences::set_resolver( null );
	}

	/** A template whose key is chosen by the test, enabled without settings storage. */
	private function template( string $key, string $to ) : EmailTemplate {
		$GLOBALS['smliser_test_template_key'] = $key;

		return new class( $to ) extends EmailTemplate {
			public function __construct( private string $to ) {}
			public static function template_key() : string { return $GLOBALS['smliser_test_template_key']; }
			protected function subject() : string { return 'Subject'; }
			protected function recipient() : string { return $this->to; }
			protected function body() : string { return 'Body'; }
			public function label() : string { return 'Label'; }
			public function description() : string { return 'Description'; }
			public static function preview() : static { throw new \LogicException( 'unused' ); }
			public function get_blocks() : array { return []; }
			public function is_enabled() : bool { return true; }
		};
	}

	public function test_security_and_transactional_emails_are_mandatory() : void {
		$mandatory = [
			'password_reset', 'password_changed', 'verify_email', 'email_otp', 'email_changed', 'mfa_reset',
			'passkey_added', 'passkey_removed', 'two_factor_enabled', 'two_factor_disabled', 'welcome',
			'organization_invite', 'organization_member_removed', 'purchase_request_received',
			'purchase_request_approved', 'purchase_request_rejected', 'purchase_request_message',
			'payment_received', 'payment_failed', 'subscription_cancelled', 'license_issued',
			'license_renewed', 'license_suspended', 'app_status_changed', 'app_ownership_changed',
			'admin_new_user_notification', 'admin_purchase_request', 'system_alert', 'test_email',
		];

		foreach ( $mandatory as $key ) {
			$this->assertNull( EmailPreferences::category_of( $key ), $key );
		}

		$this->assertSame( EmailPreferences::LICENSE_REMINDERS, EmailPreferences::category_of( 'license_expiry_reminder' ) );
		$this->assertSame( EmailPreferences::APP_UPDATES, EmailPreferences::category_of( 'new_app_version_notification' ) );
		$this->assertSame( EmailPreferences::ANNOUNCEMENTS, EmailPreferences::category_of( 'bulk_message' ) );
	}

	public function test_every_optional_template_has_a_known_category() : void {
		$categories = array_keys( EmailPreferences::categories() );

		foreach ( EmailPreferences::OPTIONAL_TEMPLATES as $key => $category ) {
			$this->assertContains( $category, $categories, $key );
		}
	}

	public function test_to_message_drops_an_optional_email_the_recipient_turned_off() : void {
		$asked = [];
		EmailPreferences::set_resolver( function ( string $email ) use ( &$asked ) : array {
			$asked[] = $email;
			return [ EmailPreferences::LICENSE_REMINDERS ];
		} );

		$this->assertNull( $this->template( 'license_expiry_reminder', 'Jane <Jane@Example.com>' )->to_message() );
		$this->assertSame( [ 'jane@example.com' ], $asked );
	}

	public function test_mandatory_email_ignores_preferences() : void {
		$asked = false;
		EmailPreferences::set_resolver( function () use ( &$asked ) : array {
			$asked = true;
			return array_keys( EmailPreferences::categories() );
		} );

		$this->assertTrue( $this->template( 'password_reset', 'jane@example.com' )->recipient_accepts() );
		$this->assertFalse( $asked, 'A mandatory email does not even look the preference up.' );
	}

	public function test_other_categories_and_failures_still_send() : void {
		EmailPreferences::set_resolver( static fn () : array => [ EmailPreferences::ANNOUNCEMENTS ] );
		$this->assertTrue( $this->template( 'license_expiry_reminder', 'jane@example.com' )->recipient_accepts() );
		$this->assertFalse( $this->template( 'bulk_message', 'jane@example.com' )->recipient_accepts() );

		EmailPreferences::set_resolver( static fn () : array => throw new \RuntimeException( 'database down' ) );
		$this->assertTrue( $this->template( 'bulk_message', 'jane@example.com' )->recipient_accepts() );

		// A list of recipients cannot be matched to one account.
		EmailPreferences::set_resolver( static fn () : array => [ EmailPreferences::ANNOUNCEMENTS ] );
		$this->assertTrue( EmailPreferences::accepts( EmailPreferences::ANNOUNCEMENTS, 'a@example.com, b@example.com' ) );
	}

	public function test_opt_outs_are_the_categories_not_kept() : void {
		$all = array_keys( EmailPreferences::categories() );

		$this->assertSame( [], EmailPreferences::opt_outs_for( $all ) );
		$this->assertSame( $all, EmailPreferences::opt_outs_for( [] ) );
		$this->assertSame(
			array_values( array_diff( $all, [ EmailPreferences::APP_UPDATES ] ) ),
			EmailPreferences::opt_outs_for( [ EmailPreferences::APP_UPDATES, 'not_a_category', [ 'nested' ] ] )
		);
	}
}
