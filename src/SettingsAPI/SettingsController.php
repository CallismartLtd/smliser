<?php
/**
 * The settings controller class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 * @since 0.2.0
 */

declare( strict_types=1 );
namespace SmartLicenseServer\SettingsAPI;

use SmartLicenseServer\Admin\ContentHandlers\OptionsPage;
use SmartLicenseServer\Contracts\URLManagerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\SiteFavicon;
use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Email\Templates\EmailTemplateRegistry;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\I18n\I18n;
use SmartLicenseServer\I18n\Locale;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\SecurityAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Settings controller class handles request and response for settings API.
 */
class SettingsController {
	use SanitizeAwareTrait, SecurityAwareTrait;

	public function __construct(
		protected Settings $settings,
		protected OptionsPage $options_page,
		Guard $guard,
		protected SiteFavicon $favicon
	) {
		$this->guard = $guard;
	}

	/**
	 * Save general settings
	 * 
	 * @param Request $request The object.
	 * @return Response
	 */
	public function save_general_settings( Request $request ) : Response {
		try {
			$this->is_system_admin();
			$fields = $this->options_page->general_settings_fields();

			// Checked before anything is saved, so a refused icon changes nothing.
			$favicon_upload = $this->favicon_upload( $request );

			foreach ( $fields as $field ) {
				$key = $field['input']['name'] ?? '';
				if ( empty( $key ) ) {
					continue;
				}

				if ( $request->has( $key ) ) {
					$value = $this->sanitize_auto( $request->get( $key ) );

					if ( Settings::SITE_LOCALE === $key ) {
						$value = $this->validate_site_locale( (string) $value );
					}

					$this->settings->set( $key, $value );
				}
			}

			if ( null !== $favicon_upload ) {
				$this->favicon->store( $favicon_upload );
			} elseif ( $this->wants_favicon_removed( $request ) ) {
				$this->favicon->remove();
			}

			return Response::json( [
				'success' => true,
				'data'    => [
					'message'     => smliser__( 'Settings updated successfully.' ),
					'favicon_url' => $this->favicon->is_managed_by_wordpress() ? '' : $this->favicon->url()->url(),
				]
			] );
		} catch ( RequestException $e ) {
			return Response::error( $e )
				->set_header( 'Content-Type', 'application/json; charset=utf-8' );
		}

	}

