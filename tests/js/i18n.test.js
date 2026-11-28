const fs = require('fs');
const assert = require('assert');
const window = { smliser_i18n: { locale: 'ru_RU', lang: 'ru-RU', dir: 'ltr',
  plural_forms: 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);',
  messages: { 'Saved.': ['Сохранено.'], '%d file': ['%d файл','%d файла','%d файлов'], 'verb\u0004Open': ['Открыть'] } },
  location: { protocol: 'https:', href: 'https://x/' } };
const document = { readyState: 'complete', querySelectorAll: () => [] };
// Run: node tests/js/i18n.test.js
new Function('window','document', fs.readFileSync(require('path').join(__dirname, '../../assets/js/i18n.js'),'utf8'))(window, document);
const { __, _n, _x, sprintf } = window.smliserI18n;
assert.strictEqual(__('Saved.'), 'Сохранено.');
assert.strictEqual(__('Other'), 'Other');
assert.strictEqual(_x('Open','verb'), 'Открыть');
assert.strictEqual(_x('Open','noun'), 'Open');
assert.strictEqual(sprintf(_n('%d file','%d files',1),1), '1 файл');
assert.strictEqual(sprintf(_n('%d file','%d files',3),3), '3 файла');
assert.strictEqual(sprintf(_n('%d file','%d files',11),11), '11 файлов');
assert.strictEqual(sprintf(_n('%d file','%d files',21),21), '21 файл');
assert.strictEqual(_n('%d dog','%d dogs',2), '%d dogs');
assert.strictEqual(sprintf('%2$s %1$s %%', 'a', 'b'), 'b a %');

// Extension text domains: own catalog and plural rule, isolated from the core one.
const window2 = { smliser_i18n: { locale: 'fr_FR', lang: 'fr-FR', dir: 'ltr', domain: 'smliser',
  plural_forms: 'nplurals=2; plural=(n > 1);',
  messages: { 'Saved.': ['Enregistré.'] },
  domains: {
    'acme': { plural_forms: 'nplurals=3; plural=(n==1 ? 0 : n==2 ? 1 : 2);',
      messages: { 'Invoice sent.': ['Facture envoyée.'], '%d invoice': ['%d facture (1)','%d factures (2)','%d factures (n)'], 'verb\u0004Send': ['Envoyer'] } },
    'joined': { plural_forms: 'nplurals=2; plural=(n > 1);', messages: { '%d seat': '%d place\u0000%d places' } },
  } },
  location: { protocol: 'https:', href: 'https://x/' } };
new Function('window','document', fs.readFileSync(require('path').join(__dirname, '../../assets/js/i18n.js'),'utf8'))(window2, document);
const t = window2.smliserI18n;
assert.strictEqual(t.domain, 'smliser');
assert.strictEqual(t.__('Saved.'), 'Enregistré.');
assert.strictEqual(t.__('Saved.', 'smliser'), 'Enregistré.');
assert.strictEqual(t.__('Saved.', 'default'), 'Enregistré.');
assert.strictEqual(t.__('Invoice sent.', 'acme'), 'Facture envoyée.');
assert.strictEqual(t.__('Invoice sent.'), 'Invoice sent.', 'core domain does not see extension strings');
assert.strictEqual(t.__('Saved.', 'acme'), 'Saved.', 'extension domain does not borrow core strings');
assert.strictEqual(t.__('Saved.', 'unknown'), 'Saved.');
assert.strictEqual(t._x('Send', 'verb', 'acme'), 'Envoyer');
assert.strictEqual(t.sprintf(t._n('%d invoice', '%d invoices', 1, 'acme'), 1), '1 facture (1)');
assert.strictEqual(t.sprintf(t._n('%d invoice', '%d invoices', 2, 'acme'), 2), '2 factures (2)', 'extension plural rule is used');
assert.strictEqual(t.sprintf(t._nx('%d invoice', '%d invoices', 5, '', 'acme'), 5), '5 factures (n)');
assert.strictEqual(t.sprintf(t._n('%d seat', '%d seats', 0, 'joined'), 0), '0 place');
assert.strictEqual(t.sprintf(t._n('%d seat', '%d seats', 4, 'joined'), 4), '4 places');
assert.strictEqual(t._n('%d dog', '%d dogs', 2, 'unknown'), '%d dogs');
assert.ok(t.hasDomain('acme') && t.hasDomain() && ! t.hasDomain('unknown'));
console.log('js ok');
