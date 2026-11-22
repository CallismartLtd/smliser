<?php
/**
 * Payment received email template.
 *
 * Sent when a payment is confirmed.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Monetization;

use SmartLicenseServer\Email\Templates\EmailTemplate;

class PaymentReceivedEmail extends EmailTemplate {

	/**
	 * @param string $to               Recipient email address.
	 * @param string $recipient_name   Name of the recipient.
	 * @param string $amount           Formatted payment amount (e.g. "$49.00").
	 * @param string $currency         Currency code (e.g. "USD").
	 * @param string $transaction_id   The payment transaction ID.
	 * @param string $payment_date     The date the payment was made.
	 * @param string $description      Description of what was paid for.
	 */
	public function __construct(
		private readonly string $to,
		private readonly string $recipient_name,
		private readonly string $amount,
		private readonly string $currency,
		private readonly string $transaction_id,
		private readonly string $payment_date,
		private readonly string $description
	) {}

	static public function template_key() : string {
		return 'payment_received';
	}

	protected function subject() : string {
		return smliser__( 'Payment Received — Thank You' );
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return "We have received your payment of {$this->amount} {$this->currency}. Your receipt is inside.";
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{recipient_name}}' => $this->recipient_name,
			'{{amount}}'         => $this->amount,
			'{{currency}}'       => $this->currency,
			'{{transaction_id}}' => $this->transaction_id,
			'{{payment_date}}'   => $this->payment_date,
			'{{description}}'    => $this->description,
		] );
	}

	protected function body() : string {
		$vars           = $this->variables();
		$recipient_name = \escHtml( $vars['{{recipient_name}}'] );
		$amount         = \escHtml( $vars['{{amount}}'] );
		$currency       = \escHtml( $vars['{{currency}}'] );
		$transaction_id = \escHtml( $vars['{{transaction_id}}'] );
		$payment_date   = \escHtml( $vars['{{payment_date}}'] );
		$description    = \escHtml( $vars['{{description}}'] );
		$support        = \escHtml( $vars['{{support_email}}'] );

		$i18n = [
			/* translators: %s: recipient name */
			sprintf( smliser_esc_html__( 'Hi %s,' ), $recipient_name ),
			smliser_esc_html__( 'Thank you for your payment. We have received it successfully and your account has been updated accordingly.' ),
			smliser_esc_html__( 'Amount Paid' ),
			smliser_esc_html__( 'Transaction ID' ),
			smliser_esc_html__( 'Payment Date' ),
			smliser_esc_html__( 'Description' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'Please keep this email as your receipt. If you have any questions about this payment, contact us at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
		];

		return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            {$i18n[0]}
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            {$i18n[1]}
        </p>

        <!-- Amount highlight -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td align="center" style="padding:24px;">
                    <p style="margin:0 0 4px;font-size:12px;font-weight:700;
                               text-transform:uppercase;letter-spacing:0.08em;color:#166534;">
                        {$i18n[2]}
                    </p>
                    <p style="margin:0;font-size:32px;font-weight:800;color:#166534;
                               letter-spacing:-0.02em;">
                        {$amount} <span style="font-size:16px;font-weight:600;">{$currency}</span>
                    </p>
                </td>
            </tr>
        </table>

        <!-- Transaction details card -->
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
                                <p style="margin:0;font-size:13px;color:#1a1a2e;
                                           font-family:monospace;font-weight:600;">
                                    {$transaction_id}
                                </p>
                            </td>
                            <td width="50%" style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[4]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$payment_date}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[5]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;line-height:1.6;">
                                    {$description}
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
		return smliser__( 'Payment Received' );
	}
	public function description() : string {
		return smliser__( 'Sent when a payment is confirmed successfully.' );
	}
	public static function preview() : static {
		return new static(
			'preview@example.com',
			smliser__( 'Jane Doe' ),
			'$49.00',
			'USD',
			'TXN-XXXXXXXXXXXX',
			gmdate( 'D, d M Y' ),
			smliser__( 'License renewal — My Awesome Plugin (Pro)' )
		);
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => smliser__( 'Hi {{recipient_name}},' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'intro',
				'type'      => 'text',
				'content'   => smliser__( 'Thank you for your payment. We have received it successfully and your account has been updated accordingly.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'banner',
				'type'      => 'banner',
				'tone'      => 'success',
				'content'   => smliser__( 'Amount Paid: {{amount}} {{currency}}' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'   => 'details',
				'type' => 'detail_card',
				'rows' => [
					[ 'label' => smliser__( 'Transaction ID' ), 'value' => '{{transaction_id}}' ],
					[ 'label' => smliser__( 'Payment Date' ),   'value' => '{{payment_date}}' ],
					[ 'label' => smliser__( 'Amount' ),         'value' => '{{amount}} {{currency}}' ],
					[ 'label' => smliser__( 'Description' ),    'value' => '{{description}}' ],
				],
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'Please keep this email as your receipt. If you have any questions about this payment, contact us at {{support_email}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
