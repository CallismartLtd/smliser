<?php
/**
 * Web environment bootstrapper class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use Callismart\DBPrism\Database;
use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\ClientDashboard\ClientDashboardRegistry;
use SmartLicenseServer\Assets\CSS;
use SmartLicenseServer\Assets\JS;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Environments\Application\Boot\BootstrapperInterface;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\Auth\WebIdentityProvider;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Environments\Application\Theming\ThemeManager;
use SmartLicenseServer\Environments\Application\Theming\ThemeRenderer;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Environments\Application\Web\HttpExecutionHandler;
use SmartLicenseServer\Environments\Application\Web\RestAPIProvider;
use SmartLicenseServer\Environments\Application\Web\HttpDispatcher;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\I18n\LocaleResolver;
use SmartLicenseServer\I18n\TranslatorInterface;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\RESTAPI\Versions\V1;
use SmartLicenseServer\Routing\Router;
use SmartLicenseServer\RuntimeConfig;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Security\Authentication\Session\UserSessionCutoff;
use SmartLicenseServer\Security\Authentication\AuthenticationMonitor;
use SmartLicenseServer\Security\Licensing\LicenseMonitor;
use SmartLicenseServer\Security\Authentication\AuthenticationFactorManager;
use SmartLicenseServer\Security\Authentication\PasskeyStore;
use SmartLicenseServer\Security\Authentication\WebAuthnManager;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\CSRF\CSRF;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\SettingsAPI\UserSettings;
use SmartLicenseServer\Templates\TemplateDiscovery;

/**
 * Class WebBootstrapper
 *
 * Handles container bindings and boot initialization for HTTP/Web request execution.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
class WebBootstrapper implements BootstrapperInterface {

	/**
	 * {@inheritdoc}
	 */
	public function isEligible() : bool {
		return ! \is_cli();
	}

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ) : void {

		$container->singleton(
			HttpDispatcher::class,
			fn ( Container $c ) : HttpDispatcher => new HttpDispatcher(
				$c->get( Router::class ),
				$c
			)
		);

		$container->singleton(
			ExecutionHandlerInterface::class,
			fn ( Container $c ) : HttpExecutionHandler => new HttpExecutionHandler(
				$c->get( HttpDispatcher::class ),
				$c->get( Request::class )
			)
		);

		$container->singleton(
			IdentityService::class,
			fn ( Container $c ) : IdentityService => new IdentityService(
				$c->get( Guard::class ),
				$c->get( WebIdentityProvider::class )
			)
		);

		$container->singleton(
			CSRF::class,
			function( Container $c ) : CSRF {
				$is_secure         = $c->get( Request::class )->isSecure();
				$guest_cookie_name = sprintf( '%ssmliser-csrf', $is_secure ? '__Host-' : '' );
				return new CSRF(
					secret: $c->get( RuntimeConfig::class )->secret,
					salt: $c->get( RuntimeConfig::class )->salt,
					sessions: $c->get( SessionManager::class ),
					secure: $is_secure,
					guest_cookie: $guest_cookie_name
				);
			}
		);

		$container->singleton(
			RestAPIProvider::class,
			fn ( Container $c ) : RestAPIProvider => RestAPIProvider::init(
				identity: $c->get( IdentityService::class ),
				guard: $c->get( Guard::class ),
				csrf: $c->get( CSRF::class ),
				urlmanager: $c->get( URLManager::class ),
				versions: $c->get( V1::class )
			)
		);
		
		$container->singleton(
			AssetsManager::class,
			fn ( Container $c ) : AssetsManager => new AssetsManager(
				$c->get( Guard::class ),
				$c->get( URLManager::class ),
				$c->get( CSS::class ),
				$c->get( JS::class ),
				$c->get( CSRF::class )
			)
		);

		$container->singleton(
			ThemeRenderer::class,
			fn ( Container $c ) : ThemeRenderer => new ThemeRenderer(
				$c->get( ThemeManager::class ),
				$c->get( URLManager::class ),
				$c->get( Guard::class ),
				$c->get( AssetsManager::class ),
				$c->get( FileSystem::class )
			)
		);

		/*
		 * One registry per dashboard for the whole request, so menus added
		 * by extensions and themes are the menus the dashboard is built from.
		 */
		$container->singleton(
			AdminDashboardRegistry::class,
			fn ( Container $c ) : AdminDashboardRegistry => new AdminDashboardRegistry(
				$c->get( URLManager::class ),
				$c
			)
		);

		$container->singleton(
			ClientDashboardRegistry::class,
			fn ( Container $c ) : ClientDashboardRegistry => new ClientDashboardRegistry(
				$c->get( URLManager::class ),
				$c,
				$c->get( Guard::class )
			)
		);

		$container->singleton( UserSessionCutoff::class,
			fn() : UserSessionCutoff => new UserSessionCutoff()
		);

		$container->singleton(
			AuthenticationMonitor::class,
			fn ( Container $c ) : AuthenticationMonitor => new AuthenticationMonitor(
				$c->get( Cache::class ),
				$c->get( RuntimeConfig::class )->secret
			)
		);

		$container->singleton(
			LicenseMonitor::class,
			fn ( Container $c ) : LicenseMonitor => new LicenseMonitor(
				$c->get( Cache::class ),
				$c->get( RuntimeConfig::class )->secret
			)
		);

		$container->singleton(
			PasskeyStore::class,
			fn ( Container $c ) : PasskeyStore => new PasskeyStore( $c->get( Database::class ) )
		);

		$container->singleton(
			WebAuthnManager::class,
			fn ( Container $c ) : WebAuthnManager => new WebAuthnManager(
				$c->get( PasskeyStore::class ),
				$c->get( AuthenticationFactorManager::class ),
				$c->get( URLManager::class ),
				$c->get( RuntimeConfig::class )->secret
			)
		);

		$container->singleton(
			SessionManager::class,
			function( Container $c ) : SessionManager {
				$is_secure   = $c->get( Request::class )->isSecure();
				$cookie_name = sprintf( '%ssmliser-sid', $is_secure ? '__Host-' : '' );
				return new SessionManager(
					secret: $c->get( RuntimeConfig::class )->secret,
					salt: $c->get( RuntimeConfig::class )->salt,
					revocation_check: $c->get( UserSessionCutoff::class ),
					secure: $is_secure,
					cookie_name: $cookie_name
				);
			}
		);
		
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ) : void {
		// Force evaluation of FileSystem API initialization.
		$container->get( FileSystem::class );

		$guard       = $container->get( Guard::class );
		$user_locale = null;

		if ( $guard->has_principal() ) {
			$user_settings = UserSettings::for( $guard->principal()->get_actor() );
			$container->singleton( UserSettings::class, $user_settings );

			try {
				$user_locale = $user_settings->get( UserSettings::LOCALE );
			} catch ( \Throwable ) {
				$user_locale = null;
			}
		}

		$this->apply_locale( $container, is_string( $user_locale ) ? $user_locale : null );

		// Views put their assets on the request's AssetsManager.
		View::use_assets( fn () : AssetsManager => $container->get( AssetsManager::class ) );

		$container->get( HttpDispatcher::class )
			->registerCoreRoutes()
			->registerProvider( $container->get( RESTProviderInterface::class ) );
		
		$container->get( TemplateDiscovery::class )
			->discover( 'core', \SMLISER_RUNTIME_DIR . '/templates/', 0 );
	}

	/**
	 * Switch the translator to the request's language.
	 *
	 * @see LocaleResolver for the precedence rules.
	 *
	 * @param Container   $container
	 * @param string|null $user_locale The signed-in user's preference.
	 */
	private function apply_locale( Container $container, ?string $user_locale ) : void {
		try {
			$translator  = $container->get( TranslatorInterface::class );
			$site_locale = $container->get( Settings::class )->get( Settings::SITE_LOCALE, '' );
			$resolver    = new LocaleResolver( $translator->available_locales() );

			$translator->set_locale(
				$resolver->resolve_request(
					$container->get( Request::class ),
					$user_locale,
					is_string( $site_locale ) ? $site_locale : null
				)
			);
		} catch ( \Throwable $e ) {
			// A broken catalog or setting must never take the site down: stay in English.
			smliser_log_error( $e );
		}
	}
}
