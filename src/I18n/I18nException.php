<?php
/**
 * I18n exception class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n;

use RuntimeException;

/**
 * Thrown when a translation file cannot be read, parsed or written.
 */
class I18nException extends RuntimeException {}
