/**
 * Public home page (welcome.blade.php). Plain JS, no Alpine or Livewire, no
 * requests to outside services. Everything here is an enhancement: without
 * JavaScript the page still reads top to bottom, the mobile menu stays open,
 * and the JS-only controls ([data-js-only]) stay hidden.
 */
import '@fontsource/plus-jakarta-sans/400.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';
import '@fontsource/plus-jakarta-sans/800.css';
import '@fontsource/caveat/600.css';

// Same localStorage key and classes as the app's "Tampilan" menu (resources/js/app.js).
const KEY = 'tampilan';
const UKURAN = ['normal', 'besar', 'sangat-besar'];

function readSettings() {
    try {
        return JSON.parse(localStorage.getItem(KEY) || '{}') || {};
    } catch (e) {
        return {};
    }
}

function saveSettings(changes) {
    try {
        localStorage.setItem(KEY, JSON.stringify({ ...readSettings(), ...changes }));
    } catch (e) {}
}

function applySettings({ ukuran = 'normal', kontras = false }) {
    const html = document.documentElement.classList;
    html.toggle('teks-besar', ukuran === 'besar');
    html.toggle('teks-sangat-besar', ukuran === 'sangat-besar');
    html.toggle('kontras-tinggi', !!kontras);

    document.querySelector('[data-ukuran="normal"]')?.setAttribute('aria-pressed', String(ukuran === 'normal'));
    document.querySelector('[data-ukuran="lebih"]')?.setAttribute('aria-pressed', String(ukuran !== 'normal'));
    document.querySelector('[data-kontras]')?.setAttribute('aria-pressed', String(!!kontras));
}

function announce(text) {
    const region = document.getElementById('tampilan-status');
    if (region) region.textContent = text;
}

function setupDisplayControls() {
    applySettings(readSettings());

    document.querySelector('[data-ukuran="normal"]')?.addEventListener('click', () => {
        saveSettings({ ukuran: 'normal' });
        applySettings(readSettings());
        announce('Ukuran teks normal.');
    });

    // A+ goes one step up (normal → besar → sangat besar) and stays at the largest.
    document.querySelector('[data-ukuran="lebih"]')?.addEventListener('click', () => {
        const now = UKURAN.indexOf(readSettings().ukuran || 'normal');
        const next = UKURAN[Math.min(now + 1, UKURAN.length - 1)];
        saveSettings({ ukuran: next });
        applySettings(readSettings());
        announce(next === 'sangat-besar' ? 'Ukuran teks sangat besar (paling besar).' : 'Ukuran teks besar.');
    });

    document.querySelector('[data-kontras]')?.addEventListener('click', () => {
        const kontras = !readSettings().kontras;
        saveSettings({ kontras });
        applySettings(readSettings());
        announce(kontras ? 'Mode kontras tinggi aktif.' : 'Mode kontras tinggi mati.');
    });
}

function setupMenu() {
    const button = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');
    if (!button || !menu) return;

    const setOpen = (open) => {
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
        menu.toggleAttribute('data-open', open);
    };

    setOpen(false);
    button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
    // Choosing a section closes the menu on small screens.
    menu.querySelectorAll('a[href^="#"]').forEach((a) => a.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            button.focus();
        }
    });
}

/** Demo widget tabs (Mandarin / Inggris): WAI-ARIA tabs with arrow keys. */
function setupTabs() {
    document.querySelectorAll('[role="tablist"]').forEach((list) => {
        const tabs = Array.from(list.querySelectorAll('[role="tab"]'));
        const select = (tab) => {
            tabs.forEach((t) => {
                const selected = t === tab;
                t.setAttribute('aria-selected', String(selected));
                t.tabIndex = selected ? 0 : -1;
                document.getElementById(t.getAttribute('aria-controls')).hidden = !selected;
            });
        };
        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => select(tab));
            tab.addEventListener('keydown', (e) => {
                const step = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
                if (!step) return;
                e.preventDefault();
                const next = tabs[(i + step + tabs.length) % tabs.length];
                select(next);
                next.focus();
            });
        });
    });
}

/** "Dengarkan" buttons read their text with the browser's own speech synthesis. */
function setupSpeech() {
    if (!('speechSynthesis' in window)) return;
    document.querySelectorAll('[data-ucapkan]').forEach((button) => {
        button.closest('[data-js-only]')?.removeAttribute('hidden');
        button.addEventListener('click', () => {
            window.speechSynthesis.cancel();
            const u = new SpeechSynthesisUtterance(button.getAttribute('data-ucapkan'));
            u.lang = button.getAttribute('data-lang') || 'zh-CN';
            u.rate = 0.85;
            window.speechSynthesis.speak(u);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-js-only]:not([data-js-only="speech"])').forEach((el) => el.removeAttribute('hidden'));
    setupDisplayControls();
    setupMenu();
    setupTabs();
    setupSpeech();
});
