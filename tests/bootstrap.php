<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads Composer's autoloader and the function files the tested units use.
 * Unit tests run without a database or an application environment.
 */

declare( strict_types = 1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( PHP_VERSION_ID >= 80400 ) {
	require_once dirname( __DIR__ ) . '/src/Utils/functions/sanitization-functions.php';
} else {
	// The sanitizer uses PHP 8.4 syntax; equivalent escaping for older test runners.
	function escHtml( mixed $input ) : string {
		return htmlspecialchars( (string) $input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	function escAttr( mixed $value ) : string {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

require_once dirname( __DIR__ ) . '/src/Utils/functions/i18n-functions.php';
