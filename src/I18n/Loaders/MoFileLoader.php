<?php
/**
 * MO catalog loader class file.
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
 * Loads binary gettext `.mo` catalogs, as produced by msgfmt, Poedit,
 * Weblate, Crowdin and translate.wordpress.org.
 */
final class MoFileLoader implements CatalogLoaderInterface {

	/**
	 * Magic number, little-endian.
	 */
	private const MAGIC_LE = 0x950412de;

	/**
	 * Magic number, big-endian.
	 */
	private const MAGIC_BE = 0xde120495;

	/**
	 * {@inheritdoc}
	 */
	public function extension() : string {
		return 'mo';
	}

	/**
	 * {@inheritdoc}
	 */
	public function load( string $path ) : Catalog {
		$data = is_readable( $path ) ? file_get_contents( $path ) : false;

		if ( false === $data ) {
			throw new I18nException( sprintf( 'Translation file "%s" is not readable.', $path ) );
		}

		return $this->parse( $data, $path );
	}

	/**
	 * Parse MO data.
	 *
	 * @param string $data
	 * @param string $source For error messages.
	 * @return Catalog
	 */
	public function parse( string $data, string $source = 'MO data' ) : Catalog {
		$length = strlen( $data );

		if ( $length < 28 ) {
			throw new I18nException( sprintf( '"%s" is not a valid MO file.', $source ) );
		}

		$magic = unpack( 'V', $data, 0 )[1];

		if ( self::MAGIC_LE === $magic ) {
			$format = 'V';
		} elseif ( self::MAGIC_BE === $magic || self::MAGIC_LE === ( unpack( 'N', $data, 0 )[1] ) ) {
			$format = 'N';
		} else {
			throw new I18nException( sprintf( '"%s" is not a valid MO file.', $source ) );
		}

		[ , $count, $originals_offset, $translations_offset ] = array_values( unpack( "{$format}4", $data, 4 ) );

		if ( $originals_offset + $count * 8 > $length || $translations_offset + $count * 8 > $length ) {
			throw new I18nException( sprintf( '"%s" is a truncated MO file.', $source ) );
		}

		$entries = [];
		$headers = [];

		for ( $i = 0; $i < $count; $i++ ) {
			[ $o_len, $o_pos ] = array_values( unpack( "{$format}2", $data, $originals_offset + $i * 8 ) );
			[ $t_len, $t_pos ] = array_values( unpack( "{$format}2", $data, $translations_offset + $i * 8 ) );

			if ( $o_pos + $o_len > $length || $t_pos + $t_len > $length ) {
				throw new I18nException( sprintf( '"%s" is a truncated MO file.', $source ) );
			}

			$original    = substr( $data, $o_pos, $o_len );
			$translation = substr( $data, $t_pos, $t_len );

			if ( '' === $original ) {
				$headers = Catalog::parse_headers( $translation );
				continue;
			}

			// Plural originals are "singular\0plural": key by the singular.
			$key             = explode( "\0", $original )[0];
			$entries[ $key ] = explode( "\0", $translation );
		}

		return new Catalog( $entries, $headers );
	}
}
