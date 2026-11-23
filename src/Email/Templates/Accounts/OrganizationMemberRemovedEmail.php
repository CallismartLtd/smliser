<?php
/**
 * Organization member removed email template.
 *
 * Sent when a member is removed from an organization.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Accounts;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Security\Owner;

class OrganizationMemberRemovedEmail extends EmailTemplate {

	/**
	 * @param Owner  $organization The organization the member was removed from.
	 * @param string $to           Recipient email address.
	 * @param string $member_name  The name of the member being removed.
	 */
	public function __construct(
		private readonly Owner  $organization,
		private readonly string $to,
		private readonly string $member_name
	) {}

	static public function template_key() : string {
		return 'organization_member_removed';
	}

	protected function subject() : string {
		return "You Have Been Removed from {$this->organization->get_name()}";
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return "Your membership in {$this->organization->get_name()} has been ended.";
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{member_name}}'       => $this->member_name,
			'{{organization_name}}' => $this->organization->get_name(),
		] );
	}

	protected function body() : string {
		$vars              = $this->variables();
		$member_name       = \escHtml( $vars['{{member_name}}'] );
		$organization_name = \escHtml( $vars['{{organization_name}}'] );
		$support           = \escHtml( $vars['{{support_email}}'] );

		$i18n = [
			/* translators: %s: member name */
			sprintf( smliser_esc_html__( 'Hi %s,' ), $member_name ),
			/* translators: %s: organization name (formatted) */
			sprintf( smliser_esc_html__( '🚫 Your membership in %s has been ended. You no longer have access to this organization\'s resources.' ), '<strong>' . $organization_name . '</strong>' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'If you believe this was done in error or would like more information, please reach out to the organization administrator or contact us at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
		];

		return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            {$i18n[0]}
        </p>

        <!-- Notice banner -->
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
        HTML;
	}

	public function label() : string {
		return smliser__( 'Organization Member Removed' );
	}
	public function description() : string {
		return smliser__( 'Sent when a member is removed from an organization.' );
	}
	public static function preview() : static {
		$org = Owner::from_array( [ 'name' => 'Acme Corporation' ] );
		return new static( $org, 'preview@example.com', smliser__( 'Jane Doe' ) );
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => smliser__( 'Hi {{member_name}},' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'banner',
				'type'      => 'banner',
				'tone'      => 'error',
				'content'   => smliser__( 'Your membership in {{organization_name}} has been ended. You no longer have access to this organization\'s resources.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you believe this was done in error or would like more information, please contact us at {{support_email}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
