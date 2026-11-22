<?php
/**
 * Installer language class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.5.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Installation;

use SmartLicenseServer\I18n\Locale;
use SmartLicenseServer\I18n\LocaleResolver;

/**
 * Picks the language the installers run in, and the one they save.
 *
 * The installers run before the database (and so the site and user
 * language settings) exist, so only these sources are used, first
 * available match wins:
 *
 * 1. `?lang=xx_XX` in the URL, or `--lang` on the command line.
 * 2. The choice remembered by the installer (installer session or the
 *    `smliser_lang` cookie).
 * 3. The browser's `Accept-Language` header.
 * 4. The source locale (`en_US`).
 *
 * Only 1 and 2 count as a choice: a language detected from the browser is
 * used for the installer's pages but never saved as the site language.
 *
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.5.0
 */
final class InstallerLanguage {

	/**
	 * Matches requested locales against the installed ones.
	 *
	 * @var LocaleResolver
	 */
	private readonly LocaleResolver $resolver;

	/**
	 * Class constructor.
	 *
	 * @param array<int, string> $available Locale codes with translations installed (TranslatorInterface::available_locales()).
	 */
	public function __construct( private readonly array $available ) {
		$this->resolver = new LocaleResolver( $available );
	}

	/**
	 * The installed locale matching a requested one.
	 *
	 * Exact match first, then the requested locale's fallback chain
	 * (`pt_BR` → `pt`).
	 *
	 * @param string|null $requested A locale code such as fr_FR.
	 * @return Locale|null Null when the value is not a locale or not installed.
	 */
	public function find( ?string $requested ) : ?Locale {
		if ( null === $requested || '' === trim( $requested ) ) {
			return null;
		}

		return $this->resolver->match( trim( $requested ) );
	}

	/**
	 * The language explicitly chosen by the person installing, if any.
	 *
	 * @param string|null $query  Value of ?lang= or --lang.
	 * @param string|null $stored The remembered choice (installer session or cookie).
	 * @return Locale|null Null when nothing valid was chosen.
	 */
	public function choice( ?string $query, ?string $stored = null ) : ?Locale {
		return $this->find( $query ) ?? $this->find( $stored );
	}

	/**
	 * The language to run the installer in.
	 *
	 * @param string|null $query         Value of ?lang= or --lang.
	 * @param string|null $stored        The remembered choice (installer session or cookie).
	 * @param string|null $accept_header The browser's Accept-Language header.
	 * @return Locale
	 */
	public function resolve( ?string $query, ?string $stored = null, ?string $accept_header = null ) : Locale {
		return $this->choice( $query, $stored )
			?? $this->resolver->resolve( accept_header: $accept_header );
	}

	/**
	 * The installed locale codes, valid ones only, without duplicates.
	 *
	 * @return array<int, string>
	 */
	public function codes() : array {
		$codes = [];

		foreach ( $this->available as $code ) {
			$locale = Locale::try_parse( (string) $code );

			if ( null !== $locale ) {
				$codes[] = $locale->code();
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Whether there is more than one language to choose from.
	 *
	 * @return bool
	 */
	public function has_choice() : bool {
		return count( $this->codes() ) > 1;
	}
}
