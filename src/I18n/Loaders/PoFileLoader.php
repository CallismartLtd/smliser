<?php
/**
 * PO catalog loader class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n\Loaders
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n\Loaders;

use SmartLicenseServer\I18n\Catalog;
use SmartLicenseServer\I18n\Po\PoDocument;

/**
 * Loads `.po` catalogs directly.
 *
 * Convenient while translating (no compile step), but slower than `.mo`
 * or `.l10n.php`; run `smliser i18n compile` for production.
 */
final class PoFileLoader implements CatalogLoaderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function extension() : string {
		return 'po';
	}

	/**
	 * {@inheritdoc}
	 */
	public function load( string $path ) : Catalog {
		return PoDocument::from_file( $path )->to_catalog();
	}
}
