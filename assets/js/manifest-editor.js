/**
 * App manifest editor of themed pages.
 *
 * Mounts the admin dashboard's JSON editor (SmliserJsonEditor) on a
 * manifest field, through a markup contract any theme can follow:
 *
 *   textarea[data-smliser-json-editor]   The manifest as JSON. It stays in the page,
 *                                        read-only, for browsers without JavaScript.
 *     data-file-input="<id>"             The form's file input the edited manifest is
 *                                        written to, as app.json, so the form posts it
 *                                        like an uploaded file. Required.
 *     data-title="…"                     The editor's title.
 *     data-description="…"               The editor's description.
 *
 * The file input is written only once the manifest is edited, so a form
 * saved without edits keeps the app's manifest as it is. A file chosen in
 * the input is loaded into the editor, so both always agree.
 *
 * Needs i18n.js and json-editor.js.
 */
( function () {
	'use strict';

	const SELECTOR = 'textarea[data-smliser-json-editor]';

	document.addEventListener( 'DOMContentLoaded', () => {
		if ( 'undefined' === typeof SmliserJsonEditor ) {
			return;
		}

		document.querySelectorAll( SELECTOR ).forEach( mount );
	} );

	/**
	 * Mount the editor on a manifest field.
	 *
	 * @param {HTMLTextAreaElement} textarea
	 */
	function mount( textarea ) {
		const input = document.getElementById( textarea.dataset.fileInput || '' );

		if ( ! input || 'file' !== input.type ) {
			return;
		}

		let data = {};

		try {
			data = JSON.parse( textarea.value || '{}' );
		} catch {
			data = {};
		}

		const frame = document.createElement( 'div' );
		frame.className = 'smliser-manifest-editor';
		textarea.hidden = true;
		textarea.after( frame );

		const dark   = window.matchMedia?.( '(prefers-color-scheme: dark)' );
		let writing  = false;
		const editor = new SmliserJsonEditor( frame, {
			title: textarea.dataset.title || smliserI18n.__( 'APP JSON Editor' ),
			description: textarea.dataset.description || '',
			data,
			autoFocus: false,
			verbose: false,
			theme: dark?.matches ? 'dark' : 'light',
			onChange: () => write( editor, input, () => writing = true, () => writing = false ),
		} );

		dark?.addEventListener?.( 'change', ( event ) => {
			if ( ( 'dark' === editor.options.theme ) !== event.matches ) {
				editor.toggleTheme();
			}
		} );

		// A file chosen in the input (not one written here) is loaded into the editor.
		input.addEventListener( 'change', async () => {
			const file = input.files?.[ 0 ];

			if ( writing || ! file ) {
				return;
			}

			try {
				await editor.setData( JSON.parse( await file.text() ) );
			} catch {
				editor.notification?.error?.( smliserI18n.__( 'Invalid JSON string' ) );
			}
		} );

		editor.expandAll();
	}

	/**
	 * Write the edited manifest to the file input, as app.json.
	 *
	 * @param {SmliserJsonEditor} editor
	 * @param {HTMLInputElement}  input
	 * @param {Function}          start Marks the write as this script's.
	 * @param {Function}          end
	 */
	async function write( editor, input, start, end ) {
		const json     = await editor.getJSON( true );
		const transfer = new DataTransfer();

		transfer.items.add( new File( [ json ], 'app.json', { type: 'application/json', lastModified: Date.now() } ) );

		start();
		input.files = transfer.files;
		// The uploader (smliser-uploader.js) lists the file on "change".
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		end();
	}
} )();
