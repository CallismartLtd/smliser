<?php
/**
 * Catalog loader interface file.
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
 * Reads a translation file into a Catalog.
 */
interface CatalogLoaderInterface {

	/**
	 * The file extension this loader reads, without the leading dot
	 * (for example `po`, `mo` or `l10n.php`).
	 */
	public function extension() : string;

	/**
	 * Load a translation file.
	 *
	 * @param string $path Absolute path to the file.
	 * @return Catalog
	 * @throws I18nException When the file cannot be read or parsed.
	 */
	public function load( string $path ) : Catalog;
}
