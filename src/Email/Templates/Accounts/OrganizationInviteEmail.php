<?php
/**
 * Organization invite email template.
 *
 * Sent when a user is invited to join an organization.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Accounts;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Security\Owner;

class OrganizationInviteEmail extends EmailTemplate {

	/**
	 * @param Owner  $organization The organization the user is being invited to.
	 * @param string $to           Recipient email address.
	 * @param string $invitee_name The name of the person being invited.
	 * @param string $inviter_name The name of the person sending the invite.
	 * @param string $invite_url   The URL to accept the invitation.
	 * @param int    $expires_in   Number of hours until the invite link expires.
	 */
	public function __construct(
		private readonly Owner  $organization,
		private readonly string $to,
		private readonly string $invitee_name,
		private readonly string $inviter_name,
		private readonly string $invite_url,
		private readonly int    $expires_in = 48
	) {}

	static public function template_key() : string {
		return 'organization_invite';
	}

	protected function subject() : string {
		return "You Have Been Invited to Join {$this->organization->get_name()}";
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return "{$this->inviter_name} has invited you to join {$this->organization->get_name()}. Accept your invitation inside.";
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{invitee_name}}'      => $this->invitee_name,
			'{{inviter_name}}'      => $this->inviter_name,
			'{{organization_name}}' => $this->organization->get_name(),
			'{{invite_url}}'        => $this->invite_url,
			'{{expires_in}}'        => (string) $this->expires_in,
		] );
	}

	protected function body() : string {
		$vars              = $this->variables();
		$invitee_name      = \escHtml( $vars['{{invitee_name}}'] );
		$inviter_name      = \escHtml( $vars['{{inviter_name}}'] );
		$organization_name = \escHtml( $vars['{{organization_name}}'] );
		$invite_url        = \escHtml( $vars['{{invite_url}}'] );
		$expires_in        = \escHtml( $vars['{{expires_in}}'] );
		$support           = \escHtml( $vars['{{support_email}}'] );

		$i18n = [
			/* translators: %s: invitee name */
			sprintf( smliser_esc_html__( 'Hi %s,' ), $invitee_name ),
			/* translators: 1: inviter name (formatted), 2: organization name (formatted) */
			sprintf( smliser_esc_html__( '%1$s has invited you to join %2$s. Accept the invitation below to get started.' ), '<strong>' . $inviter_name . '</strong>', '<strong>' . $organization_name . '</strong>' ),
			/* translators: 1: opening <a> tag, 2: closing <a> tag */
			sprintf( smliser_esc_html__( '%1$s Accept Invitation %2$s' ), '<a href="' . $invite_url . '" style="display:inline-block;padding:14px 32px;background-color:#6366f1; color:#ffffff;font-size:15px;font-weight:700;text-decoration:none; border-radius:8px;letter-spacing:0.01em;">', '</a>' ),
			/* translators: 1: expires in (formatted), 2: closing <strong> tag, 3: inviter name (formatted) */
			sprintf( smliser_esc_html__( '⚠ This invitation link will expire in %1$s hours%2$s. If it expires, please ask %3$s to send a new invitation.' ), '<strong>' . $expires_in, '</strong>', '<strong>' . $inviter_name . '</strong>' ),
			smliser_esc_html__( 'If the button above does not work, copy and paste the link below into your browser:' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'If you did not expect this invitation or have any concerns, please contact us at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
		];

		return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            {$i18n[0]}
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            {$i18n[1]}
        </p>

        <!-- Accept button -->
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
        </table>

        <!-- Fallback URL -->
        <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.6;">
            {$i18n[4]}
        </p>
        <p style="margin:0 0 24px;font-size:12px;word-break:break-all;">
            <a href="{$invite_url}" style="color:#6366f1;text-decoration:none;">{$invite_url}</a>
        </p>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            {$i18n[5]}
        </p>
        HTML;
	}

	public function label() : string {
		return smliser__( 'Organization Invite' );
	}
	public function description() : string {
		return smliser__( 'Sent when a user is invited to join an organization.' );
	}
	public static function preview() : static {
		$org = Owner::from_array( [ 'name' => 'Acme Corporation' ] );
		return new static( $org, 'preview@example.com', smliser__( 'Jane Doe' ), smliser__( 'John Smith' ), 'https://example.com/invite?token=xxxx', 48 );
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => smliser__( 'Hi {{invitee_name}},' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'intro',
				'type'      => 'text',
				'content'   => smliser__( '{{inviter_name}} has invited you to join {{organization_name}}. Accept the invitation below to get started.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'button',
				'type'      => 'button',
				'label'     => smliser__( 'Accept Invitation' ),
				'url'       => '{{invite_url}}',
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'expiry_notice',
				'type'      => 'banner',
				'tone'      => 'warning',
				'content'   => smliser__( 'This invitation link will expire in {{expires_in}} hours. If it expires, please ask {{inviter_name}} to send a new invitation.' ),
				'editable'  => true,
				'removable' => true,
			],
			[
				'id'        => 'fallback',
				'type'      => 'text',
				'content'   => smliser__( 'If the button above does not work, copy and paste this link into your browser: {{invite_url}}' ),
				'editable'  => true,
				'removable' => true,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you did not expect this invitation or have any concerns, please contact us at {{support_email}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
