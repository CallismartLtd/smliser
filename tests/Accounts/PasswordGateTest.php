<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Tests\Accounts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmartLicenseServer\ClientDashboard\ActionHandlers\AccountManagement;
use SmartLicenseServer\ClientDashboard\ActionHandlers\SecurityManagement;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Accounts\PasswordConfirmation;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Permission\Role;

require_once dirname( __DIR__, 2 ) . '/src/Utils/functions/conditional-functions.php';

if ( ! defined( 'APP_DEBUG' ) ) {
	define( 'APP_DEBUG', false );
}

/**
 * Sensitive account actions stop at a wrong or missing current password,
 * before they read or change anything.
 *
 * The handlers are built without their services: only the guard is set, so
 * an action that went past the password check would fail on a missing one.
 */
final class PasswordGateTest extends TestCase {

	private User $user;

	protected function setUp() : void {
		$this->user = ( new User() )
			->set_id( 9 )
			->set_email( 'jane@example.com' )
			->set_password_hash( password_hash( 'correct horse', PASSWORD_DEFAULT ) );
	}

	protected function tearDown() : void {
		$_POST = [];
	}

	public function test_password_confirmation() : void {
		$this->assertTrue( PasswordConfirmation::matches( $this->user, 'correct horse' ) );
		$this->assertFalse( PasswordConfirmation::matches( $this->user, 'wrong' ) );
		$this->assertFalse( PasswordConfirmation::matches( $this->user, '' ) );
		$this->assertFalse( PasswordConfirmation::matches( ( new User() )->set_id( 1 ), 'anything' ) );
	}

	/**
	 * @return array<string, array{0: string, 1: array<string, string>}>
	 */
	public static function account_actions() : array {
		return [
			'email change'   => [ 'start_email_change', [ 'email' => 'new@example.com' ] ],
			'data export'    => [ 'request_export', [] ],
			'account delete' => [ 'delete_account', [ 'confirm_email' => 'jane@example.com' ] ],
		];
	}

	#[DataProvider( 'account_actions' )]
	public function test_account_actions_need_the_current_password( string $method, array $fields ) : void {
		$handler = $this->handler( AccountManagement::class );

		foreach ( [ '', 'wrong password' ] as $password ) {
			$response = $handler->$method( $this->request( $fields + [ 'password' => $password ] ) );

			$this->assertSame( 403, $response->get_status_code(), $method );
			$this->assertFalse( $this->body( $response )['success'] );
		}
	}

	public function test_password_change_needs_the_current_password() : void {
		$handler  = $this->handler( SecurityManagement::class );
		$response = $handler->change_password( $this->request( [ 'current_password' => 'nope', 'password_1' => 'new password 1', 'password_2' => 'new password 1' ] ) );

		$this->assertSame( 403, $response->get_status_code() );
	}

	public function test_signed_out_requests_are_refused() : void {
		$handler = $this->handler( AccountManagement::class, signed_in: false );

		$this->assertSame( 403, $handler->delete_account( $this->request( [ 'password' => 'correct horse' ] ) )->get_status_code() );
		$this->assertSame( 403, $handler->request_export( $this->request( [ 'password' => 'correct horse' ] ) )->get_status_code() );
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T
	 */
	private function handler( string $class, bool $signed_in = true ) : object {
		$guard = new Guard();
		$guard->set_principal( $signed_in ? new Principal( $this->user, new Role() ) : null );

		$reflection = new \ReflectionClass( $class );
		$handler    = $reflection->newInstanceWithoutConstructor();
		$reflection->getProperty( 'guard' )->setValue( $handler, $guard );

		return $handler;
	}

	/** @param array<string, string> $fields */
	private function request( array $fields ) : Request {
		$_POST = $fields;

		return new Request( $fields, [ 'Accept' => 'application/json' ], 'POST', '/' );
	}

	/** @return array<string, mixed> */
	private function body( Response $response ) : array {
		$body = $response->get_body();

		return is_array( $body ) ? $body : (array) json_decode( (string) $body, true );
	}
}
