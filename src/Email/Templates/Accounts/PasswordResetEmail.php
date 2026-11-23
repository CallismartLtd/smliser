<?php
/**
 * Password reset email template.
 *
 * Sent when a user requests a password reset.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Accounts;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Security\Actors\User;

class PasswordResetEmail extends EmailTemplate {

	/**
	 * @param User   $user         The user requesting the reset.
	 * @param string $to           Recipient email address.
	 * @param string $reset_url    The password reset link.
	 * @param int    $expires_in   Number of minutes until the reset link expires.
	 * @param string $ip_address   IP address of the requesting user.
	 * @param string $user_agent   User agent of the requesting client.
	 */
	public function __construct(
		private readonly User   $user,
		private readonly string $to,
		private readonly string $reset_url,
		private readonly int    $expires_in,
		private readonly string $ip_address,
		private readonly string $user_agent
	) {}

	static public function template_key() : string {
		return 'password_reset';
	}

	protected function subject() : string {
		return smliser__( 'Reset Your Password' );
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return smliser__( 'We received a request to reset your password. Use the link inside to proceed.' );
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{display_name}}' => $this->user->get_display_name(),
			'{{reset_url}}'    => $this->reset_url,
			'{{expires_in}}'   => (string) $this->expires_in,
			'{{ip_address}}'   => $this->ip_address,
			'{{user_agent}}'   => $this->user_agent,
		] );
	}

	protected function body() : string {
		$vars         = $this->variables();
		$display_name = \escHtml( $vars['{{display_name}}'] );
		$reset_url    = \escHtml( $vars['{{reset_url}}'] );
		$expires_in   = \escHtml( $vars['{{expires_in}}'] );
		$support      = \escHtml( $vars['{{support_email}}'] );
		$ip_address   = \escHtml( $vars['{{ip_address}}'] );
		$user_agent   = \escHtml( $vars['{{user_agent}}'] );

		$i18n = [
			/* translators: %s: display name */
			sprintf( smliser_esc_html__( 'Hi %s,' ), $display_name ),
			smliser_esc_html__( 'We received a request to reset the password for your account. Click the button below to choose a new password.' ),
			/* translators: 1: opening <a> tag, 2: closing <a> tag */
			sprintf( smliser_esc_html__( '%1$s Reset My Password %2$s' ), '<a href="' . $reset_url . '" style="display:inline-block;padding:14px 32px;background-color:#6366f1; color:#ffffff;font-size:15px;font-weight:700;text-decoration:none; border-radius:8px;letter-spacing:0.01em;">', '</a>' ),
			/* translators: 1: ip address (formatted), 2: user agent (formatted) */
			sprintf( smliser_esc_html__( 'Request made from IP: %1$s Device: %2$s' ), '<strong>' . $ip_address . '</strong>' . '<br>', '<strong>' . $user_agent . '</strong>' ),
			/* translators: 1: expires in (formatted), 2: closing <strong> tag */
			sprintf( smliser_esc_html__( '⚠ This link will expire in %1$s minutes%2$s. If it expires, you can request a new one from the login page.' ), '<strong>' . $expires_in, '</strong>' ),
			smliser_esc_html__( 'If the button above does not work, copy and paste the link below into your browser:' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'If you did not request a password reset, you can safely ignore this email. Your password will not change. If you have concerns, contact us at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
		];

		return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            {$i18n[0]}
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            {$i18n[1]}
        </p>

        <!-- Reset button -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:0 0 24px;">
            <tr>
                <td align="center">
                    {$i18n[2]}
                </td>
            </tr>
        </table>

        <!-- Expiry notice -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fffbeb;border:1px solid #fde68a;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:14px 20px;">
                    <p style="margin:0;font-size:13px;color:#92400e;line-height:1.5;">
                        {$i18n[3]}
                    </p>
                </td>
            </tr>
            <tr>
                <td style="padding:14px 20px;">
                    <p style="margin:0;font-size:13px;color:#92400e;line-height:1.5;">
                        {$i18n[4]}
                    </p>
                </td>
            </tr>
        </table>

        <!-- Fallback URL -->
        <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.6;">
            {$i18n[5]}
        </p>
        <p style="margin:0 0 24px;font-size:12px;color:#6366f1;word-break:break-all;">
            <a href="{$reset_url}" style="color:#6366f1;text-decoration:none;">{$reset_url}</a>
        </p>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            {$i18n[6]}
        </p>
        HTML;
	}

	public function label() : string {
		return smliser__( 'Password Reset' );
	}
	
	public function description() : string {
		return smliser__( 'Sent when a user requests a password reset.' );
	}

	public static function preview() : static {
		$user = User::from_array( [ 'display_name' => 'Jane Doe' ] );
		return new static(
			$user,
			'preview@example.com',
			'https://example.com/reset?token=xxxx',
			30,
			'192.168.1.50',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
		);
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => smliser__( 'Hi {{display_name}},' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'intro',
				'type'      => 'text',
				'content'   => smliser__( 'We received a request to reset the password for your account. Click the button below to choose a new password.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'button',
				'type'      => 'button',
				'label'     => smliser__( 'Reset My Password' ),
				'url'       => '{{reset_url}}',
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'expiry_notice',
				'type'      => 'banner',
				'tone'      => 'warning',
				'content'   => smliser__( 'This link will expire in {{expires_in}} minutes. If it expires, you can request a new one from the login page.' ),
				'editable'  => true,
				'removable' => true,
			],
			[
				'id'        => 'fallback',
				'type'      => 'text',
				'content'   => smliser__( 'If the button above does not work, copy and paste this link into your browser: {{reset_url}}' ),
				'editable'  => true,
				'removable' => true,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you did not request a password reset, you can safely ignore this email. Your password will not change. If you have concerns, contact us at {{support_email}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
