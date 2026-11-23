/**
 * Default theme behaviour.
 *
 *  - Buttons with data-copy copy their value to the clipboard.
 *  - Buttons with data-action="back" go back in history.
 *  - A form with data-auto-submit is submitted when one of its fields
 *    changes (page-forms.js posts it), e.g. the dashboard context switcher.
 */
( function () {
	'use strict';

	document.addEventListener( 'change', ( event ) => {
		const form = event.target.closest( 'form[data-auto-submit]' );

		if ( form ) {
			form.requestSubmit();
		}
	} );

	document.addEventListener( 'toggle', ( event ) => {
		const dropdown = event.target.closest( '.dash-context-dropdown' );

		if ( ! dropdown ) {
			return;
		}

		if ( dropdown.open ) {
			positionContextPanel( dropdown );
			return;
		}

		const panel = dropdown.querySelector( '.dash-context-panel' );

		if ( panel ) {
			panel.removeAttribute( 'data-positioned' );
			panel.removeAttribute( 'style' );
		}
	}, true );

	window.addEventListener( 'resize', scheduleOpenContextPanelPosition );
	window.addEventListener( 'scroll', scheduleOpenContextPanelPosition, true );

	document.addEventListener( 'click', ( event ) => {
		document.querySelectorAll( '.dash-context-dropdown[open]' ).forEach( ( dropdown ) => {
			if ( ! dropdown.contains( event.target ) ) {
				dropdown.open = false;
			}
		} );

		const back = event.target.closest( '[data-action="back"]' );

		if ( back ) {
			history.back();
			return;
		}

		const button = event.target.closest( '[data-copy]' );

		if ( ! button || ! navigator.clipboard ) {
			return;
		}

		navigator.clipboard.writeText( button.dataset.copy ).then( () => {
			const label = button.textContent;

			button.textContent = smliserI18n.__( 'Copied' );
			setTimeout( () => {
				button.textContent = label;
			}, 2000 );
		} );
	} );

	/**
	 * Keep an open context menu inside the viewport and choose the roomier side.
	 *
	 * @param {HTMLDetailsElement} dropdown
	 */
	function positionContextPanel( dropdown ) {
		const panel = dropdown.querySelector( '.dash-context-panel' );
		const trigger = dropdown.querySelector( 'summary' );

		if ( ! panel || ! trigger ) {
			return;
		}

		const margin = 12;
		const gap = 8;
		const viewportWidth = document.documentElement.clientWidth;
		const viewportHeight = window.innerHeight;
		const anchor = trigger.getBoundingClientRect();

		panel.style.position = 'fixed';
		panel.style.top = `${margin}px`;
		panel.style.left = `${margin}px`;
		panel.style.right = 'auto';
		panel.style.maxHeight = 'none';

		const width = panel.getBoundingClientRect().width;
		const left = Math.max( margin, Math.min( anchor.right - width, viewportWidth - width - margin ) );
		const below = Math.max( 0, viewportHeight - anchor.bottom - gap - margin );
		const above = Math.max( 0, anchor.top - gap - margin );
		const desiredHeight = panel.scrollHeight;
		const placeBelow = desiredHeight <= below || below >= above;
		const available = Math.max( 0, placeBelow ? below : above );

		panel.style.left = `${left}px`;
		panel.style.maxHeight = `${available}px`;

		const height = panel.getBoundingClientRect().height;
		const top = placeBelow ? anchor.bottom + gap : anchor.top - gap - height;

		panel.style.top = `${Math.max( margin, Math.min( top, viewportHeight - height - margin ) )}px`;
		panel.setAttribute( 'data-positioned', '' );
	}

	let contextPanelPositionFrame = null;

	function scheduleOpenContextPanelPosition() {
		if ( null !== contextPanelPositionFrame ) {
			return;
		}

		contextPanelPositionFrame = window.requestAnimationFrame( () => {
			contextPanelPositionFrame = null;
			positionOpenContextPanels();
		} );
	}

	function positionOpenContextPanels() {
		document.querySelectorAll( '.dash-context-dropdown' ).forEach( ( dropdown ) => {
			if ( dropdown.open ) {
				positionContextPanel( dropdown );
			}
		} );
	}
} )();