	/**
	 * Save routing settings.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function save_routing_settings( Request $request ) : Response {
		try {
			$this->is_system_admin();

			$fields = $this->options_page->get_routing_fields();
			$values = [];

			foreach ( $fields as $field ) {

				$key = $field['input']['name'] ?? '';
				
				if ( empty( $key ) ) {
					continue;
				}

				$default_value = $field['input']['value'] ?? '';

				$value = $this->sanitize_slug( $request->get( $key, $default_value ) ) ?: $default_value;

				// The web server or the app answers these paths before the prefix could.
				if ( in_array( $value, URLManagerInterface::RESERVED_URL_PREFIXES, true ) ) {
					throw new RequestException(
						'invalid_param',
						sprintf( smliser__( '"%s" cannot be used as the %s: it is reserved. Reserved: %s.' ), $value, $field['label'] ?? $key, implode( ', ', URLManagerInterface::RESERVED_URL_PREFIXES ) )
					);
				}

				$values[ $key ] = $value;
			}

			// Save only when every prefix is valid.
			foreach ( $values as $key => $value ) {
				$this->settings->set( $key, $value );
			}

			return Response::json( [
				'success' => true,
				'data'    => [
					'message' => smliser__( 'Routes has been updated.' ),
				],
			] );

		} catch ( RequestException $e ) {
			return Response::error( $e )
				->set_header( 'Content-Type', 'application/json; charset=utf-8' );
		}
	}

	/**
	 * Handle email template toggling.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function toggle_email_template( Request $request ) : Response {
		try {
			$this->is_system_admin();

			$template_key = $request->get( 'template_key' );

			if ( ! $template_key ) {
				throw new RequestException(
					'required_param',
					smliser__( 'Email template key is required.' )
				);
			}

			if ( ! EmailTemplateRegistry::has( $template_key ) ) {
				throw new RequestException(
					'invalid_param',
					smliser__( 'Email template key is invalid.' )
				);
			}

			$preview = EmailTemplateRegistry::preview( $template_key );

			$new_state = $preview->is_enabled() ? false : true;
			$success   = $preview->is_enabled() ? $preview->disable() : $preview->enable();


			if ( ! $success ) {
				throw new RequestException(
					'server_error',
					smliser__( 'Failed to update email template state. Please try again.' )
				);
			}

			$label   = EmailTemplateRegistry::entry( $template_key )['label'];
			$message = $new_state
				? sprintf( smliser__( '%s email has been enabled.' ), $label )
				: sprintf( smliser__( '%s email has been disabled.' ), $label );


			return Response::json( [
				'success' => true,
				'data'    => [
					'message'      => $message,
					'template_key' => $template_key,
					'is_enabled'   => $new_state,
				],
			] );

		} catch ( RequestException $e ) {
			return Response::error( $e )
				->set_header( 'Content-Type', 'application/json; charset=utf-8' );
		}
	}
	
	/**
	 * Render a live preview from the editor's current block and style state.
	 *
	 * Called on every debounced change in the editor. Renders the full email
	 * HTML via render_from_blocks() without persisting anything — the result
	 * is written directly into the editor's preview iframe.
	 *
	 * Expected POST params:
	 *   template_key (string) — registered template key
	 *   blocks       (string) — JSON-encoded block array from editor state
	 *   styles       (string) — JSON-encoded style token map from editor state
	 *
	 * @param  Request  $request
	 * @return Response JSON — { success: true, data: { html: string } }
	 */
	public function preview_email_template( Request $request ) : Response {
		try {
			$this->is_system_admin();

			$template_key = $request->get( 'template_key' );
			$blocks_raw   = $request->get_file( 'blocks' )?->get_contents() ?? '';
			$styles_raw   = $request->get_file( 'styles' )?->get_contents() ?? '';

			if ( ! $template_key ) {
				throw new RequestException( 'required_param', smliser__( 'Template key is required.' ) );
			}

			if ( ! EmailTemplateRegistry::has( $template_key ) ) {
				throw new RequestException( 'invalid_param', smliser__( 'Invalid template key.' ) );
			}

			$blocks = json_decode( $blocks_raw, true );
			$styles = json_decode( $styles_raw, true );

			if ( ! is_array( $blocks ) || ! is_array( $styles ) ) {
				throw new RequestException( 'invalid_param', smliser__( 'Invalid blocks or styles data.' ) );
			}

			$html = EmailTemplateRegistry::preview( $template_key )
				->render_from_blocks( $blocks, $styles );

			return Response::json( [
				'success' => true,
				'data'    => [ 'html' => $html ],
			] );

		} catch ( RequestException $e ) {
			return Response::error( $e )
				->set_header( 'Content-Type', 'application/json; charset=utf-8' );
		}
	}

	/**
	 * Save the editor's current block and style state as a custom template.
	 *
	 * Renders the full HTML from the submitted block and style data via
	 * render_from_blocks(), then persists it via save_custom_template() so
	 * subsequent render() calls return the custom version without needing
	 * block data at send time.
	 *
	 * Expected POST params:
	 *   template_key (string) — registered template key
	 *   blocks       (string) — JSON-encoded block array from editor state
	 *   styles       (string) — JSON-encoded style token map from editor state
	 *
	 * @param  Request  $request
	 * @return Response JSON — { success: true, data: { message: string, template_key: string } }
	 */
	public function save_email_template( Request $request ) : Response {
		try {
			$this->is_system_admin();

			$template_key = $request->get( 'template_key' );
			$blocks_raw   = $request->get_file( 'blocks' )?->get_contents() ?? '';
			$styles_raw   = $request->get_file( 'styles' )?->get_contents() ?? '';

			if ( ! $template_key ) {
				throw new RequestException( 'required_param', smliser__( 'Template key is required.' ) );
			}

			if ( ! EmailTemplateRegistry::has( $template_key ) ) {
				throw new RequestException( 'invalid_param', smliser__( 'Invalid template key.' ) );
			}

			$blocks = json_decode( $blocks_raw, true );
			$styles = json_decode( $styles_raw, true );

			if ( ! is_array( $blocks ) || ! is_array( $styles ) ) {
				throw new RequestException( 'invalid_param', smliser__( 'Invalid blocks or styles data.' ) );
			}

			$preview = EmailTemplateRegistry::preview( $template_key );
			$html    = $preview->render_from_blocks( $blocks, $styles );

			if ( ! $preview->save_custom_template( $html ) ) {
				throw new RequestException(
					'server_error',
					smliser__( 'Failed to save template. Please try again.' )
				);
			}

			$label = EmailTemplateRegistry::entry( $template_key )['label'];

			return Response::json( [
				'success' => true,
				'data'    => [
					'message'      => "{$label} template saved successfully.",
					'template_key' => $template_key,
				],
			] );

		} catch ( RequestException $e ) {
			return Response::error( $e )
				->set_header( 'Content-Type', 'application/json; charset=utf-8' );
		}
	}

