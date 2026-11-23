<?php
/**
 * App status changed email template.
 *
 * Sent to the app owner when the status of their hosted application
 * is changed by an administrator.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Apps;

use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\HostedApps\WPPlugin;

class AppStatusChangedEmail extends EmailTemplate {

	/**
	 * @param HostedAppsInterface $app        The application whose status changed.
	 * @param string              $to         Recipient email address.
	 * @param string              $old_status The previous status.
	 * @param string              $new_status The new status.
	 * @param string|null         $reason     Optional reason for the status change.
	 */
	public function __construct(
		private readonly HostedAppsInterface $app,
		private readonly string              $to,
		private readonly string              $old_status,
		private readonly string              $new_status,
		private readonly ?string             $reason = null
	) {}

	static public function template_key() : string {
		return 'app_status_changed';
	}

	protected function subject() : string {
		return "{$this->app->get_name()} Status Changed to " . ucfirst( $this->new_status );
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return "The status of {$this->app->get_name()} has been changed from {$this->old_status} to {$this->new_status}.";
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{app_name}}'   => $this->app->get_name(),
			'{{app_type}}'   => HostedAppsRegistry::instance()->get_app_type_label( $this->app->get_type() ),
			'{{old_status}}' => ucfirst( $this->old_status ),
			'{{new_status}}' => ucfirst( $this->new_status ),
			'{{reason}}'     => $this->reason ?? 'No reason provided.',
		] );
	}

	protected function body() : string {
		$vars       = $this->variables();
		$app_name   = \escHtml( $vars['{{app_name}}'] );
		$app_type   = \escHtml( $vars['{{app_type}}'] );
		$old_status = \escHtml( $vars['{{old_status}}'] );
		$new_status = \escHtml( $vars['{{new_status}}'] );
		$reason     = \escHtml( $vars['{{reason}}'] );
		$support    = \escHtml( $vars['{{support_email}}'] );

		$is_negative = in_array(
			strtolower( $this->new_status ),
			[ 'suspended', 'inactive', 'rejected', 'disabled' ],
			true
		);

		[ $banner_bg, $banner_border, $banner_text_color, $banner_icon ] = $is_negative
			? [ '#fef2f2', '#fecaca', '#991b1b', '&#128683;' ]
			: [ '#f0fdf4', '#bbf7d0', '#166534', '&#10003;' ];

		$i18n = [
			smliser_esc_html__( 'A status update for your application.' ),
			/* translators: 1: banner icon, 2: app name (formatted), 3: old status (formatted), 4: new status (formatted) */
			sprintf( smliser_esc_html__( '%1$s The status of %2$s has been changed from %3$s to %4$s.' ), $banner_icon, '<strong>' . $app_name . '</strong>', '<strong>' . $old_status . '</strong>', '<strong>' . $new_status . '</strong>' ),
			smliser_esc_html__( 'Application' ),
			smliser_esc_html__( 'Type' ),
			smliser_esc_html__( 'Reason' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'If you have questions about this change or believe it was made in error, please contact us at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
		];

		return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            {$i18n[0]}
        </p>

        <!-- Status banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:{$banner_bg};border:1px solid {$banner_border};
                      border-radius:8px;margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;
                               color:{$banner_text_color};line-height:1.5;">
                        {$i18n[1]}
                    </p>
                </td>
            </tr>
        </table>

        <!-- Details card -->
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
                                    {$i18n[2]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$app_name}
                                </p>
                            </td>
                            <td width="50%" style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[3]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$app_type}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[4]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;line-height:1.6;">
                                    {$reason}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            {$i18n[5]}
        </p>
        HTML;
	}

	public function label() : string {
		return smliser__( 'Application Status Changed' );
	}
	public function description() : string {
		return smliser__( 'Sent to the app owner when the status of their application is changed by an administrator.' );
	}
	public static function preview() : static {
		$app = WPPlugin::from_array_minimal( [
			'name' => 'My Awesome Plugin',
			'type' => 'wpplugin',
		] );
		return new static( $app, 'preview@example.com', 'active', 'suspended', smliser__( 'Violation of terms of service.' ) );
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => smliser__( 'A status update for your application.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'banner',
				'type'      => 'banner',
				'tone'      => 'warning',
				'content'   => smliser__( 'The status of {{app_name}} has been changed from {{old_status}} to {{new_status}}.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'   => 'details',
				'type' => 'detail_card',
				'rows' => [
					[ 'label' => smliser__( 'Application' ),     'value' => '{{app_name}}' ],
					[ 'label' => smliser__( 'Type' ),            'value' => '{{app_type}}' ],
					[ 'label' => smliser__( 'Previous Status' ), 'value' => '{{old_status}}' ],
					[ 'label' => smliser__( 'New Status' ),      'value' => '{{new_status}}' ],
					[ 'label' => smliser__( 'Reason' ),          'value' => '{{reason}}' ],
				],
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you have questions about this change or believe it was made in error, please contact us at {{support_email}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
