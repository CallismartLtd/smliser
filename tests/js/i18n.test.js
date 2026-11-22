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
console.log('js ok');
