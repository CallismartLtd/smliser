<?php
/**
 * PHP catalog loader class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n\Loaders
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n\Loaders;

use SmartLicenseServer\I18n\Catalog;
use SmartLicenseServer\I18n\I18nException;

/**
 * Loads compiled `.l10n.php` catalogs.
 *
 * This is the fastest format (served from OPcache) and is the same layout
 * WordPress 6.5+ uses, so a compiled file works in both environments:
 *
 *     return [
 *         'domain'       => 'smliser',
 *         'language'     => 'fr_FR',
 *         'plural-forms' => 'nplurals=2; plural=(n > 1);',
 *         'messages'     => [
 *             'Save'                => 'Enregistrer',
 *             "button\x04Close"     => 'Fermer',
 *             '%d license'          => "%d licence\0%d licences",
 *         ],
 *     ];
 *
 * Only load files from trusted locations: they are executed.
 */
final class PhpFileLoader implements CatalogLoaderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function extension() : string {
		return 'l10n.php';
	}

	/**
	 * {@inheritdoc}
	 */
	public function load( string $path ) : Catalog {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new I18nException( sprintf( 'Translation file "%s" is not readable.', $path ) );
		}

		$data = ( static fn ( string $__file ) : mixed => include $__file )( $path );

		if ( ! is_array( $data ) ) {
			throw new I18nException( sprintf( 'Translation file "%s" must return an array.', $path ) );
		}

		$entries = [];

		foreach ( (array) ( $data['messages'] ?? [] ) as $key => $translation ) {
			if ( is_array( $translation ) ) {
				$entries[ (string) $key ] = array_map( 'strval', array_values( $translation ) );
			} else {
				$entries[ (string) $key ] = explode( "\0", (string) $translation );
			}
		}

		$headers = [];

		foreach ( [ 'plural-forms' => 'Plural-Forms', 'language' => 'Language', 'domain' => 'X-Domain', 'project-id-version' => 'Project-Id-Version' ] as $key => $header ) {
			if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$headers[ $header ] = $data[ $key ];
			}
		}

		return new Catalog( $entries, $headers );
	}
}
