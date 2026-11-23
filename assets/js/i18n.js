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
 *
 * Also powers language pickers: any <select data-smliser-language-switcher>
 * saves the choice (to the user's preferences when signed in, otherwise a
 * cookie) and reloads the page.
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

	const pluralIndex = compilePlural( data.plural_forms );

	function lookup( text, context ) {
		const key = context ? context + CONTEXT_SEPARATOR + text : text;
		return Object.prototype.hasOwnProperty.call( messages, key ) ? messages[ key ] : null;
	}

	/**
	 * Translate a string.
	 */
	function __( text, domain ) {
		const forms = lookup( text );
		return ( forms && forms[ 0 ] ) || text;
	}

	/**
	 * Translate a string with a disambiguating context.
	 */
	function _x( text, context, domain ) {
		const forms = lookup( text, context );
		return ( forms && forms[ 0 ] ) || text;
	}

	/**
	 * Translate a string with plural forms and a context.
	 */
	function translatePlural( single, plural, count, context ) {
		const forms = lookup( single, context );
		const n = Math.abs( parseInt( count, 10 ) || 0 );

		if ( forms ) {
			const form = forms[ pluralIndex( n ) ];
			if ( form ) return form;
		}

		return n === 1 ? single : plural;
	}

	function _nx( single, plural, count, context, domain ) {
		return translatePlural( single, plural, count, context );
	}

	/**
	 * Translate a string with plural forms.
	 */
	function _n( single, plural, count, domain ) {
		return translatePlural( single, plural, count, '' );
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

	async function savePreference( endpoint, locale ) {
		const vars = ( typeof smliser_var === 'object' && smliser_var ) || window.smliser_var || {};
		const body = new FormData();
		body.append( 'key', 'locale' );
		body.append( 'value', locale );

		const headers = {};
		if ( vars.csrf_header && vars.csrf_token ) {
			headers[ vars.csrf_header ] = vars.csrf_token;
		}

		const response = await fetch( endpoint, { method: 'POST', body, headers, credentials: 'same-origin' } );
		const json = await response.json().catch( () => ( {} ) );

		if ( ! response.ok || ! json.success ) {
			throw new Error( ( json.data && json.data.message ) || __( 'The preference could not be saved.' ) );
		}
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
						await savePreference( endpoint, locale );
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
		__,
		_x,
		_n,
		_nx,
		sprintf,
		esc,
		formatNumber,
	} );
}( window, document ) );
