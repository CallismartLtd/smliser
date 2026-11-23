<?php
/**
 * Test email template.
 *
 * Sent when an administrator tests an email provider configuration
 * from the email settings page.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\System;

use SmartLicenseServer\Email\Templates\EmailTemplate;

class TestEmail extends EmailTemplate {

	/**
	 * @param string $to            Recipient email address.
	 * @param string $provider_name The name of the email provider being tested.
	 */
	public function __construct(
		private readonly string $to,
		private readonly string $provider_name
	) {}

	static public function template_key() : string {
		return 'test_email';
	}

	protected function subject() : string {
		return smliser__( 'Test Email — Your Email Provider is Working' );
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return "This is a test email sent via {$this->provider_name}. If you received this, your configuration is correct.";
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{provider_name}}' => $this->provider_name,
			'{{sent_at}}'       => gmdate( 'D, d M Y H:i T' ),
		] );
	}

	protected function body() : string {
		$vars          = $this->variables();
		$provider_name = \escHtml( $vars['{{provider_name}}'] );
		$sent_at       = \escHtml( $vars['{{sent_at}}'] );
		$app_name      = \escHtml( $vars['{{app_name}}'] );
		$support       = \escHtml( $vars['{{support_email}}'] );

		$i18n = [
			smliser_esc_html__( 'Email Configuration Test' ),
			smliser_esc_html__( '✓ Your email provider is configured correctly. This test email was delivered successfully.' ),
			/* translators: %s: app name (formatted) */
			sprintf( smliser_esc_html__( 'This is an automated test email sent from %s to verify that your email provider is set up and working correctly. No action is required.' ), '<strong>' . $app_name . '</strong>' ),
			smliser_esc_html__( 'Provider' ),
			smliser_esc_html__( 'Sent At' ),
			smliser_esc_html__( 'Delivered To' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'If you did not expect this email or have any concerns, contact us at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
		];

		return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            {$i18n[0]}
        </p>

        <!-- Success banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#166534;line-height:1.5;">
                        {$i18n[1]}
                    </p>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            {$i18n[2]}
        </p>

        <!-- Test details card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:24px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td width="50%" style="padding:0 16px 16px 0;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[3]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$provider_name}
                                </p>
                            </td>
                            <td width="50%" style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[4]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$sent_at}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[5]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$this->to}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            {$i18n[6]}
        </p>
        HTML;
	}

	public function label() : string {
		return smliser__( 'Test Email' );
	}
	public function description() : string {
		return smliser__( 'Sent when an administrator tests an email provider configuration.' );
	}

	public static function preview() : static {
		$providers = [smliser__( 'PHP Mail' ), 'SMTP', 'Brevo', 'SendGrid', 'Mailgun', 'Postmark' ];
		$provider  = $providers[\random_int( 0, count( $providers ) - 1 )];
		return new static( 'preview@example.com', $provider );
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => smliser__( 'Email Configuration Test' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'banner',
				'type'      => 'banner',
				'tone'      => 'success',
				'content'   => smliser__( 'Your email provider is configured correctly. This test email was delivered successfully.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'intro',
				'type'      => 'text',
				'content'   => smliser__( 'This is an automated test email sent from {{app_name}} to verify that your email provider is set up and working correctly. No action is required.' ),
				'editable'  => true,
				'removable' => true,
			],
			[
				'id'   => 'details',
				'type' => 'detail_card',
				'rows' => [
					[ 'label' => smliser__( 'Provider' ),     'value' => '{{provider_name}}' ],
					[ 'label' => smliser__( 'Sent At' ),      'value' => '{{sent_at}}' ],
					[ 'label' => smliser__( 'Delivered To' ), 'value' => '{{recipient}}' ],
				],
				'editable'  => true,
				'removable' => true,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you did not expect this email or have any concerns, contact us at {{support_email}}.' ),
				'editable'  => true,
				'removable' => true,
			],
		];
	}
}
