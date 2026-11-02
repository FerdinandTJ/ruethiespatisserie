// Jalankan: node tests/po-ui.cjs (tanpa dependensi atau browser eksternal).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../po.js'), 'utf8');
function page({ clipboard, legacyCopy = false } = {}) {
  const events = {};
  const input = { value: '0', max: '12', disabled: false, dataset: { price: '25000', minQty: '3' }, setCustomValidity(message) { this.error = message; } };
  const total = {};
  const form = { querySelectorAll: () => [input], addEventListener: (name, fn) => { events[name] = fn; } };
  const link = { value: 'https://staging.ruethiespatisserie.com/pre-order/status/?t=private-test', focus() { this.focused = true; }, select() { this.selected = true; }, setSelectionRange(from, to) { this.range = [from, to]; } };
  const button = { hidden: true, addEventListener: (name, fn) => { events.copy = fn; } };
  const feedback = {};
  const elements = { 'po-form': form, 'po-total': total, 'po-status-link': link, 'po-copy-link': button, 'po-copy-feedback': feedback };
  vm.runInNewContext(source, { document: { getElementById: id => elements[id] || null, execCommand: command => { assert.equal(command, 'copy'); return legacyCopy; } }, navigator: { clipboard }, Intl });
  return { input, total, link, button, feedback, events };
}
(async () => {
  const ui = page();
  ui.input.value = '2'; ui.events.input();
  assert.match(ui.input.error, /Minimal.*3/);
  ui.input.value = '3'; ui.events.input();
  assert.equal(ui.input.error, ''); assert.match(ui.total.textContent, /75\.000/);
  ui.input.value = '0'; ui.events.input(); assert.equal(ui.input.error, '');
  ui.input.value = '2'; ui.input.disabled = true; ui.events.input(); assert.equal(ui.input.error, '');
  console.log('PASS: minimum applies only to selected, enabled products; valid quantities update total');
  let copied;
  const native = page({ clipboard: { writeText: async text => { copied = text; } } });
  assert.equal(native.button.hidden, false);
  await native.events.copy(); assert.equal(copied, native.link.value); assert.match(native.feedback.textContent, /berhasil disalin/);
  console.log('PASS: clipboard copies the complete private URL');
  const fallback = page({ clipboard: { writeText: async () => { throw new Error('denied'); } }, legacyCopy: true });
  await fallback.events.copy(); assert.equal(fallback.link.selected, true); assert.match(fallback.feedback.textContent, /berhasil disalin/);
  const manual = page(); await manual.events.copy();
  assert.equal(manual.link.focused, true); assert.equal(manual.link.selected, true);
  assert.deepEqual(manual.link.range, [0, manual.link.value.length]); assert.match(manual.feedback.textContent, /Ctrl\+C/);
  console.log('PASS: denied/missing clipboard falls back to copy or selected text with manual instructions');
  vm.runInNewContext(source, { document: { getElementById: () => null } });
  console.log('PASS: script works on pages without form or private link');
})().catch(error => { console.error(error); process.exitCode = 1; });
