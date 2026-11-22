<?php
/**
 * Email verification email template.
 *
 * Sent after signup, and when a user asks for a new verification link.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Accounts;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Security\Actors\User;

class VerifyEmailEmail extends EmailTemplate {

	/**
	 * @param User   $user        The user verifying their email address.
	 * @param string $to          Recipient email address.
	 * @param string $verify_url  The verification link.
	 * @param int    $expires_in  Number of hours until the link expires.
	 */
	public function __construct(
		private readonly User   $user,
		private readonly string $to,
		private readonly string $verify_url,
		private readonly int    $expires_in
	) {}

	static public function template_key() : string {
		return 'verify_email';
	}

	protected function subject() : string {
		return smliser__( 'Verify Your Email Address' );
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return smliser__( 'Confirm this is your email address to finish setting up your account.' );
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{display_name}}' => $this->user->get_display_name(),
			'{{verify_url}}'   => $this->verify_url,
			'{{expires_in}}'   => (string) $this->expires_in,
		] );
	}

	protected function body() : string {
		$vars         = $this->variables();
		$display_name = \escHtml( $vars['{{display_name}}'] );
		$verify_url   = \escHtml( $vars['{{verify_url}}'] );
		$expires_in   = \escHtml( $vars['{{expires_in}}'] );
		$support      = \escHtml( $vars['{{support_email}}'] );

		$i18n = [
			/* translators: %s: display name */
			sprintf( smliser_esc_html__( 'Hi %s,' ), $display_name ),
			smliser_esc_html__( 'Please confirm that this is your email address. Licenses issued to this address will then appear in your account.' ),
			/* translators: 1: opening <a> tag, 2: closing <a> tag */
			sprintf( smliser_esc_html__( '%1$s Verify My Email %2$s' ), '<a href="' . $verify_url . '" style="display:inline-block;padding:14px 32px;background-color:#6366f1; color:#ffffff;font-size:15px;font-weight:700;text-decoration:none; border-radius:8px;letter-spacing:0.01em;">', '</a>' ),
			/* translators: 1: expires in (formatted), 2: closing <strong> tag */
			sprintf( smliser_esc_html__( '⚠ This link will expire in %1$s hours%2$s. If it expires, you can ask for a new one from your account.' ), '<strong>' . $expires_in, '</strong>' ),
			smliser_esc_html__( 'If the button above does not work, copy and paste the link below into your browser:' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'If you did not create an account, you can safely ignore this email. If you have concerns, contact us at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
		];

		return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            {$i18n[0]}
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            {$i18n[1]}
        </p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:0 0 24px;">
            <tr>
                <td align="center">
                    {$i18n[2]}
                </td>
            </tr>
        </table>

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
        </table>

        <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.6;">
            {$i18n[4]}
        </p>
        <p style="margin:0 0 24px;font-size:12px;color:#6366f1;word-break:break-all;">
            <a href="{$verify_url}" style="color:#6366f1;text-decoration:none;">{$verify_url}</a>
        </p>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            {$i18n[5]}
        </p>
        HTML;
	}

	public function label() : string {
		return smliser__( 'Verify Email' );
	}

	public function description() : string {
		return smliser__( 'Sent after signup, and when a user asks for a new verification link.' );
	}

	public static function preview() : static {
		$user = User::from_array( [ 'display_name' => 'Jane Doe' ] );

		return new static( $user, 'preview@example.com', 'https://example.com/auth/verify-email?key=xxxx', 48 );
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
				'content'   => smliser__( 'Please confirm that this is your email address. Licenses issued to this address will then appear in your account.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'button',
				'type'      => 'button',
				'label'     => smliser__( 'Verify My Email' ),
				'url'       => '{{verify_url}}',
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'expiry_notice',
				'type'      => 'banner',
				'tone'      => 'warning',
				'content'   => smliser__( 'This link will expire in {{expires_in}} hours. If it expires, you can ask for a new one from your account.' ),
				'editable'  => true,
				'removable' => true,
			],
			[
				'id'        => 'fallback',
				'type'      => 'text',
				'content'   => smliser__( 'If the button above does not work, copy and paste this link into your browser: {{verify_url}}' ),
				'editable'  => true,
				'removable' => true,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you did not create an account, you can safely ignore this email. If you have concerns, contact us at {{support_email}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
