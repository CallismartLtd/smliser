( function( env ) {
    'use strict';
    
    const docE  = document.documentElement;

    /**
	 * Apply the user's theme.
	 *
	 * The server prints the account's choice on html[data-theme]
	 * (UserSettings::DASHBOARD_THEME_NAME): 'light' or 'dark' win and are
	 * mirrored to this browser; 'auto' follows the system. With no choice
	 * saved yet, a theme kept in this browser by an older version applies
	 * and is saved to the account.
	 */
	function restoreTheme() {
		var current = docE.getAttribute( 'data-theme' );

		if ( 'light' === current || 'dark' === current || 'auto' === current ) {
			try {
				if ( 'auto' === current ) {
					window.localStorage.removeItem( smliser_var.theme_storage_key );
				} else {
					window.localStorage.setItem( smliser_var.theme_storage_key, current );
				}
			} catch ( error ) {
				// Storage unavailable. Ignore.
			}

			if ( 'auto' === current ) {
				docE.removeAttribute( 'data-theme' );
			}

			return;
		}

		var stored;

		try {
			stored = window.localStorage.getItem( smliser_var.theme_storage_key );
		} catch ( error ) {
			stored = null;
		}

		if ( 'dark' === stored || 'light' === stored ) {
			docE.setAttribute( 'data-theme', stored );
			migrateStoredTheme( stored );
		} else {
			// If nothing is stored (or storage is cleared), strip the attribute 
			// so CSS @media (prefers-color-scheme) takes over.
			docE.removeAttribute( 'data-theme' );
		}        
        
	}

	/**
	 * A theme chosen before it was saved to the account lives only in this
	 * browser: save it once, so the client dashboard Settings page and every
	 * other browser agree. Later choices are saved as they are made.
	 *
	 * @param {string} theme 'light' or 'dark'.
	 */
	function migrateStoredTheme( theme ) {
		var endpoint = smliser_var.preferences_url;

		if ( ! endpoint || ! window.fetch || ! window.FormData ) {
			return;
		}

		var body    = new FormData();
		var headers = {};

		body.append( 'key', 'theme' );
		body.append( 'value', theme );

		if ( smliser_var.csrf_header && smliser_var.csrf_token ) {
			headers[ smliser_var.csrf_header ] = smliser_var.csrf_token;
		}

		window.fetch( endpoint, { method: 'POST', body: body, headers: headers, credentials: 'same-origin' } ).catch( function () {
			// Offline or signed out: the browser keeps the choice; try again next time.
		} );
	}

    /**
	 * Apply the collapsed state saved from a previous session, if any.
	 *
	 * @param {HTMLElement} wrapper The dashboard wrapper element.
	 */
	function restoreCollapsedState() {
		var stored;

		try {
			stored = window.localStorage.getItem( smliser_var.sidebar_state_key );
		} catch ( error ) {
			stored = null;
		}

		if ( '1' === stored ) {
			wrapper.classList.add( 'is-collapsed' );
		}
	}

    restoreTheme();
    env.restoreCollapsedState   = restoreCollapsedState;
    
})( window )