<?php
/**
 * The admin options page handler class
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Admin\ContentHandlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Cache\CacheAdapterRegistry;
use SmartLicenseServer\Cache\CacheProviderIcons;
use SmartLicenseServer\Contracts\URLManagerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\SiteFavicon;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\EmailProviderIcons;
use SmartLicenseServer\Email\EmailProvidersRegistry;
use SmartLicenseServer\Email\Templates\EmailTemplateRegistry;
use SmartLicenseServer\I18n\I18n;
use SmartLicenseServer\I18n\WPTranslator;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateLocator;

use function compact;

class OptionsPage implements AdminPageInterface {

	public function __construct(
		protected TemplateLocator $locator,
		protected MonetizationRegistry $monetization_registry,
		protected EmailProvidersRegistry $email_providers_registry,
		protected Cache $cache,
		protected Settings $settings,
		protected URLManager $urlmanager,
		protected EmailProviderIcons $email_icons_provider,
		protected CacheProviderIcons $cache_provider_icons,
		protected AssetsManager $assets_manager,
		protected SiteFavicon $favicon
	) {
		$this->register_scripts();
	}

	protected function register_scripts() : void {
		$this->assets_manager->set_script_category( 'cache-stats', AssetsManager::CATEGORY_ADMIN_DASHBOARD );
	}

	/*
	|---------
	| ROUTING
	|---------
	*/

	/**
	 * General settings page.
	 */
	public function general_settings( Request $request ) : void {
		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'request', 'urlmanager', 'page_handler' );
		$this->locator->render( 'admin.contents.options.index', $vars );
	}

	/**
	 * Permalink/routes settings page.
	 */
	public function routes_setting( Request $request ) : void {
		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'request', 'urlmanager', 'page_handler' );
		$this->locator->render( 'admin.contents.options.routing', $vars );
	}

	/**
	 * Monetization providers settings page.
	 */
	public function monetization_options( Request $request ) : void {
		if ( $request->has( 'provider' ) ) {
			self::monetization_provider_settings( $request );
			return;
		}
		
		$providers    = $this->monetization_registry->all( true, true );
		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact(
			'request', 'urlmanager', 'page_handler', 'providers',
		);

		$this->locator->render( 'admin.contents.options.monetization.monetization-providers', $vars );
	}

	/**
	 * Settings page for an individual monetization provider.
	 */
	private function monetization_provider_settings( Request $request ) : void {
		$provider_key = $request->get( 'provider' );
		$provider     = $this->monetization_registry->get_provider( $provider_key );
		$name         = $provider?->get_name() ?? '';
		$id           = $provider?->get_id() ?? '';
		$schema       = $provider?->get_settings_schema();
		$settings     = [];

		foreach ( $schema as $key => $data ) {
			$settings[] = [
				'label' => $data['label'] ?? '',
				'help'  => $data['description'] ?? '',
				'input' => [
					'type'  => $data['type'] ?? 'text',
					'name'  => $key,
					'value' => MonetizationRegistry::get_option( $id, $key ) ?? $data['default'] ?? '',
				]
			];
		}

		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'request', 'provider', 'name', 'settings', 'id','page_handler',
			'urlmanager'
		);
		$this->locator->render( 'admin.contents.options.monetization.monetizations', $vars );
	}

	/**
	 * Email settings page dispatcher.
	 *
	 * Routes to individual provider settings when a provider query param
	 * is present, to the template list when section=templates, to an
	 * individual template when section=templates&template=key,
	 * otherwise renders the provider list with global email settings.
	 */
	public function email_options( Request $request ) : void {
		if ( $request->has( 'provider' ) ) {
			self::email_provider_settings( $request );
			return;
		}

		if ( 'templates' === $request->get( 'section' ) ) {
			self::email_template_options( $request );
			return;
		}

		$registry         = $this->email_providers_registry;
		$providers        = $registry->all( true, true );
		$default_provider = EmailProvidersRegistry::get_default_provider_id();
		$email_fields     = static::email_settings_fields();

		$page_handler   = $this;
		$urlmanager     = $this->urlmanager;
		$icons_provider = $this->email_icons_provider;
		
		$vars = compact( 'registry', 'request', 'email_fields', 'providers', 'default_provider',
			'urlmanager', 'page_handler', 'icons_provider'
		);

		$this->locator->render( 'admin.contents.options.email.index', $vars );
	}

	/**
	 * Email template list or individual template view.
	 *
	 * Dispatches to the single template view when a template key
	 * is present, otherwise renders the full template list.
	 */
	private function email_template_options( Request $request ) : void {
		if ( $request->has( 'template' ) ) {
			self::email_template_editor( $request );
			return;
		}

		$templates    = EmailTemplateRegistry::all();
		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'request', 'templates', 'urlmanager', 'page_handler' );
		$this->locator->render( 'admin.contents.options.email.templates', $vars );
	}

	/**
	 * Individual email template view.
	 *
	 * Provides the preview render and enable/disable/reset controls
	 * for a single template type.
	 */
	private function email_template_editor( Request $request ) : void {
		$key   = $request->get( 'template' );
		$entry = EmailTemplateRegistry::entry( $key );

		// if ( ! $entry ) {
		//     wp_safe_redirect( remove_query_arg( 'template' ) );
		//     exit;
		// }

		$preview        = EmailTemplateRegistry::preview( $key );
		$preview_html   = $preview?->render();
		$current_url    = smliser_get_current_url()->remove_query_param( 'message' );
		$page_handler   = $this;
		$urlmanager     = $this->urlmanager;
		$assets_manager = $this->assets_manager;
		$vars           = compact( 'entry', 'current_url', 'preview', 'preview_html', 'page_handler',
			'urlmanager', 'assets_manager'
		);

		$this->locator->render( 'admin.contents.options.email.editor', $vars );
	}

	/**
	 * Settings page for an individual email provider.
	 */
	private function email_provider_settings( Request $request ) : void {
		$provider_key = $request->get( 'provider' );
		$registry     = $this->email_providers_registry;
		$provider     = $registry->get( $provider_key );

		$provider_name = $provider ? $provider::get_name() : '';
		$provider_id   = $provider ? $provider::get_id() : '';
		$schema        = $provider ? $provider::get_settings_schema() : [];
		$is_default    = EmailProvidersRegistry::get_default_provider_id() === $provider_id;

		// Pre-populate each field with persisted value.
		$saved_settings = [];
		foreach ( $schema as $key => $_ ) {
			$saved_settings[ $key ] = EmailProvidersRegistry::get_option( $provider_id, $key );
		}

		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$vars         = compact( 'request', 'saved_settings', 'provider', 'provider_id', 'provider_key',
			'provider_name', 'schema', 'is_default', 'urlmanager', 'page_handler'
		);
		$this->locator->render( 'admin.contents.options.email.form', $vars );
	}

	/**
	 * Cache options page dispatcher.
	 *
	 * Routes to:
	 *   - section=stats  → live cache statistics dashboard
	 *   - adapter=<id>   → individual adapter settings
	 *   - (default)      → adapter selection grid
	 */
	public function cache_options( Request $request ) : void {
		if ( 'stats' === $request->get( 'section' ) ) {
			self::cache_stats( $request );
			return;
		}

		if ( $request->has( 'adapter' ) ) {
			self::cache_adapter_settings( $request );
			return;
		}

		$cache_registry   = CacheAdapterRegistry::instance();
		$providers        = $cache_registry->all( true, true );
		$default_provider = CacheAdapterRegistry::get_default_adapter_id();

		$page_handler         = $this;
		$urlmanager           = $this->urlmanager;
		$cache_provider_icons = $this->cache_provider_icons;
		$vars                 = compact( 'page_handler', 'urlmanager', 'request', 'cache_registry', 'providers',
			'default_provider', 'cache_provider_icons'
		);

		$this->locator->render( 'admin.contents.options.cache.index', $vars );
	}

	/**
	 * Live cache statistics dashboard.
	 *
	 * Pulls stats from the active adapter via the smliser_cache() singleton
	 * so no second adapter instance is created (which would open a second
	 * connection on network-backed adapters such as Memcached or Redis).
	 */
	private function cache_stats( Request $request ) : void {
		$cache                = $this->cache;
		$stats                = $cache->get_stats();
		$adapter_id           = $cache->get_id();
		$adapter_name         = $cache->get_name();
		$is_supported         = $cache->is_supported();
		$page_handler         = $this;
		$urlmanager           = $this->urlmanager;
		$cache_provider_icons = $this->cache_provider_icons;
		$vars                 = compact( 'request', 'cache', 'stats', 'adapter_id', 'adapter_name',
			'is_supported', 'page_handler', 'urlmanager', 'cache_provider_icons'
		);
		$this->locator->render( 'admin.contents.options.cache.stats', $vars );
	}

	/**
	 * Settings page for an individual cache adapter.
	 */
	private function cache_adapter_settings( Request $request ) : void {
		$adapter_key = $request->get( 'adapter' );
		$collection  = CacheAdapterRegistry::instance();
		$adapter     = $collection->get_adapter( $adapter_key );

		$adapter_name = $adapter?->get_name() ?? '';
		$adapter_id   = $adapter?->get_id() ?? '';
		$schema       = $adapter?->get_settings_schema() ?? [];
		$is_default   = CacheAdapterRegistry::get_default_adapter_id() === $adapter_id;

		// Pre-populate each field with persisted value.
		$saved_settings = [];
		foreach ( $schema as $key => $_ ) {
			$saved_settings[ $key ] = CacheAdapterRegistry::get_option( $adapter_id, $key );
		}

		$page_handler = $this;
		$urlmanager   = $this->urlmanager;
		$settings     = $this->settings;
		$vars         = compact( 'request', 'adapter', 'adapter_name', 'adapter_key', 'schema',
			'is_default', 'adapter_id', 'saved_settings', 'page_handler', 'urlmanager', 'settings' );
		$this->locator->render( 'admin.contents.options.cache.form', $vars );
	}

	/*
	|--------
	| FIELDS
	|--------
	*/

	/**
	 * Global email settings fields.
	 *
	 * These are provider-agnostic settings that apply regardless of
	 * which provider is active — default sender identity, fallback
	 * behaviour, and provider selection.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function email_settings_fields() : array {
		return [
			[
				'label' => smliser__( 'Default Email Provider' ),
				'help'  => smliser__( 'The email provider used for all outgoing system emails. Configure individual providers using the cards below.' ),
				'input' => [
					'type'    => 'select',
					'name'    => EmailProvidersRegistry::DEFAULT_PROVIDER_KEY,
					'value'   => EmailProvidersRegistry::get_default_provider_id() ?? '',
					'class'   => 'smliser-form-label-row smliser-auto-select2',
					'options' => array_map(
						static fn( string $p ) => $p::get_name(),
						$this->email_providers_registry->all()
					),
				],
			],

			[
				'label' => smliser__( 'Default From Name' ),
				'help'  => smliser__( 'Sender name used in outgoing emails when the active provider does not have a From Name configured.' ),
				'input' => [
					'type'  => 'text',
					'name'  => EmailProvidersRegistry::DEFAULT_SENDER_NAME_KEY,
					'value' => $this->email_providers_registry->get_default_sender_name(),
					'attr'  => [
						'autocomplete' => 'off',
						'spellcheck'   => 'off',
					],
				],
			],

			[
				'label' => smliser__( 'Default From Address' ),
				'help'  => smliser__( 'Sender email address used in outgoing emails when the active provider does not have a From Email configured.' ),
				'input' => [
					'type'  => 'text',
					'name'  => EmailProvidersRegistry::DEFAULT_SENDER_EMAIL_KEY,
					'value' => $this->email_providers_registry->get_default_sender_email(),
					'attr'  => [
						'autocomplete' => 'off',
						'spellcheck'   => 'off',
					],
				],
			],
		];
	}

	/**
	 * General settings form fields.
	 *
	 * Every field is saved by SettingsController::save_general_settings()
	 * under its input name. The site favicon is not one of them: it is an
	 * upload, see favicon_field().
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function general_settings_fields() : array {
		$settings = $this->settings;

		return [
			$this->text_field(
				smliser__( 'Repository Name' ),
				smliser__( 'Public name of this license repository. This may appear in system emails, API responses, and administrative interfaces.' ),
				Settings::REPOSITORY_NAME,
				$settings->get( Settings::REPOSITORY_NAME, SMLISER_APP_NAME )
			),
			$this->text_field(
				smliser__( 'Administration Email' ),
				smliser__( 'Primary email address for receiving system notifications, error reports, and administrative alerts.' ),
				Settings::ADMIN_EMAIL,
				$settings->get( Settings::ADMIN_EMAIL, '' )
			),
			$this->text_field(
				smliser__( 'Hosting Email' ),
				smliser__( 'Designated contact email for server, infrastructure, or application hosting-related issues and notifications.' ),
				Settings::HOSTING_EMAIL,
				$settings->get( Settings::HOSTING_EMAIL, '' )
			),
			$this->text_field(
				smliser__( 'Support Email' ),
				smliser__( 'Customer-facing support email address used in license communications and support responses.' ),
				Settings::SUPPORT_EMAIL,
				$settings->get( Settings::SUPPORT_EMAIL, '' )
			),
			$this->text_field(
				smliser__( 'License Key Prefix' ),
				smliser__( 'Prefix automatically added to generated license keys (e.g., SMLISER-XXXX-XXXX). Helps identify the issuing system.' ),
				Settings::LICENSE_KEY_PREFIX,
				$settings->get( Settings::LICENSE_KEY_PREFIX, 'SMLISER' )
			),
			$this->number_field(
				smliser__( 'Default License Duration (Days)' ),
				smliser__( 'Default number of days a newly generated license remains valid when no expiration date is specified.' ),
				Settings::DEFAULT_LICENSE_DURATION,
				$settings->get( Settings::DEFAULT_LICENSE_DURATION, 365 ),
				1
			),
			$this->number_field(
				smliser__( 'Default License Activation Limit' ),
				smliser__( 'Default number of activations allowed per license key.' ),
				Settings::DEFAULT_ACTIVATION_LIMIT,
				$settings->get( Settings::DEFAULT_ACTIVATION_LIMIT, 1 ),
				1
			),
			$this->number_field(
				smliser__( 'API Rate Limit (Per Minute)' ),
				smliser__( 'Maximum number of API requests allowed per client within a one-minute window.' ),
				Settings::API_RATE_LIMIT,
				$settings->get( Settings::API_RATE_LIMIT, 60 ),
				1
			),
			$this->number_field(
				smliser__( 'Log Retention (Days)' ),
				smliser__( 'Number of days system logs are retained before automatic cleanup.' ),
				Settings::LOG_RETENTION_DAYS,
				$settings->get( Settings::LOG_RETENTION_DAYS, 30 ),
				1
			),
			$this->number_field(
				smliser__( 'Receipt Retention (Days)' ),
				smliser__( 'Days a payment receipt is kept after its purchase request is approved or rejected. The receipt file is then deleted; the request stays as a record. 0 keeps receipts forever.' ),
				Settings::RECEIPT_RETENTION_DAYS,
				$settings->get( Settings::RECEIPT_RETENTION_DAYS, 90 ),
				0
			),

			[
				'label' => smliser__( 'Environment Mode' ),
				'help'  => smliser__( 'Defines whether this repository operates in production, staging, or development mode.' ),
				'input' => [
					'type'    => 'select',
					'name'    => Settings::ENVIRONMENT_MODE,
					'value'   => $settings->get( Settings::ENVIRONMENT_MODE, 'production' ),
					'options' => [
						'production'  => smliser__( 'Production' ),
						'staging'     => smliser__( 'Staging' ),
						'development' => smliser__( 'Development' ),
					],
				],
			],

			...$this->site_language_fields(),

			$this->text_field(
				smliser__( 'Terms URL' ),
				smliser__( 'Full URL to your Terms of Service or license agreement page.' ),
				Settings::TERMS_URL,
				$settings->get( Settings::TERMS_URL, '' )
			),
			$this->text_field(
				smliser__( 'Privacy Policy URL' ),
				smliser__( 'Full URL to your privacy policy page.' ),
				Settings::PRIVACY_POLICY_URL,
				$settings->get( Settings::PRIVACY_POLICY_URL, '' )
			),
		];
	}

	/**
	 * The site favicon field, rendered by the
	 * admin.contents.options.favicon-field template and saved by
	 * SettingsController::save_general_settings() through SiteFavicon.
	 *
	 * Under WordPress the WordPress site icon applies, so there is no field.
	 *
	 * @return array{
	 *     label: string,
	 *     help: string,
	 *     upload_name: string,
	 *     remove_name: string,
	 *     url: string,
	 *     default_url: string,
	 *     is_custom: bool,
	 *     accept: string,
	 *     max_bytes: int
	 * }|null
	 */
	public function favicon_field() : ?array {
		if ( $this->favicon->is_managed_by_wordpress() ) {
			return null;
		}

		return [
			'label'       => smliser__( 'Site Favicon' ),
			'help'        => sprintf(
				/* translators: 1: largest file size in kilobytes, 2: smallest size, 3: largest size (in pixels). */
				smliser__( 'Icon shown in browser tabs and bookmarks for the admin dashboard, the client dashboard and public pages. Upload a square PNG or ICO file of %1$d KB or less, between %2$d and %3$d pixels per side.' ),
				SiteFavicon::MAX_BYTES / 1024,
				SiteFavicon::MIN_SIDE,
				SiteFavicon::MAX_SIDE
			),
			'upload_name' => SiteFavicon::UPLOAD_FIELD,
			'remove_name' => SiteFavicon::REMOVE_FIELD,
			'url'         => $this->favicon->url()->url(),
			'default_url' => $this->favicon->default_url()->url(),
			'is_custom'   => $this->favicon->is_custom(),
			'accept'      => '.png,.ico,image/png,image/x-icon,image/vnd.microsoft.icon',
			'max_bytes'   => SiteFavicon::MAX_BYTES,
		];
	}

	/**
	 * A single-line text field (browser autocomplete and spellcheck off).
	 *
	 * @param string $label Translated label.
	 * @param string $help  Translated help text.
	 * @param string $name  Input name: the setting key.
	 * @param mixed  $value Current value.
	 * @return array<string, mixed>
	 */
	private function text_field( string $label, string $help, string $name, mixed $value ) : array {
		return [
			'label' => $label,
			'help'  => $help,
			'input' => [
				'type'  => 'text',
				'name'  => $name,
				'value' => $value,
				'attr'  => [
					'autocomplete' => 'off',
					'spellcheck'   => 'off',
				],
			],
		];
	}

	/**
	 * A number field with a lower bound.
	 *
	 * @param string $label Translated label.
	 * @param string $help  Translated help text.
	 * @param string $name  Input name: the setting key.
	 * @param mixed  $value Current value.
	 * @param int    $min   Smallest value allowed.
	 * @return array<string, mixed>
	 */
	private function number_field( string $label, string $help, string $name, mixed $value, int $min ) : array {
		return [
			'label' => $label,
			'help'  => $help,
			'input' => [
				'type'  => 'number',
				'name'  => $name,
				'value' => $value,
				'attr'  => [ 'min' => $min ],
			],
		];
	}

	/**
	 * The site language field. Under WordPress the language comes from
	 * WordPress' own settings, so the field is not offered.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function site_language_fields() : array {
		if ( I18n::translator() instanceof WPTranslator ) {
			return [];
		}

		return [
			[
				'label' => smliser__( 'Site Language' ),
				'help'  => smliser__( 'Language of the admin and client dashboards, emails and public pages. Users can still choose their own language in their preferences. "Automatic" follows each visitor\'s browser language when a translation is installed.' ),
				'input' => [
					'type'    => 'select',
					'name'    => Settings::SITE_LOCALE,
					'value'   => $this->settings->get( Settings::SITE_LOCALE, '' ),
					'options' => [ '' => smliser__( 'Automatic (browser language)' ) ] + I18n::language_options(),
				],
			],
		];
	}

	/**
	 * Get routing settings form fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_routing_fields() : array {
		return [
			[
				'label' => smliser__( 'Admin URL Prefix' ),
				'help'  => smliser__( 'The URL segment that appears before the admin url. For example: https://example.com/smliser-admin/' ),
				'input' => [
					'type'  => 'text',
					'name'  => URLManagerInterface::ADMIN_URL_PREFIX_KEY,
					'value' => $this->urlmanager->admin_url_prefix(),
					'attr'  => [
						'autocomplete' => 'off',
						'spellcheck'   => 'off',
					],
				],
			],
			[
				'label' => smliser__( 'Client Dashboard URL Prefix' ),
				'help'  => smliser__( 'The URL segment that appears before the client dashboard page. For example: https://example.com/dashboard/' ),
				'input' => [
					'type'  => 'text',
					'name'  => URLManagerInterface::CLIENT_DASHBOARD_URL_PREFIX_KEY,
					'value' => $this->urlmanager->client_dashboard_url_prefix(),
					'attr'  => [
						'autocomplete' => 'off',
						'spellcheck'   => 'off',
					],
				],
			],
			[
				'label' => smliser__( 'Repository URL Prefix' ),
				'help'  => smliser__( 'The URL segment that appears before repository pages. For example: https://example.com/repository/' ),
				'input' => [
					'type'  => 'text',
					'name'  => URLManagerInterface::REPOSITORY_URL_PREFIX_KEY,
					'value' => $this->urlmanager->repository_url_prefix(),
					'attr'  => [
						'autocomplete' => 'off',
						'spellcheck'   => 'off',
					],
				],
			],

			[
				'label' => smliser__( 'Downloads URL Prefix' ),
				'help'  => smliser__( 'The URL segment that appears before download pages. For example: https://example.com/downloads/' ),
				'input' => [
					'type'  => 'text',
					'name'  => URLManagerInterface::DOWNLOADS_URL_PREFIX_KEY,
					'value' => $this->urlmanager->downloads_url_prefix(),
					'attr'  => [
						'autocomplete' => 'off',
						'spellcheck'   => 'off',
					],
				],
			],
			[
				'label' => smliser__( 'Authentication URL prefix' ),
				'help'  => smliser__( 'The URL segment the appears before authentication pages. For example https://example.com/auth' ),
				'input' => [
					'type'  => 'text',
					'name'  => URLManagerInterface::LOGIN_URL_PREFIX_KEY,
					'value' => $this->urlmanager->login_url_prefix(),
					'attr'  => [
						'autocomplete' => 'off',
						'spellcheck'   => 'off'
					]
				]
			]
		];
	}

	/*
	|------
	| MENU
	|------
	*/

	/**
	 * Get menu args.
	 *
	 * @return array<string, mixed>
	 */
	public function get_menu_args( Request $request ) : array {
		$tab     = $request->get( 'tab' ) ?? $request->route_param( 'tab' );
		$section = $request->get( 'section' );

		$title = match ( true ) {
			'routes' === $tab                            => smliser__( 'Page Routing' ),
			'monetization' === $tab                      => smliser__( 'Monetization Providers' ),
			'email' === $tab && 'templates' === $section => smliser__( 'Email Templates' ),
			'email' === $tab                             => smliser__( 'Email Providers' ),
			'cache' === $tab && 'stats' === $section     => smliser__( 'Cache Statistics' ),
			'cache' === $tab                             => smliser__( 'Cache Adapters' ),
			default                                      => smliser__( 'General Settings' ),
		};

		return [
			'breadcrumbs' => [
				[
					'label' => smliser__( 'General Settings' ),
					'url'   => $this->urlmanager->admin_options_url(),
					'icon'  => 'ti ti-home',
				],
				[
					'label' => $title,
				],
			],
			'actions' => [
				[
					'title'  => smliser__( 'Monetization Provider Settings' ),
					'label'  => smliser__( 'Monetizations' ),
					'url'    => $this->urlmanager->admin_options_url( 'monetization' ),
					'icon'   => 'ti ti-basket-dollar',
					'active' => 'monetization' === $tab,
				],
				[
					'title'  => smliser__( 'Email Provider Settings' ),
					'label'  => smliser__( 'Email Providers' ),
					'url'    => $this->urlmanager->admin_options_url( 'email' ),
					'icon'   => 'ti ti-mail',
					'active' => 'email' === $tab && 'templates' !== $section,
				],
				[
					'title'  => smliser__( 'Email Templates' ),
					'label'  => smliser__( 'Email Templates' ),
					'url'    => $this->urlmanager->admin_options_url( 'email' )->add_query_param( 'section', 'templates' ),
					'icon'   => 'ti ti-template',
					'active' => 'email' === $tab && 'templates' === $section,
				],
				[
					'title'  => smliser__( 'Cache Adapters' ),
					'label'  => smliser__( 'Cache' ),
					'url'    => $this->urlmanager->admin_options_url( 'cache' ),
					'icon'   => 'ti ti-database-search',
					'active' => 'cache' === $tab && 'stats' !== $section,
				],
				[
					'title'  => smliser__( 'Cache Statistics' ),
					'label'  => smliser__( 'Cache Stats' ),
					'url'    => $this->urlmanager->admin_options_url( 'cache' )->add_query_param( 'section', 'stats' ),
					'icon'   => 'ti ti-chart-bar',
					'active' => 'cache' === $tab && 'stats' === $section,
				],
				[
					'title'  => smliser__( 'Routes Settings' ),
					'label'  => smliser__( 'Routes' ),
					'url'    => $this->urlmanager->admin_options_url( 'routes' ),
					'icon'   => 'ti ti-globe',
					'active' => 'routes' === $tab,
				],
			],
		];
	}

	/*
	|---------------------------
	| INTERFACE IMPLEMENTATION
	|---------------------------
	*/

	public function index_page_handler() : callable {
		return [$this, 'general_settings'];
	}

	/**
	 * @inheritdoc
	 */
	public function get_submenu() : array {
		return [
			[
				'title'      => smliser__( 'General' ),
				'slug'       => 'index',
				'callback'   => [$this, 'general_settings'],
				'visibility' => true,
			],
			[
				'title'      => smliser__( 'Monetization' ),
				'slug'       => 'monetization',
				'callback'   => [$this, 'monetization_options'],
				'visibility' => true,
			],
			[
				'title'      => smliser__( 'Email' ),
				'slug'       => 'email',
				'callback'   => [$this, 'email_options'],
				'visibility' => true,
			],
			[
				'title'      => smliser__( 'Cache' ),
				'slug'       => 'cache',
				'callback'   => [$this, 'cache_options'],
				'visibility' => true,
			],
			[
				'title'      => smliser__( 'Routes' ),
				'slug'       => 'routes',
				'callback'   => [$this, 'routes_setting'],
				'visibility' => true,
			]
		];
	}
	
	public function get_menu_key() : string {
		return 'settings';
	}

	public function get_menu_data() : array {
		return [
			'title'      => smliser__( 'Settings' ),
			'slug'       => 'settings',
			'handler'    => $this,
			'icon'       => 'ti ti-settings',
			'visibility' => true
		];
	}
}
