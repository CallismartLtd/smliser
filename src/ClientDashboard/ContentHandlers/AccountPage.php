<?php
/**
 * Account page class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use SmartLicenseServer\ClientDashboard\ActionHandlers\AccountManagement;
use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\EmailPreferences;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Security\Accounts\AccountDeletion;
use SmartLicenseServer\Security\Authentication\AuthenticationFactorManager;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Account: the signed-in user's profile, email address, email
 * notifications, their data, and deleting the account (a personal page).
 *
 * The actions are AccountManagement's. Under WordPress, the profile, the
 * email address and the account belong to WordPress: those sections point
 * to the WordPress profile instead.
 */
final class AccountPage implements ClientPageInterface {
	use PersonalPageTrait;

	/**
	 * @param Guard                       $guard      The signed-in principal.
	 * @param URLManager                  $urlmanager URL API.
	 * @param AuthenticationFactorManager $factors    Pending email change codes.
	 * @param AccountDeletion             $deletion   Why the account cannot be deleted yet.
	 */
	public function __construct(
		Guard $guard,
		URLManager $urlmanager,
		private AuthenticationFactorManager $factors,
		private AccountDeletion $deletion
	) {
		$this->guard      = $guard;
		$this->urlmanager = $urlmanager;
	}

	public function get_menu_key() : string {
		return 'account';
	}

	public function get_menu_data() : array {
		return [
			'title'      => smliser__( 'Account' ),
			'slug'       => 'account',
			'handler'    => $this,
			'icon'       => 'ti ti-user-circle',
			'visibility' => fn () : bool => $this->is_visible(),
		];
	}

	public function get_submenu() : array {
		return [];
	}

	public function index_page_handler() : callable {
		return [ $this, 'account' ];
	}

	/**
	 * The page.
	 *
	 * View data (template client.account):
	 *   bool   $wordpress        Whether WordPress manages the profile and account.
	 *   string $wordpress_url    The WordPress profile, under WordPress.
	 *   string $display_name, $email
	 *   bool   $email_verified
	 *   string $email_challenge  A pending email change's token, for the code form.
	 *   string $pending_email    The address that change goes to.
	 *   list<array{key: string, label: string, description: string, receive: bool}> $notifications
	 *   string[] $deletion_blockers Why the account cannot be deleted yet.
	 *   array<string, string> $actions
	 *
	 * @param Request $request
	 * @return View
	 */
	public function account( Request $request ) : View {
		$user = $this->current_user();

		if ( ! $user ) {
			return View::make( 'errors.404', [] )->set_status_code( 404 );
		}

		$token   = (string) $request->get( 'email_challenge', '', false );
		$pending = '' !== $token ? $this->factors->get_challenge( $token, AccountManagement::CHANGE_EMAIL ) : null;
		$pending = $pending && (int) $pending['user_id'] === (int) $user->get_id() ? $pending : null;
		$off     = EmailPreferences::turned_off( $user );
		$refusal = $this->deletion->refusal( $user );
		$reasons = $refusal ? ( $refusal->get_error_data()['reasons'] ?? [] ) : [];

		$notifications = [];
		foreach ( EmailPreferences::categories() as $key => $category ) {
			$notifications[] = $category + [ 'key' => $key, 'receive' => ! in_array( $key, $off, true ) ];
		}

		$url = fn ( string $path ) : string => $this->urlmanager->client_dashboard_url( $path )->url();

		return View::make( 'client.account', [
			'wordpress'         => \is_wp(),
			'wordpress_url'     => \is_wp() && function_exists( 'get_edit_profile_url' ) ? (string) \get_edit_profile_url() : '',
			'display_name'      => $user->get_display_name(),
			'email'             => $user->get_email(),
			'email_verified'    => $user->is_email_verified(),
			'email_challenge'   => $pending ? $token : '',
			'pending_email'     => $pending ? (string) ( $pending['payload']['email'] ?? '' ) : '',
			'notifications'     => $notifications,
			'deletion_blockers' => is_array( $reasons ) ? array_values( $reasons ) : [],
			'actions'           => [
				'profile'       => $url( 'json/account/profile' ),
				'email_start'   => $url( 'json/account/email/start' ),
				'email_verify'  => $url( 'json/account/email/verify' ),
				'notifications' => $url( 'json/account/notifications' ),
				'export'        => $url( 'json/account/export' ),
				'delete'        => $url( 'json/account/delete' ),
			],
		] )
			->set_title( smliser__( 'Account' ) )
			->set_header( 'Cache-Control', 'no-store' )
			->set_header( 'Referrer-Policy', 'no-referrer' )
			->add_script( View::FORMS_SCRIPT );
	}
}