(() => {
    'use strict';

    const KEYWORDS = {
        php: new Set((
            'abstract and array as break callable case catch class clone const ' +
            'continue declare default do else elseif empty enddeclare endfor ' +
            'endforeach endif endswitch endwhile enum eval exit extends final ' +
            'finally fn for foreach function global goto if implements include ' +
            'include_once instanceof insteadof interface isset list match namespace ' +
            'new or print private protected public readonly require require_once ' +
            'return static switch throw trait try unset use var while xor yield ' +
            'yield from'
        ).split(/\s+/)),

        javascript: new Set((
            'async await break case catch class const continue debugger default ' +
            'delete do else export extends finally for from function get if import ' +
            'in instanceof let new of return set static super switch this throw ' +
            'try typeof var void while with yield'
        ).split(/\s+/)),

        bash: new Set((
            'if then else elif fi for select while until do done case esac ' +
            'function in time coproc'
        ).split(/\s+/)),

        css: new Set((
            'important inherit initial unset revert revert-layer'
        ).split(/\s+/))
    };

    const LITERALS = {
        json: new Set(['true', 'false', 'null']),
        javascript: new Set(['true', 'false', 'null', 'undefined', 'NaN', 'Infinity']),
        php: new Set(['true', 'false', 'null', 'TRUE', 'FALSE', 'NULL']),
        bash: new Set(['true', 'false'])
    };

    const BUILTINS = {
        php: new Set((
            'echo print isset empty count strlen array_map array_filter ' +
            'array_merge array_keys array_values json_encode json_decode ' +
            'sprintf printf var_dump gettype is_array is_string is_int ' +
            'is_null str_replace trim explode implode preg_match preg_replace ' +
            'file_get_contents file_put_contents'
        ).split(/\s+/)),

        javascript: new Set((
            'Array Object String Number Boolean Math Date JSON Promise Map Set ' +
            'RegExp Error Symbol BigInt console window document navigator fetch ' +
            'parseInt parseFloat isNaN setTimeout setInterval clearTimeout ' +
            'clearInterval'
        ).split(/\s+/)),

        bash: new Set((
            'cd pwd echo printf read export source alias unalias unset local ' +
            'readonly declare typeset shift eval exec exit return test'
        ).split(/\s+/))
    };

    const LANGUAGE_ALIASES = {
        js: 'javascript',
        node: 'javascript',
        shell: 'bash',
        sh: 'bash',
        zsh: 'bash',
        yml: 'yaml',
        html: 'xml'
    };

    function normalizeLanguage(language) {
        language = (language || 'text').toLowerCase();
        return LANGUAGE_ALIASES[language] || language;
    }

    function nextNonWhitespace(source, index) {
        while (index < source.length && /\s/.test(source[index])) {
            index++;
        }

        return source[index] || '';
    }

    function readQuoted(source, start, quote) {
        let i = start + 1;

        while (i < source.length) {
            if (source[i] === '\\') {
                i += 2;
                continue;
            }

            if (source[i] === quote) {
                return i + 1;
            }

            // Bash single-quoted strings do not use backslash escapes.
            if (quote === "'" && source[i] === '\n') {
                return i;
            }

            i++;
        }

        return source.length;
    }

    function readBlockComment(source, start) {
        const end = source.indexOf('*/', start + 2);
        return end === -1 ? source.length : end + 2;
    }

    function readLineComment(source, start) {
        const end = source.indexOf('\n', start);
        return end === -1 ? source.length : end;
    }

    function tokenize(source, language) {
        const tokens = [];
        let i = 0;
        let braceDepth = 0;
        let bashExpectCommand = true;

        const add = (type, value) => {
            tokens.push({ type, value });
        };

        const isIdentifierStart = char => /[A-Za-z_$]/.test(char);
        const isIdentifierPart = char => /[A-Za-z0-9_$]/.test(char);

        while (i < source.length) {
            const char = source[i];
            const next = source[i + 1] || '';

            // Preserve whitespace exactly as written.
            if (/\s/.test(char)) {
                let end = i + 1;

                while (end < source.length && /\s/.test(source[end])) {
                    if (language === 'bash' && source[end] === '\n') {
                        bashExpectCommand = true;
                    }
                    end++;
                }

                add('', source.slice(i, end));
                i = end;
                continue;
            }

            // Comments.
            if (
                (language === 'javascript' || language === 'php' || language === 'css') &&
                char === '/' && next === '/'
            ) {
                const end = readLineComment(source, i);
                add('comment', source.slice(i, end));
                i = end;
                continue;
            }

            if (
                (language === 'javascript' || language === 'php' || language === 'css') &&
                char === '/' && next === '*'
            ) {
                const end = readBlockComment(source, i);
                add('comment', source.slice(i, end));
                i = end;
                continue;
            }

            if (
                (language === 'bash' || language === 'php') &&
                char === '#' &&
                (language === 'bash' || source.slice(i, i + 2) !== '#[')
            ) {
                const end = readLineComment(source, i);
                add('comment', source.slice(i, end));
                i = end;
                continue;
            }

            // PHP attributes: #[...]
            if (language === 'php' && source.slice(i, i + 2) === '#[') {
                add('attribute', '#[');
                i += 2;
                continue;
            }

            // Strings.
            if (char === "'" || char === '"' || (language === 'javascript' && char === '`')) {
                const end = readQuoted(source, i, char);
                add('string', source.slice(i, end));
                i = end;
                continue;
            }

            // PHP and Bash variables.
            if (
                (language === 'php' || language === 'bash') &&
                char === '$'
            ) {
                const match = source.slice(i).match(
                    /^\$(?:\{[^}]*\}|[A-Za-z_][A-Za-z0-9_]*|[0-9@#?!$*_-])/
                );

                if (match) {
                    add('variable', match[0]);
                    i += match[0].length;
                    continue;
                }
            }

            // CSS hexadecimal colors.
            if (language === 'css' && char === '#') {
                const match = source.slice(i).match(/^#[0-9a-fA-F]{3,8}\b/);

                if (match) {
                    add('number', match[0]);
                    i += match[0].length;
                    continue;
                }
            }

            // Numbers.
            const number = source.slice(i).match(
                /^(?:0[xX][\da-fA-F]+|0[bB][01]+|(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?n?)/
            );

            if (number) {
                add('number', number[0]);
                i += number[0].length;
                continue;
            }

            // Identifiers and language-specific classification.
            if (isIdentifierStart(char)) {
                let end = i + 1;

                while (end < source.length && isIdentifierPart(source[end])) {
                    end++;
                }

                const word = source.slice(i, end);
                const following = nextNonWhitespace(source, end);
                let type = '';

                if (language === 'json') {
                    if (following === ':') {
                        type = 'property';
                    } else if (LITERALS.json.has(word)) {
                        type = 'literal';
                    }
                }

                else if (language === 'php') {
                    if (KEYWORDS.php.has(word)) {
                        type = 'keyword';
                    } else if (LITERALS.php.has(word)) {
                        type = 'literal';
                    } else if (BUILTINS.php.has(word)) {
                        type = 'builtin';
                    } else if (following === '(') {
                        type = 'function';
                    } else if (/^[A-Z_][A-Z0-9_]*$/.test(word)) {
                        type = 'constant';
                    }
                }

                else if (language === 'javascript') {
                    if (KEYWORDS.javascript.has(word)) {
                        type = 'keyword';
                    } else if (LITERALS.javascript.has(word)) {
                        type = 'literal';
                    } else if (BUILTINS.javascript.has(word)) {
                        type = 'builtin';
                    } else if (following === '(') {
                        type = 'function';
                    } else if (/^[A-Z][A-Za-z0-9_]*$/.test(word)) {
                        type = 'type';
                    }
                }

                else if (language === 'css') {
                    if (word.startsWith('--')) {
                        type = 'variable';
                    } else if (KEYWORDS.css.has(word)) {
                        type = 'keyword';
                    } else if (following === ':' && braceDepth > 0) {
                        type = 'property';
                    } else if (following === '{') {
                        type = 'selector';
                    } else if (following === '(') {
                        type = 'function';
                    }
                }

                else if (language === 'bash') {
                    if (KEYWORDS.bash.has(word)) {
                        type = 'keyword';
                    } else if (bashExpectCommand) {
                        type = 'command';
                        bashExpectCommand = false;
                    } else if (BUILTINS.bash.has(word)) {
                        type = 'builtin';
                    }
                }

                add(type, word);
                i = end;
                continue;
            }

            // Bash command-line flags.
            if (
                language === 'bash' &&
                char === '-' &&
                /[A-Za-z0-9]/.test(next)
            ) {
                const match = source.slice(i).match(/^--?[A-Za-z0-9][\w-]*/);

                if (match) {
                    add('flag', match[0]);
                    i += match[0].length;
                    continue;
                }
            }

			// CSS custom properties.
			if (language === 'css' && source.slice(i, i + 2) === '--') {
				const match = source.slice(i).match(/^--[A-Za-z_][\w-]*/);

				if (match) {
					add('variable', match[0]);
					i += match[0].length;
					continue;
				}
			}

            // Operators and punctuation.
            const operator = source.slice(i).match(
                /^(?:===|!==|=>|->|\?\?=|\?\?|\?\.|&&|\|\||==|!=|<=|>=|\+\+|--|\+=|-=|\*=|\/=|%=|<<|>>|\*\*|::|[=+\-*\/%<>!&|^~?:])/
            );

            if (operator) {
                add('operator', operator[0]);

                if (language === 'bash' && /^(?:;|&&|\|\||\|)$/.test(operator[0])) {
                    bashExpectCommand = true;
                }

                i += operator[0].length;
                continue;
            }

            if ('{}[](),;.'.includes(char)) {
                add('punctuation', char);

                if (language === 'css' && char === '{') {
                    braceDepth++;
                } else if (language === 'css' && char === '}') {
                    braceDepth = Math.max(0, braceDepth - 1);
                }

                if (language === 'bash' && (char === ';' || char === '|')) {
                    bashExpectCommand = true;
                }

                i++;
                continue;
            }

            add('', char);
            i++;
        }

        return tokens;
    }

    function highlightCode(code, language) {
        if (code.dataset.highlighted === 'true') {
            return;
        }

        const source = code.textContent;
        const tokens = tokenize(source, language);
        const fragment = document.createDocumentFragment();

        for (const token of tokens) {
            if (!token.type) {
                fragment.appendChild(document.createTextNode(token.value));
                continue;
            }

            const span = document.createElement('span');
            span.className = `tok-${token.type}`;
            span.textContent = token.value;
            fragment.appendChild(span);
        }

        code.replaceChildren(fragment);
        code.dataset.highlighted = 'true';
    }

    function detectLanguage(code) {
        const match = code.className.match(
            /(?:language|lang)-([\w-]+)/i
        );

        return normalizeLanguage(match ? match[1] : 'text');
    }

    function transformCodeBlock(pre) {
        if (pre.dataset.copyable === 'true') {
            return;
        }

        const code = pre.querySelector(':scope > code');

        if (!code) {
            return;
        }

        const language = detectLanguage(code);
        const wrapper = document.createElement('div');

        wrapper.className = 'code-block';
        wrapper.dataset.language = language;

        const header = document.createElement('div');
        header.className = 'code-block__header';

        const label = document.createElement('span');
        label.className = 'code-block__language';
        label.textContent = language === 'text' ? 'Plain text' : language;

        const copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.className = 'code-block__copy';
        copyButton.textContent = 'Copy';

        copyButton.addEventListener('click', async () => {
            const originalText = code.textContent;

            try {
                await navigator.clipboard.writeText(originalText);
                copyButton.textContent = 'Copied!';
                copyButton.classList.add('is-copied');
            } catch {
                copyButton.textContent = 'Failed';
            }

            window.setTimeout(() => {
                copyButton.textContent = 'Copy';
                copyButton.classList.remove('is-copied');
            }, 1800);
        });

        header.append(label, copyButton);

        pre.classList.add('code-block__content');

        pre.parentNode.insertBefore(wrapper, pre);
        wrapper.append(header, pre);

        pre.dataset.copyable = 'true';

        if (language !== 'text') {
            highlightCode(code, language);
        }
    }

    function initializeCodeBlocks(root = document) {
        if (root.matches?.('pre')) {
            transformCodeBlock(root);
        }

        root.querySelectorAll?.('pre > code').forEach(code => {
            transformCodeBlock(code.parentElement);
        });
    }

    initializeCodeBlocks();

    const observer = new MutationObserver(records => {
        for (const record of records) {
            for (const node of record.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    initializeCodeBlocks(node);
                }
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();
