/**
 * Client dashboard Settings page (SettingsPage, pages/client/settings.php).
 *
 *  - [data-settings-tabs]      Tabs: [data-settings-tab] buttons show the matching
 *                              [data-settings-panel]; the tab is remembered for
 *                              this browser tab.
 *  - form[data-settings-language]  Saves the language (key=locale; '' is automatic)
 *                              and reloads the page in it.
 *  - form[data-settings-theme] Saves the theme (key=theme) as soon as a choice is
 *                              made and shows it at once, the same way the admin
 *                              dashboard's theme toggle does.
 *
 * Saving goes through window.smliserPreferences (assets/js/i18n.js), which posts
 * to the page's json/preferences URL with the CSRF header.
 */
( function () {
	'use strict';

	const STORAGE_KEY = 'smliser:settings-tab';
	const { __ } = window.smliserI18n;

	const readStored = () => {
		try {
			return window.sessionStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			return null;
		}
	};

	const writeStored = ( name ) => {
		try {
			window.sessionStorage.setItem( STORAGE_KEY, name );
		} catch ( e ) {
			// Storage unavailable: tabs still work, just unremembered.
		}
	};

	/**
	 * Show a message in a form's [data-settings-message].
	 *
	 * @param {HTMLFormElement} form
	 * @param {string}          text  '' hides the message.
	 * @param {?string}         state 'error' or 'success'.
	 */
	const say = ( form, text, state ) => {
		const box = form.querySelector( '[data-settings-message]' );

		if ( ! box ) {
			return;
		}

		box.textContent = text;
		box.hidden      = '' === text;

		if ( state ) {
			box.setAttribute( 'data-state', state );
			box.setAttribute( 'role', 'error' === state ? 'alert' : 'status' );
		} else {
			box.removeAttribute( 'data-state' );
		}
	};

	const initTabs = ( tabs ) => {
		const buttons = Array.from( tabs.querySelectorAll( '[data-settings-tab]' ) );
		const panels  = Array.from( tabs.querySelectorAll( '[data-settings-panel]' ) );

		if ( ! buttons.length || ! panels.length ) {
			return;
		}

		const names = buttons.map( ( button ) => button.dataset.settingsTab );

		const activate = ( name, focus = false ) => {
			buttons.forEach( ( button ) => {
				const selected = button.dataset.settingsTab === name;
				button.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
				button.tabIndex = selected ? 0 : -1;

				if ( selected && focus ) {
					button.focus();
				}
			} );
			panels.forEach( ( panel ) => panel.hidden = panel.dataset.settingsPanel !== name );
			writeStored( name );
		};

		buttons.forEach( ( button, index ) => {
			button.addEventListener( 'click', () => activate( button.dataset.settingsTab ) );
			button.addEventListener( 'keydown', ( event ) => {
				let next = index;

				if ( 'ArrowRight' === event.key ) next = ( index + 1 ) % buttons.length;
				else if ( 'ArrowLeft' === event.key ) next = ( index - 1 + buttons.length ) % buttons.length;
				else if ( 'Home' === event.key ) next = 0;
				else if ( 'End' === event.key ) next = buttons.length - 1;
				else return;

				event.preventDefault();
				activate( buttons[ next ].dataset.settingsTab, true );
			} );
		} );

		// Restore the last tab chosen in this browser tab, unless the URL asked for one.
		const asked  = new URL( window.location.href ).searchParams.get( 'settings_tab' );
		const stored = readStored();

		if ( ! asked && stored && names.includes( stored ) && stored !== tabs.dataset.activeTab ) {
			activate( stored );
		}
	};

	const initLanguage = ( form, endpoint ) => {
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();

			const select = form.querySelector( 'select[name="value"]' );
			const button = form.querySelector( '[type="submit"]' );

			if ( ! select ) {
				return;
			}

			say( form, '', null );
			select.disabled = true;

			if ( button ) {
				button.disabled = true;
			}

			try {
				// Reloads the page in the new language once it is saved.
				await window.smliserPreferences.setLanguage( select.value, endpoint );
			} catch ( error ) {
				select.disabled = false;

				if ( button ) {
					button.disabled = false;
				}

				say( form, error?.message || __( 'The preference could not be saved.' ), 'error' );
			}
		} );
	};

	const initTheme = ( form, endpoint ) => {
		form.addEventListener( 'change', async ( event ) => {
			const input = event.target.closest( 'input[name="value"]' );

			if ( ! input || ! input.checked ) {
				return;
			}

			say( form, '', null );

			try {
				await window.smliserPreferences.setTheme( input.value, endpoint );
				say( form, __( 'Theme saved.' ), 'success' );
			} catch ( error ) {
				say( form, error?.message || __( 'The preference could not be saved.' ), 'error' );
			}
		} );

		// The page may come from the back/forward cache: show what is checked.
		const checked = form.querySelector( 'input[name="value"]:checked' );

		if ( checked ) {
			window.smliserPreferences.applyTheme( checked.value );
		}
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-settings]' ).forEach( ( page ) => {
			const endpoint = page.dataset.preferencesUrl || '';

			page.querySelectorAll( '[data-settings-tabs]' ).forEach( initTabs );
			page.querySelectorAll( 'form[data-settings-language]' ).forEach( ( form ) => initLanguage( form, endpoint ) );
			page.querySelectorAll( 'form[data-settings-theme]' ).forEach( ( form ) => initTheme( form, endpoint ) );
		} );
	} );
} )();
