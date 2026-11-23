<?php
/**
 * PO merger class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\I18n\Tools
 * @since   0.5.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\I18n\Tools;

use SmartLicenseServer\I18n\Locale;
use SmartLicenseServer\I18n\PluralForms;
use SmartLicenseServer\I18n\Po\PoDocument;
use SmartLicenseServer\I18n\Po\PoEntry;

/**
 * Creates and updates translation (PO) files from the template (POT),
 * like `msginit` and `msgmerge`.
 */
final class PoMerger {

	/**
	 * Start a translation of a template.
	 */
	public function init( PoDocument $template, Locale $locale, string $project = 'Smart License Server' ) : PoDocument {
		$headers = [
			'Project-Id-Version'        => $project,
			'Report-Msgid-Bugs-To'      => $template->headers['Report-Msgid-Bugs-To'] ?? '',
			'POT-Creation-Date'         => $template->headers['POT-Creation-Date'] ?? gmdate( 'Y-m-d H:iO' ),
			'PO-Revision-Date'          => gmdate( 'Y-m-d H:iO' ),
			'Last-Translator'           => '',
			'Language-Team'             => $locale->native_name(),
			'Language'                  => $locale->code(),
			'MIME-Version'              => '1.0',
			'Content-Type'              => 'text/plain; charset=UTF-8',
			'Content-Transfer-Encoding' => '8bit',
			'Plural-Forms'              => PluralForms::header_for_locale( $locale ),
			'X-Domain'                  => $template->headers['X-Domain'] ?? '',
		];

		return $this->merge( $template, new PoDocument( array_filter( $headers, static fn ( string $v ) : bool => '' !== $v ) ) );
	}

	/**
	 * Update a translation with the current template: new strings are added
	 * untranslated, removed strings are dropped, existing translations and
	 * translator comments are kept, and references follow the template.
	 *
	 * @return PoDocument
	 */
	public function merge( PoDocument $template, PoDocument $translation ) : PoDocument {
		$count   = PluralForms::from_header_or_default( $translation->headers['Plural-Forms'] ?? null )->count();
		$headers = $translation->headers;

		if ( isset( $template->headers['POT-Creation-Date'] ) ) {
			$headers['POT-Creation-Date'] = $template->headers['POT-Creation-Date'];
		}

		$merged = new PoDocument( $headers );

		foreach ( $template->entries as $key => $source ) {
			$existing = $translation->entries[ $key ] ?? null;
			$forms    = $existing?->translations ?? [];

			if ( null !== $source->plural ) {
				$forms = array_pad( array_slice( $forms, 0, $count ), $count, '' );
			} else {
				$forms = [ $forms[0] ?? '' ];
			}

			$entry = new PoEntry(
				msgid: $source->msgid,
				context: $source->context,
				plural: $source->plural,
				translations: $forms,
				references: $source->references,
				extracted_comments: $source->extracted_comments,
				translator_comments: $existing?->translator_comments ?? [],
				flags: $source->flags,
			);

			if ( $existing?->is_fuzzy() ) {
				$entry->add_flag( 'fuzzy' );
			}

			$merged->entries[ $key ] = $entry;
		}

		return $merged;
	}

	/**
	 * Translation progress of a document.
	 *
	 * @return array{total: int, translated: int, fuzzy: int, percent: float}
	 */
	public static function stats( PoDocument $document ) : array {
		$total      = count( $document->entries );
		$translated = 0;
		$fuzzy      = 0;

		foreach ( $document->entries as $entry ) {
			if ( $entry->is_fuzzy() ) {
				++$fuzzy;
			} elseif ( $entry->is_translated() ) {
				++$translated;
			}
		}

		return [
			'total'      => $total,
			'translated' => $translated,
			'fuzzy'      => $fuzzy,
			'percent'    => $total > 0 ? round( $translated / $total * 100, 1 ) : 0.0,
		];
	}
}
