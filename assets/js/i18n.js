/**
 * Smart License Server — JavaScript translations.
 *
 * Reads the `smliser_i18n` constant printed by the server (locale, text
 * direction, plural rule and the catalog of strings used by scripts) and
 * exposes gettext-style helpers:
 *
 *     const { __, _n, _x, sprintf } = window.smliserI18n;
 *     toast( __( 'Saved.' ) );
 *     // translators: %d: number of files.
 *     label = sprintf( _n( '%d file', '%d files', count ), count );
 *
 * Pass literal strings so `smliser i18n make-pot` can extract them.
 * Extensions pass their own text domain last, as in PHP:
 *
 *     __( 'Invoice sent.', 'acme-invoices' );
 *
 * Its strings are sent to the browser once the extension registers the
 * domain with I18n::add_text_domain().
 *
 * Also powers language pickers: any <select data-smliser-language-switcher>
 * saves the choice (to the user's preferences when signed in, otherwise a
 * cookie) and reloads the page.
 *
 * `window.smliserPreferences` saves the signed-in user's preferences to the
 * client dashboard `json/preferences` endpoint (smliser_var.preferences_url)
 * with the CSRF header: setTheme(), applyTheme(), setLanguage(), save().
 */
( function ( window, document ) {
	'use strict';

	// Printed as a top-level `const` (standalone) or `var` (WordPress).
	/* global smliser_i18n, smliser_var */
	const data = ( typeof smliser_i18n === 'object' && smliser_i18n ) || window.smliser_i18n || {};
	const messages = data.messages || {};
	const CONTEXT_SEPARATOR = '\u0004';

	/**
	 * Compile a gettext plural expression without eval(): only `n`,
	 * integers, parentheses and C operators are accepted.
	 *
	 * @param {string} header e.g. "nplurals=2; plural=(n != 1);"
	 * @returns {(n: number) => number}
	 */
	function compilePlural( header ) {
		const english = ( n ) => ( n === 1 ? 0 : 1 );
		const match = /plural\s*=\s*([^;]+)/.exec( header || '' );

		if ( ! match || /[^n0-9\s()?:<>=!&|%*/+-]/.test( match[ 1 ] ) ) {
			return english;
		}

		const tokens = match[ 1 ].match( /\d+|==|!=|<=|>=|&&|\|\||[n()?:<>!%*/+-]/g ) || [];
		let pos = 0;

		const LEVELS = [ [ '||' ], [ '&&' ], [ '==', '!=' ], [ '<', '>', '<=', '>=' ], [ '+', '-' ], [ '*', '/', '%' ] ];
		const ops = {
			'||': ( a, b ) => +( a || b ), '&&': ( a, b ) => +( a && b ),
			'==': ( a, b ) => +( a === b ), '!=': ( a, b ) => +( a !== b ),
			'<': ( a, b ) => +( a < b ), '>': ( a, b ) => +( a > b ),
			'<=': ( a, b ) => +( a <= b ), '>=': ( a, b ) => +( a >= b ),
			'+': ( a, b ) => a + b, '-': ( a, b ) => a - b, '*': ( a, b ) => a * b,
			'/': ( a, b ) => ( b === 0 ? 0 : Math.trunc( a / b ) ),
			'%': ( a, b ) => ( b === 0 ? 0 : a % b ),
		};

		function primary() {
			const t = tokens[ pos++ ];
			if ( t === 'n' ) return ( n ) => n;
			if ( t === '!' ) { const inner = primary(); return ( n ) => +! inner( n ); }
			if ( t === '(' ) { const inner = ternary(); if ( tokens[ pos++ ] !== ')' ) throw new Error( 'paren' ); return inner; }
			if ( /^\d+$/.test( t ) ) { const v = parseInt( t, 10 ); return () => v; }
			throw new Error( 'token' );
		}

		function binary( level ) {
			if ( level >= LEVELS.length ) return primary();
			let left = binary( level + 1 );
			while ( LEVELS[ level ].includes( tokens[ pos ] ) ) {
				const op = ops[ tokens[ pos++ ] ];
				const l = left, r = binary( level + 1 );
				left = ( n ) => op( l( n ), r( n ) );
			}
			return left;
		}

		function ternary() {
			const cond = binary( 0 );
			if ( tokens[ pos ] !== '?' ) return cond;
			pos++;
			const yes = ternary();
			if ( tokens[ pos++ ] !== ':' ) throw new Error( 'ternary' );
			const no = ternary();
			return ( n ) => ( cond( n ) ? yes( n ) : no( n ) );
		}

		try {
			const fn = ternary();
			return pos === tokens.length ? fn : english;
		} catch ( e ) {
			return english;
		}
	}

	const DEFAULT_DOMAIN = data.domain || 'smliser';
	const hasOwn = ( object, key ) => Object.prototype.hasOwnProperty.call( object, key );

	/**
	 * Catalogs per text domain: { messages, pluralIndex }. The core domain
	 * comes from the top level of `smliser_i18n`, extension domains from
	 * `smliser_i18n.domains` (see I18n::script_data()).
	 */
	const catalogs = Object.create( null );

	function addCatalog( domain, catalog ) {
		catalogs[ domain ] = {
			messages: ( catalog && catalog.messages ) || {},
			pluralIndex: compilePlural( catalog && catalog.plural_forms ),
		};
	}

	addCatalog( DEFAULT_DOMAIN, { messages, plural_forms: data.plural_forms } );

	Object.keys( data.domains || {} ).forEach( ( domain ) => {
		if ( domain !== DEFAULT_DOMAIN ) {
			addCatalog( domain, data.domains[ domain ] );
		}
	} );

	/**
	 * The catalog of a domain. No domain, or WordPress' `default`, means the
	 * core domain. Domains are isolated: a domain without a catalog has no
	 * translations, it does not borrow the core ones.
	 */
	function catalogFor( domain ) {
		const name = ( ! domain || domain === 'default' ) ? DEFAULT_DOMAIN : String( domain );
		return catalogs[ name ] || null;
	}

	function lookup( text, context, domain ) {
		const catalog = catalogFor( domain );
		const key = context ? context + CONTEXT_SEPARATOR + text : text;

		if ( ! catalog || ! hasOwn( catalog.messages, key ) ) {
			return null;
		}

		const forms = catalog.messages[ key ];

		// Compiled catalogs hold plural forms as an array; accept a NUL-joined string too.
		return Array.isArray( forms ) ? forms : String( forms ).split( '\u0000' );
	}

	/**
	 * Translate a string.
	 */
	function __( text, domain ) {
		const forms = lookup( text, '', domain );
		return ( forms && forms[ 0 ] ) || text;
	}

	/**
	 * Translate a string with a disambiguating context.
	 */
	function _x( text, context, domain ) {
		const forms = lookup( text, context, domain );
		return ( forms && forms[ 0 ] ) || text;
	}

	/**
	 * Translate a string with plural forms and a context.
	 */
	function translatePlural( single, plural, count, context, domain ) {
		const forms = lookup( single, context, domain );
		const n = Math.abs( parseInt( count, 10 ) || 0 );

		if ( forms ) {
			const form = forms[ catalogFor( domain ).pluralIndex( n ) ];
			if ( form ) return form;
		}

		return n === 1 ? single : plural;
	}

	function _nx( single, plural, count, context, domain ) {
		return translatePlural( single, plural, count, context, domain );
	}

	/**
	 * Translate a string with plural forms.
	 */
	function _n( single, plural, count, domain ) {
		return translatePlural( single, plural, count, '', domain );
	}

	/**
	 * Tell whether a text domain has translations in the active locale.
	 */
	function hasDomain( domain ) {
		return null !== catalogFor( domain );
	}

	/**
	 * Minimal sprintf: %s, %d, %f, %%, and numbered %1$s placeholders.
	 */
	function sprintf( format, ...args ) {
		let index = 0;

		return String( format ).replace( /%(?:(\d+)\$)?([sdf%])/g, ( match, position, type ) => {
			if ( type === '%' ) return '%';
			const value = position ? args[ parseInt( position, 10 ) - 1 ] : args[ index++ ];
			if ( type === 'd' ) return String( parseInt( value, 10 ) || 0 );
			if ( type === 'f' ) return String( parseFloat( value ) || 0 );
			return value === undefined ? '' : String( value );
		} );
	}

	/**
	 * Escape text for HTML (element content or a quoted attribute). Use it on
	 * translations inserted with innerHTML.
	 */
	function esc( text ) {
		return String( text ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ] );
	}

	/**
	 * Format a number for the active locale.
	 */
	function formatNumber( value, options ) {
		try {
			return new Intl.NumberFormat( data.lang || undefined, options ).format( value );
		} catch ( e ) {
			return String( value );
		}
	}

	/*
	|----------------------
	| Language switcher
	|----------------------
	*/

	const COOKIE = 'smliser_lang';

	function rememberInCookie( locale ) {
		const maxAge = locale ? 60 * 60 * 24 * 365 : 0;
		const secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = COOKIE + '=' + encodeURIComponent( locale ) + '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
	}

	function reloadWithoutLangParam() {
		const url = new URL( window.location.href );
		url.searchParams.delete( 'lang' );
		window.location.assign( url.toString() );
	}

	function vars() {
		return ( typeof smliser_var === 'object' && smliser_var ) || window.smliser_var || {};
	}

	/**
	 * Save one of the signed-in user's preferences (PreferenceManagement::save()).
	 *
	 * @param {string} endpoint The client dashboard `json/preferences` URL.
	 * @param {string} key      'locale', 'theme' or 'sidebar_collapsed'.
	 * @param {string} value
	 * @return {Promise<string>} The server's message.
	 */
	async function savePreference( endpoint, key, value ) {
		const body = new FormData();
		body.append( 'key', key );
		body.append( 'value', value );

		const headers = {};
		const csrf    = vars();
		if ( csrf.csrf_header && csrf.csrf_token ) {
			headers[ csrf.csrf_header ] = csrf.csrf_token;
		}

		const response = await fetch( endpoint, { method: 'POST', body, headers, credentials: 'same-origin' } );
		const json = await response.json().catch( () => ( {} ) );

		if ( ! response.ok || ! json.success ) {
			throw new Error( ( json.data && json.data.message ) || __( 'The preference could not be saved.' ) );
		}

		return ( json.data && json.data.message ) || __( 'Saved.' );
	}

	/*
	|----------------------
	| Dashboard theme
	|----------------------
	*/

	const THEMES = [ 'auto', 'light', 'dark' ];

	function themeStorageKey() {
		return vars().theme_storage_key || 'dashboard-theme';
	}

	/**
	 * Show a theme now and remember it in this browser: 'light' or 'dark' set
	 * html[data-theme]; 'auto' removes it so prefers-color-scheme applies.
	 *
	 * @param {string} theme
	 */
	function applyTheme( theme ) {
		const root = document.documentElement;

		try {
			if ( 'light' === theme || 'dark' === theme ) {
				window.localStorage.setItem( themeStorageKey(), theme );
			} else {
				window.localStorage.removeItem( themeStorageKey() );
			}
		} catch ( e ) {
			// Storage unavailable (private mode, disabled). The account still keeps the choice.
		}

		if ( ! root ) {
			return;
		}

		if ( 'light' === theme || 'dark' === theme ) {
			root.setAttribute( 'data-theme', theme );
		} else {
			root.removeAttribute( 'data-theme' );
		}
	}

	/**
	 * Choose the dashboard theme: shown at once, remembered in this browser and,
	 * for a signed-in user, saved to their account.
	 *
	 * @param {string} theme    'auto', 'light' or 'dark'.
	 * @param {string} endpoint Preferences URL; defaults to smliser_var.preferences_url.
	 * @return {Promise<string>} The server's message ('' when not signed in).
	 */
	async function setTheme( theme, endpoint ) {
		if ( ! THEMES.includes( theme ) ) {
			throw new Error( __( 'That theme is not available.' ) );
		}

		applyTheme( theme );

		const url = endpoint || vars().preferences_url;

		return url ? savePreference( url, 'theme', theme ) : '';
	}

	/*
	|----------------------
	| Language
	|----------------------
	*/

	/**
	 * Choose the language and reload the page in it. '' returns to automatic
	 * (cookie removed, account preference cleared). The page is reloaded only
	 * once the account was updated, since a saved preference beats the cookie.
	 *
	 * @param {string} locale   A locale code, or ''.
	 * @param {string} endpoint Preferences URL; defaults to smliser_var.preferences_url.
	 * @return {Promise<void>}
	 */
	async function setLanguage( locale, endpoint ) {
		const url = endpoint || vars().preferences_url;

		if ( url ) {
			await savePreference( url, 'locale', locale );
		}

		rememberInCookie( locale );
		reloadWithoutLangParam();
	}

	function initSwitchers() {
		document.querySelectorAll( 'select[data-smliser-language-switcher]' ).forEach( ( select ) => {
			select.addEventListener( 'change', async () => {
				const locale = select.value;
				const endpoint = select.getAttribute( 'data-endpoint' );

				select.disabled = true;
				rememberInCookie( locale );

				if ( endpoint ) {
					try {
						await savePreference( endpoint, 'locale', locale );
					} catch ( e ) {
						// The cookie still applies; the user is told why the account was not updated.
						if ( typeof window.console !== 'undefined' ) {
							window.console.warn( e.message );
						}
					}
				}

				reloadWithoutLangParam();
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initSwitchers );
	} else {
		initSwitchers();
	}

	window.smliserI18n = Object.freeze( {
		locale: data.locale || 'en_US',
		lang: data.lang || 'en-US',
		dir: data.dir || 'ltr',
		isRTL: data.dir === 'rtl',
		domain: DEFAULT_DOMAIN,
		hasDomain,
		__,
		_x,
		_n,
		_nx,
		sprintf,
		esc,
		formatNumber,
	} );

	/**
	 * The signed-in user's preferences, shared by the admin theme toggle and
	 * the client dashboard Settings page.
	 */
	window.smliserPreferences = Object.freeze( {
		themes: THEMES,
		save: ( key, value, endpoint ) => savePreference( endpoint || vars().preferences_url, key, value ),
		applyTheme,
		setTheme,
		setLanguage,
	} );
}( window, document ) );
