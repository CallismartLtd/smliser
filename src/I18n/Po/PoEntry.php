<?php
/**
 * PO entry class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n\Po
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n\Po;

use SmartLicenseServer\I18n\Catalog;

/**
 * One message in a PO/POT file.
 */
final class PoEntry {

	/**
	 * Constructor.
	 *
	 * @param string             $msgid
	 * @param string|null        $context            msgctxt.
	 * @param string|null        $plural             msgid_plural.
	 * @param array<int, string> $translations       msgstr / msgstr[n].
	 * @param array<int, string> $references         `#:` source references (file:line).
	 * @param array<int, string> $extracted_comments `#.` comments for translators.
	 * @param array<int, string> $translator_comments `# ` comments.
	 * @param array<int, string> $flags              `#,` flags (fuzzy, php-format, ...).
	 */
	public function __construct(
		public string $msgid,
		public ?string $context = null,
		public ?string $plural = null,
		public array $translations = [],
		public array $references = [],
		public array $extracted_comments = [],
		public array $translator_comments = [],
		public array $flags = [],
	) {}

	/**
	 * The catalog key of the entry.
	 */
	public function key() : string {
		return Catalog::key( $this->msgid, $this->context );
	}

	/**
	 * Tell whether the entry is marked fuzzy (needs review; not used at runtime).
	 */
	public function is_fuzzy() : bool {
		return in_array( 'fuzzy', $this->flags, true );
	}

	/**
	 * Tell whether every form has a translation.
	 */
	public function is_translated() : bool {
		if ( [] === $this->translations ) {
			return false;
		}

		foreach ( $this->translations as $t ) {
			if ( '' === $t ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Add a flag once.
	 */
	public function add_flag( string $flag ) : void {
		if ( ! in_array( $flag, $this->flags, true ) ) {
			$this->flags[] = $flag;
		}
	}
}
