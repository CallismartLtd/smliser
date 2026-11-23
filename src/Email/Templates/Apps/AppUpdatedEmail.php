<?php
/**
 * App updated email template.
 *
 * Sent to the app owner when a new version of their application is uploaded.
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
use SmartLicenseServer\HostedApps\WPTheme;

class AppUpdatedEmail extends EmailTemplate {

	/**
	 * @param HostedAppsInterface $app          The updated application.
	 * @param string              $to           Recipient email address.
	 * @param string              $old_version  The previous version before update.
	 */
	public function __construct(
		private readonly HostedAppsInterface $app,
		private readonly string              $to,
		private readonly string              $old_version
	) {}

	static public function template_key() : string {
		return 'app_updated';
	}

	protected function subject() : string {
		return "{$this->app->get_name()} Updated to Version {$this->app->get_version()}";
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return "{$this->app->get_name()} has been updated from version {$this->old_version} to {$this->app->get_version()}.";
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{app_name}}'    => $this->app->get_name(),
			'{{new_version}}' => $this->app->get_version(),
			'{{old_version}}' => $this->old_version,
			'{{app_type}}'    => HostedAppsRegistry::instance()->get_app_type_label( $this->app->get_type() ),
			'{{app_slug}}'    => $this->app->get_slug(),
		] );
	}

	protected function body() : string {
		$vars        = $this->variables();
		$app_name    = \escHtml( $vars['{{app_name}}'] );
		$new_version = \escHtml( $vars['{{new_version}}'] );
		$old_version = \escHtml( $vars['{{old_version}}'] );
		$app_type    = \escHtml( $vars['{{app_type}}'] );
		$app_slug    = \escHtml( $vars['{{app_slug}}'] );
		$support     = \escHtml( $vars['{{support_email}}'] );

		$i18n = [
			smliser_esc_html__( 'A new version of your application is now live.' ),
			/* translators: 1: app name (formatted), 2: old version (formatted), 3: new version (formatted) */
			sprintf( smliser_esc_html__( '✓ %1$s has been updated from version %2$s to version %3$s.' ), '<strong>' . $app_name . '</strong>', '<strong>' . $old_version . '</strong>', '<strong>' . $new_version . '</strong>' ),
			smliser_esc_html__( 'Application Name' ),
			smliser_esc_html__( 'Type' ),
			smliser_esc_html__( 'Previous Version' ),
			smliser_esc_html__( 'New Version' ),
			/* translators: %s: support (formatted) */
			sprintf( smliser_esc_html__( 'If you did not make this update or have any concerns, contact us immediately at %s.' ), '<a href="mailto:' . $support . '" style="color:#6366f1;text-decoration:none;">' . $support . '</a>' ),
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

        <!-- App details card -->
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
                            <td width="50%" style="padding:0 16px 0 0;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[4]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$old_version}
                                </p>
                            </td>
                            <td width="50%" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    {$i18n[5]}
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:700;">
                                    {$new_version}
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
		return smliser__( 'Application Updated' );
	}
	public function description() : string {
		return smliser__( 'Sent to the app owner when a new version of their application is uploaded.' );
	}
	public static function preview() : static {
		$app = WPTheme::from_array_minimal( [
			'name'    => 'My Awesome Theme',
			'version' => '2.0.0',
			'type'    => 'wptheme',
			'slug'    => 'my-awesome-theme',
		] );
		return new static( $app, 'preview@example.com', '0.2.0' );
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => smliser__( 'A new version of your application is now live.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'banner',
				'type'      => 'banner',
				'tone'      => 'success',
				'content'   => smliser__( '{{app_name}} has been updated from version {{old_version}} to version {{new_version}}.' ),
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'   => 'details',
				'type' => 'detail_card',
				'rows' => [
					[ 'label' => smliser__( 'Application Name' ),  'value' => '{{app_name}}' ],
					[ 'label' => smliser__( 'Type' ),              'value' => '{{app_type}}' ],
					[ 'label' => smliser__( 'Previous Version' ),  'value' => '{{old_version}}' ],
					[ 'label' => smliser__( 'New Version' ),       'value' => '{{new_version}}' ],
				],
				'editable'  => true,
				'removable' => true,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => smliser__( 'If you did not make this update or have any concerns, contact us immediately at {{support_email}}.' ),
				'editable'  => true,
				'removable' => false,
			],
		];
	}
}
