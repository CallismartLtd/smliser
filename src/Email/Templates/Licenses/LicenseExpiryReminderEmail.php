<?php
/**
 * License expiry reminder email template.
 *
 * Sent X days before a license is due to expire.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Licenses;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Monetization\License;

class LicenseExpiryReminderEmail extends EmailTemplate {

	/**
	 * @param License $license     The license nearing expiry.
	 * @param string  $to          Recipient email address.
	 * @param int     $days_left   Number of days remaining before expiry.
	 */
	public function __construct(
		private readonly License $license,
		private readonly string  $to,
		private readonly int     $days_left
	) {}

	static public function template_key() : string {
		return 'license_expiry_reminder';
	}

	protected function subject() : string {
		return "Your License Expires in {$this->days_left} Day(s)";
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return "Action required — your license expires in {$this->days_left} day(s). Renew now to avoid interruption.";
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{licensee_name}}' => $this->license->get_licensee_fullname(),
			'{{license_key}}'   => $this->license->get_license_key(),
			'{{end_date}}'      => $this->license->get_end_date()->format( \smliser_datetime_format() ),
			'{{days_left}}'     => (string) $this->days_left,
		] );
	}

	protected function body() : string {
		$vars = $this->variables();

		$licensee_name = \escHtml( $vars['{{licensee_name}}'] );
		$license_key   = \escHtml( $vars['{{license_key}}'] );
		$end_date      = \escHtml( $vars['{{end_date}}'] );
		$days_left     = \escHtml( $vars['{{days_left}}'] );
		$support       = \escHtml( $vars['{{support_email}}'] );

		$i18n = [
			/* translators: %s: licensee name */
			sprintf( smliser_esc_html__( 'Hi %s,' ), $licensee_name ),
			/* translators: 1: days left (formatted), 2: closing <strong> tag, 3: end date (formatted) */
			sprintf( smliser_esc_html__( '⚠ Your license expires in %1$s day(s)%2$s on %3$s. Renew now to avoid any interruption to your service.' ), '<strong>' . $days_left, '</strong>', '<strong>' . $end_date . '</strong>' ),
			smliser_esc_html__( 'License Key' ),
			smliser_esc_html__( 'Expiry Date' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'If you have already renewed or have any questions, please contact us at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
		];

		return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            {$i18n[0]}
        </p>

        <!-- Urgency banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fffbeb;border:1px solid #fde68a;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#92400e;line-height:1.5;">
                        {$i18n[1]}
                    </p>
                </td>
            </tr>
        </table>

        <!-- License details card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:24px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[2]}
                                </p>
                                <p style="margin:0;font-size:15px;color:#1a1a2e;
                                           font-family:monospace;font-weight:700;letter-spacing:0.05em;">
                                    {$license_key}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[3]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$end_date}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            {$i18n[4]}
        </p>
        HTML;
	}

	public function label() : string {
		return smliser__( 'License Expiry Reminder' );
	}
	public function description() : string {
		return smliser__( 'Sent a set number of days before a license is due to expire.' );
	}
	public static function preview() : static {
		$license = License::from_array( [
			'licensee_fullname' => 'Jane Doe',
			'license_key'       => 'SMLISER-XXXX-XXXX-XXXX',
			'end_date'          => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
		] );
		return new static( $license, 'preview@example.com', 7 );
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => smliser__( 'Hi {{licensee_name}},' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'banner',
				'type'      => 'banner',
				'tone'      => 'warning',
				'content'   => smliser__( 'Your license expires in {{days_left}} day(s) on {{end_date}}. Renew now to avoid any interruption to your service.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'   => 'details',
				'type' => 'detail_card',
				'rows' => [
					[ 'label' => smliser__( 'License Key' ),   'value' => '{{license_key}}' ],
					[ 'label' => smliser__( 'Expiry Date' ),   'value' => '{{end_date}}' ],
					[ 'label' => smliser__( 'Days Remaining' ), 'value' => '{{days_left}}' ],
				],
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you have already renewed or have any questions, please contact us at {{support_email}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
