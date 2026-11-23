<?php
/**
 * MFA reset email template.
 *
 * Sent after a user's two-factor authentication has been reset by an admin.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Accounts;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Security\Actors\User;

class MfaResetEmail extends EmailTemplate {

	/**
	 * @param User   $user  The user whose 2FA was reset.
	 * @param string $to    Recipient email address.
	 */
	public function __construct(
		private readonly User   $user,
		private readonly string $to
	) {}

	static public function template_key() : string {
		return 'mfa_reset';
	}

	protected function subject() : string {
		return smliser__( 'Two-Factor Authentication Has Been Reset' );
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return smliser__( 'Your two-factor authentication and recovery codes were reset by an administrator. If this was not you, secure your account immediately.' );
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{display_name}}' => $this->user->get_display_name(),
			'{{reset_at}}'     => gmdate( 'D, d M Y H:i T' ),
		] );
	}

	protected function body() : string {
		$vars         = $this->variables();
		$display_name = \escHtml( $vars['{{display_name}}'] );
		$reset_at     = \escHtml( $vars['{{reset_at}}'] );
		$support      = \escHtml( $vars['{{support_email}}'] );

		$i18n = [
			/* translators: %s: display name */
			sprintf( smliser_esc_html__( 'Hi %s,' ), $display_name ),
			/* translators: %s: reset at (formatted) */
			sprintf( smliser_esc_html__( '⚠ Your two-factor authentication (TOTP) and recovery codes were reset by an administrator on %s.' ), '<strong>' . $reset_at . '</strong>' ),
			smliser_esc_html__( 'All active login sessions have been terminated across all your devices.' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( '🚫 If you did not request this reset, your account may be compromised. Please contact us immediately at %s so we can secure your account.' ), '<a href="mailto:' . $support . '" style="color:#991b1b;text-decoration:underline;">' . $support . '</a>' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'If you requested this reset due to lost authenticator app access or recovery codes, you can sign in and set up a new authenticator device. For any questions, contact us at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
		];

		return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            {$i18n[0]}
        </p>

        <!-- Warning banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fef2f2;border:1px solid #fecaca;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#991b1b;line-height:1.5;">
                        {$i18n[1]}
                    </p>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            {$i18n[2]}
        </p>

        <!-- Security notice -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fef2f2;border:1px solid #fecaca;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:13px;font-weight:600;color:#991b1b;line-height:1.5;">
                        {$i18n[3]}
                    </p>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            {$i18n[4]}
        </p>
        HTML;
	}

	public function label() : string {
		return smliser__( 'Two-Factor Authentication Reset' );
	}
	public function description() : string {
		return smliser__( 'Sent after a user\'s two-factor authentication has been reset by an administrator.' );
	}
	public static function preview() : static {
		$user = User::from_array( [ 'display_name' => 'Jane Doe' ] );
		return new static( $user, 'preview@example.com' );
	}

	public function get_blocks() : array {
		return [];
	}
}
