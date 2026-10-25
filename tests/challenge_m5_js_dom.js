/**
 * =============================================================================
 * EMPIRICAL ADVERSARIAL JS DOM HARNESS: Milestone 5 (main.js Resilience)
 * Agent: challenger_m5_2
 * 
 * Adversarial vectors:
 * 1. Regex special characters: [, ], *, +, \, (, ), ?, ^, $, {, }, |, .
 * 2. HTML injection & XSS payloads: <script>, <img src=x onerror=...>, "><svg>
 * 3. Quotes & control chars: ', ", `, \', \", \n, \r, \t, null byte
 * 4. Empty inputs & whitespace: "", "   ", tabs, newlines
 * 5. Unicode, Vietnamese diacritics & Emojis: Tiếng Việt, Đỗ, Nguyễn, 🔴, 🟡, 🟢
 * 6. Extremely long inputs (10k chars)
 * 7. Malformed DOM rows: missing data-level, missing data-name, missing data-search
 * 8. Non-student rows in tbody (empty rows, summary rows, header rows)
 * 9. Empty table scenario: 0 rows, clicking all chips
 * 10. Missing DOM elements: null search input, null emptyRow, null chips
 * =============================================================================
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let passed = 0;
let failed = 0;
const findings = [];

function assertTest(name, fn) {
    try {
        fn();
        passed++;
        console.log(`[PASS] ${name}`);
    } catch (e) {
        failed++;
        console.error(`[FAIL] ${name}: ${e.message}`);
        findings.push({ name, error: e.message, stack: e.stack });
    }
}

// Simple DOM Mock Environment conforming to what main.js uses
class MockElement {
    constructor(tag, attrs = {}) {
        this.tagName = tag.toUpperCase();
        this.attributes = { ...attrs };
        this.dataset = {};
        for (const [k, v] of Object.entries(attrs)) {
            if (k.startsWith('data-')) {
                const camelKey = k.slice(5).replace(/-([a-z])/g, (_, g) => g.toUpperCase());
                this.dataset[camelKey] = v;
            }
        }
        this.id = attrs.id || '';
        this.className = attrs.class || '';
        this.classList = {
            _classes: new Set((attrs.class || '').split(/\s+/).filter(Boolean)),
            add: (c) => this.classList._classes.add(c),
            remove: (c) => this.classList._classes.delete(c),
            contains: (c) => this.classList._classes.has(c)
        };
        this.style = { display: '' };
        this.value = attrs.value || '';
        this.listeners = {};
    }

    addEventListener(event, fn) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(fn);
    }

    trigger(event) {
        if (this.listeners[event]) {
            for (const fn of this.listeners[event]) {
                fn.call(this);
            }
        }
    }
}

class MockDocument {
    constructor() {
        this.listeners = {};
        this.elements = [];
    }

    addEventListener(event, fn) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(fn);
    }

    trigger(event) {
        if (this.listeners[event]) {
            for (const fn of this.listeners[event]) {
                fn.call(this);
            }
        }
    }

    getElementById(id) {
        return this.elements.find(e => e.id === id) || null;
    }

    querySelectorAll(selector) {
        if (selector === 'tbody tr[data-level]') {
            return this.elements.filter(e => e.tagName === 'TR' && e.dataset.level !== undefined);
        }
        if (selector === '.chip[data-filter]') {
            return this.elements.filter(e => e.classList.contains('chip') && e.dataset.filter !== undefined);
        }
        return [];
    }
}

function loadMainJsInContext(mockDoc) {
    const code = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'main.js'), 'utf8');
    const context = {
        document: mockDoc,
        console: console,
        setTimeout: setTimeout,
        clearTimeout: clearTimeout
    };
    vm.createContext(context);
    vm.runInContext(code, context);
    mockDoc.trigger('DOMContentLoaded');
}

console.log('============================================================');
console.log('  EduRadar Milestone 5: Adversarial JS DOM Stress Harness   ');
console.log('============================================================\n');

// -------------------------------------------------------------
// Group 1: Regex Special Characters & Injection Resilience
// -------------------------------------------------------------
console.log('--- NHÓM 1: Thử thách Ký tự Regex đặc biệt & Mã Độc ---');

const regexChars = ['[', ']', '*', '+', '\\', '(', ')', '?', '^', '$', '{', '}', '|', '.', '.*', '[a-z]+', '(?:test)', '\\d{1,2}'];

regexChars.forEach(char => {
    assertTest(`1. Search query with regex character '${char}' does not throw syntax error or TypeError`, () => {
        const doc = new MockDocument();
        const searchInput = new MockElement('input', { id: 'search' });
        const emptyRow = new MockElement('tr', { id: 'radarEmptyRow' });
        const row1 = new MockElement('tr', { 'data-level': 'red', 'data-name': 'nguyễn văn an', 'data-search': 'hs01 nguyễn văn an 6a1 toán' });
        const row2 = new MockElement('tr', { 'data-level': 'green', 'data-name': 'trần thị bích', 'data-search': 'hs02 trần thị bích 6a1 toán' });
        const chipAll = new MockElement('button', { class: 'chip active', 'data-filter': 'all' });
        
        doc.elements = [searchInput, emptyRow, row1, row2, chipAll];
        loadMainJsInContext(doc);

        searchInput.value = char;
        searchInput.trigger('input');
    });
});

const htmlPayloads = [
    '<script>alert(1)</script>',
    '<img src=x onerror=alert(1)>',
    '"><svg onload=alert(1)>',
    '<b onmouseover=alert(1)>test</b>',
    '&lt;script&gt;',
    '<iframe src="javascript:alert(1)"></iframe>'
];

htmlPayloads.forEach(payload => {
    assertTest(`1. Search query with HTML/XSS payload '${payload.slice(0, 20)}...' executes safely without TypeError`, () => {
        const doc = new MockDocument();
        const searchInput = new MockElement('input', { id: 'search' });
        const emptyRow = new MockElement('tr', { id: 'radarEmptyRow' });
        const row1 = new MockElement('tr', { 'data-level': 'red', 'data-name': 'nguyễn văn an', 'data-search': 'hs01 nguyễn văn an 6a1 toán' });
        doc.elements = [searchInput, emptyRow, row1];
        loadMainJsInContext(doc);

        searchInput.value = payload;
        searchInput.trigger('input');
        // If string does not match, row1 should be hidden
        if (row1.style.display !== 'none') {
            throw new Error('Expected non-matching HTML payload to hide row');
        }
    });
});

const quotePayloads = ["'", '"', '`', "\\'", '\\"', "\0", "\n", "\r\n", "\t", "   "];
quotePayloads.forEach((q, idx) => {
    assertTest(`1. Search query with quote/whitespace payload #${idx + 1} executes safely without TypeError`, () => {
        const doc = new MockDocument();
        const searchInput = new MockElement('input', { id: 'search' });
        const emptyRow = new MockElement('tr', { id: 'radarEmptyRow' });
        const row1 = new MockElement('tr', { 'data-level': 'red', 'data-name': 'nguyễn văn an', 'data-search': 'hs01 nguyễn văn an 6a1 toán' });
        doc.elements = [searchInput, emptyRow, row1];
        loadMainJsInContext(doc);

        searchInput.value = q;
        searchInput.trigger('input');
    });
});

// -------------------------------------------------------------
// Group 2: Empty Table & Missing DOM Elements
// -------------------------------------------------------------
console.log('\n--- NHÓM 2: Thử thách Bảng Rỗng & Phần tử DOM Khuyết Thiếu ---');

assertTest('2.1 Empty table (0 student rows) clicking filter chips causes zero TypeErrors', () => {
    const doc = new MockDocument();
    const searchInput = new MockElement('input', { id: 'search' });
    const emptyRow = new MockElement('tr', { id: 'radarEmptyRow' });
    const chipAll = new MockElement('button', { class: 'chip active', 'data-filter': 'all' });
    const chipRed = new MockElement('button', { class: 'chip', 'data-filter': 'red' });
    const chipYellow = new MockElement('button', { class: 'chip', 'data-filter': 'yellow' });
    const chipGreen = new MockElement('button', { class: 'chip', 'data-filter': 'green' });

    doc.elements = [searchInput, emptyRow, chipAll, chipRed, chipYellow, chipGreen];
    loadMainJsInContext(doc);

    // Click each chip on empty table
    chipRed.trigger('click');
    if (!chipRed.classList.contains('active') || chipAll.classList.contains('active')) {
        throw new Error('Chip active state toggle failed on empty table');
    }
    chipYellow.trigger('click');
    chipGreen.trigger('click');
    chipAll.trigger('click');

    // Type into search on empty table
    searchInput.value = 'abc';
    searchInput.trigger('input');
    searchInput.value = '';
    searchInput.trigger('input');
});

assertTest('2.2 Missing search element in DOM does not throw TypeError on load or chip click', () => {
    const doc = new MockDocument();
    // No search element
    const emptyRow = new MockElement('tr', { id: 'radarEmptyRow' });
    const row1 = new MockElement('tr', { 'data-level': 'red', 'data-name': 'nguyễn văn an', 'data-search': 'hs01' });
    const chipRed = new MockElement('button', { class: 'chip', 'data-filter': 'red' });

    doc.elements = [emptyRow, row1, chipRed];
    loadMainJsInContext(doc);

    chipRed.trigger('click');
    if (row1.style.display !== '') {
        throw new Error('Expected red row to remain visible when red chip clicked');
    }
});

assertTest('2.3 Missing radarEmptyRow element in DOM does not throw TypeError', () => {
    const doc = new MockDocument();
    const searchInput = new MockElement('input', { id: 'search' });
    // No emptyRow element
    const row1 = new MockElement('tr', { 'data-level': 'red', 'data-name': 'nguyễn văn an', 'data-search': 'hs01' });
    const chipRed = new MockElement('button', { class: 'chip', 'data-filter': 'red' });

    doc.elements = [searchInput, row1, chipRed];
    loadMainJsInContext(doc);

    searchInput.value = 'nonexistent student';
    searchInput.trigger('input');
    if (row1.style.display !== 'none') {
        throw new Error('Expected row to be hidden');
    }
});

// -------------------------------------------------------------
// Group 3: Non-student Rows & Malformed Data Attributes
// -------------------------------------------------------------
console.log('\n--- NHÓM 3: Hàng Phi Học Sinh & Dữ Liệu Thuộc Tính Bị Khuyết ---');

assertTest('3.1 Row with missing data-name and data-search does not throw TypeError on search', () => {
    const doc = new MockDocument();
    const searchInput = new MockElement('input', { id: 'search' });
    const emptyRow = new MockElement('tr', { id: 'radarEmptyRow' });
    // Row only has data-level, missing data-name and data-search
    const malformedRow = new MockElement('tr', { 'data-level': 'yellow' });
    delete malformedRow.dataset.name;
    delete malformedRow.dataset.search;

    doc.elements = [searchInput, emptyRow, malformedRow];
    loadMainJsInContext(doc);

    searchInput.value = 'anything';
    searchInput.trigger('input');
    if (malformedRow.style.display !== 'none') {
        throw new Error('Malformed row should be hidden when non-empty search does not match');
    }

    searchInput.value = '';
    searchInput.trigger('input');
    if (malformedRow.style.display !== '') {
        throw new Error('Malformed row should be visible when search is cleared');
    }
});

assertTest('3.2 Non-student rows without data-level are never affected by filter or search', () => {
    const doc = new MockDocument();
    const searchInput = new MockElement('input', { id: 'search' });
    const emptyRow = new MockElement('tr', { id: 'radarEmptyRow' });
    const headerRow = new MockElement('tr', { class: 'sub-header' }); // No data-level
    const studentRow = new MockElement('tr', { 'data-level': 'green', 'data-name': 'nguyễn văn a', 'data-search': 'hs01' });

    doc.elements = [searchInput, emptyRow, headerRow, studentRow];
    loadMainJsInContext(doc);

    headerRow.style.display = 'table-row'; // Pre-set
    searchInput.value = 'xyz';
    searchInput.trigger('input');

    // studentRow should be hidden
    if (studentRow.style.display !== 'none') {
        throw new Error('Student row should be hidden');
    }
    // headerRow must not be touched or altered
    if (headerRow.style.display !== 'table-row') {
        throw new Error('Non-student row was unexpectedly modified by main.js');
    }
});

assertTest('3.3 Extreme long input (10,000 characters) executes with 0 errors and hides unmatched rows', () => {
    const doc = new MockDocument();
    const searchInput = new MockElement('input', { id: 'search' });
    const emptyRow = new MockElement('tr', { id: 'radarEmptyRow' });
    const row1 = new MockElement('tr', { 'data-level': 'red', 'data-name': 'nguyễn văn an', 'data-search': 'hs01 nguyễn văn an' });
    doc.elements = [searchInput, emptyRow, row1];
    loadMainJsInContext(doc);

    searchInput.value = 'A'.repeat(10000);
    const t0 = Date.now();
    searchInput.trigger('input');
    const elapsed = Date.now() - t0;

    if (row1.style.display !== 'none') {
        throw new Error('Expected row to be hidden for 10k query');
    }
    if (elapsed > 1000) {
        throw new Error(`Search took too long: ${elapsed}ms`);
    }
});

console.log('\n============================================================');
console.log(`  Adversarial JS DOM Results: Passed: ${passed} | Failed: ${failed}`);
console.log('============================================================');

if (failed > 0) {
    process.exit(1);
}
