import { beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';
import appShell from '../../resources/js/app-shell.js';

let shell;
let desktop;
let focused;
let dropdowns;
beforeEach(() => {
    desktop = false;
    focused = false;
    dropdowns = [];
    globalThis.window = { scrollY: 0, innerHeight: 800, matchMedia: () => ({ matches: desktop }) };
    globalThis.document = { documentElement: { scrollHeight: 3000 }, activeElement: { matches: () => true }, querySelectorAll: () => dropdowns, body: { classList: { remove() {} } } };
    shell = appShell();
    shell.$refs = { header: { contains: () => focused } };
    shell.init();
});
const scroll = (y) => { window.scrollY = y; shell.onScroll(); };

test('initial, downward, slight upward and top-of-page visibility', () => {
    assert.equal(shell.headerHidden, false);
    scroll(200);
    assert.equal(shell.headerHidden, true);
    scroll(192);
    assert.equal(shell.headerHidden, false);
    scroll(250);
    assert.equal(shell.headerHidden, true);
    scroll(19);
    assert.equal(shell.headerHidden, false);
});

test('accumulates slow scrolling but ignores direction jitter', () => {
    scroll(100);
    for (const y of [99, 100, 99, 100, 99]) scroll(y);
    assert.equal(shell.headerHidden, true);
    for (const y of [98, 97, 96, 95, 94, 93, 92]) scroll(y);
    assert.equal(shell.headerHidden, false);
});

test('open menu, dropdown or header focus keeps the header visible', () => {
    scroll(100);
    shell.mobileMenuOpen = true;
    scroll(200);
    assert.equal(shell.headerHidden, false);
    shell.mobileMenuOpen = false;
    focused = true;
    scroll(300);
    assert.equal(shell.headerHidden, false);
    focused = false;
    dropdowns = [{ getClientRects: () => [{}] }];
    scroll(400);
    assert.equal(shell.headerHidden, false);
    dropdowns = [{ getClientRects: () => [] }];
    scroll(500);
    assert.equal(shell.headerHidden, true);
});

test('desktop stays visible and resizing closes mobile menu', () => {
    scroll(100);
    desktop = true;
    shell.mobileMenuOpen = true;
    shell.resetHeader();
    assert.equal(shell.mobileMenuOpen, false);
    scroll(500);
    assert.equal(shell.headerHidden, false);
});

test('pointer focus left on a theme button does not pin the header', () => {
    focused = true;
    document.activeElement.matches = () => false;
    scroll(100);
    assert.equal(shell.headerHidden, true);
});

test('overscroll does not cause bounce flicker', () => {
    scroll(2200);
    scroll(2250);
    scroll(2200);
    assert.equal(shell.headerHidden, true);
    scroll(2192);
    assert.equal(shell.headerHidden, false);
    scroll(-20);
    assert.equal(shell.headerHidden, false);
});

test('navigation reset starts visible and destruction releases body lock', () => {
    scroll(100);
    shell.resetHeader();
    assert.equal(shell.headerHidden, false);
    let removed;
    document.body.classList.remove = (value) => { removed = value; };
    shell.destroy();
    assert.equal(removed, 'mobile-menu-open');
});