	/**
	 * Reset a template type to its system default.
	 *
	 * Deletes the stored custom template HTML so render() falls back to
	 * the system default skeleton on the next send. The editor reloads
	 * the page after a successful reset to reflect the fresh default state.
	 *
	 * Expected POST params:
	 *   template_key (string) — registered template key
	 *
	 * @param  Request  $request
	 * @return Response JSON — { success: true, data: { message: string, template_key: string } }
	 */
	public function reset_email_template( Request $request ) : Response {
		try {
			$this->is_system_admin();

			$template_key = $request->get( 'template_key' );

			if ( ! $template_key ) {
				throw new RequestException( 'required_param', smliser__( 'Template key is required.' ) );
			}

			if ( ! EmailTemplateRegistry::has( $template_key ) ) {
				throw new RequestException( 'invalid_param', smliser__( 'Invalid template key.' ) );
			}

			$preview = EmailTemplateRegistry::preview( $template_key );

			if ( ! $preview->reset_to_default() ) {
				throw new RequestException(
					'server_error',
					smliser__( 'Failed to reset template. Please try again.' )
				);
			}

			$label = EmailTemplateRegistry::entry( $template_key )['label'];

			return Response::json( [
				'success' => true,
				'data'    => [
					'message'      => "{$label} template reset to default.",
					'template_key' => $template_key,
				],
			] );

		} catch ( RequestException $e ) {
			return Response::error( $e )
				->set_header( 'Content-Type', 'application/json; charset=utf-8' );
		}
	}

	/**
	 * The site favicon posted with the general settings, validated.
	 *
	 * Null when no file was chosen, and always under WordPress, where the
	 * WordPress site icon applies.
	 *
	 * @param Request $request
	 * @return UploadedFile|null
	 * @throws RequestException When the file is not an acceptable icon.
	 */
	private function favicon_upload( Request $request ) : ?UploadedFile {
		if ( $this->favicon->is_managed_by_wordpress() ) {
			return null;
		}

		$file = $request->get_file( SiteFavicon::UPLOAD_FIELD );

		// An empty file input is still posted, as "no file".
		if ( null === $file || ! $file->exists() || UPLOAD_ERR_NO_FILE === $file->get_error_code() ) {
			return null;
		}

		$problem = $this->favicon->validate( $file );

		if ( null !== $problem ) {
			$file->reject();
			throw $problem;
		}

		return $file;
	}

	/**
	 * Whether the general settings ask to go back to the default favicon.
	 *
	 * @param Request $request
	 * @return bool
	 */
	private function wants_favicon_removed( Request $request ) : bool {
		return ! $this->favicon->is_managed_by_wordpress()
			&& '1' === (string) $request->get( SiteFavicon::REMOVE_FIELD, '' );
	}

	/**
	 * Validate the site language: empty (automatic) or an installed locale.
	 *
	 * @param string $value
	 * @return string The normalized locale code, or ''.
	 * @throws RequestException When the language is not installed.
	 */
	private function validate_site_locale( string $value ) : string {
		if ( '' === $value ) {
			return '';
		}

		$locale = Locale::try_parse( $value );

		if ( null === $locale || ! in_array( $locale->code(), I18n::translator()->available_locales(), true ) ) {
			throw new RequestException(
				'invalid_param',
				sprintf(
					/* translators: %s: a locale code such as fr_FR. */
					smliser__( 'The language "%s" is not installed.' ),
					$value
				)
			);
		}

		return $locale->code();
	}
}
