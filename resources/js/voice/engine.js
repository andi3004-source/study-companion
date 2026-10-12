/**
 * "Mode suara": one shared voice layer for the whole app (Web Speech API,
 * Chrome or Edge). A complement only: the semantic markup and screen readers
 * stay the main path, and this never changes them.
 *
 * Turn taking: the microphone is never open while speech synthesis runs; a
 * short beep (Web Audio) plays right before it opens. Transcripts go only to
 * POST /voice/command as text; no audio, image or transcript is stored in
 * localStorage, sessionStorage or on the server. localStorage holds only the
 * one-time consent flag; sessionStorage holds only "aktif" so the mode
 * resumes after a page load. There are no global single-key shortcuts.
 */

const CONSENT_KEY = 'modeSuaraSetuju';
const SESSION_KEY = 'modeSuara';
const SILENCE_MS = 8000;
const MAX_REMINDERS = 2;
const MIN_CONFIDENCE = 0.6;
const GROUP_SIZE = 6;
const DANGEROUS = /hapus|reset|selesai dan analisis|keluar/i;

export const MESSAGES = {
    unsupported: 'Mode suara membutuhkan Chrome atau Edge',
    consent:
        'Mode suara memakai pengenalan suara bawaan browser (Chrome atau Edge). Suaramu dikirim ke penyedia browser untuk diubah menjadi teks. Aplikasi ini tidak menyimpan suara.',
    resume: 'Mode suara aktif. Ketuk atau tekan Enter pada tombol Mode suara untuk melanjutkan mikrofon',
    silenceStop: 'Mode suara berhenti karena tidak ada suara. Keyboard tetap bisa dipakai.',
    reminder: 'Saya masih mendengarkan. Katakan perintah untuk mendengar daftar perintah.',
    noZhVoice: 'Suara Mandarin belum terpasang di perangkat ini, jadi teks Mandarin mungkin tidak terbaca dengan benar.',
    errors: {
        'not-allowed': 'Izin mikrofon ditolak. Izinkan mikrofon di pengaturan browser, lalu coba lagi.',
        'service-not-allowed': 'Izin mikrofon ditolak. Izinkan mikrofon di pengaturan browser, lalu coba lagi.',
        'no-speech': 'Tidak ada suara yang terdengar.',
        network: 'Pengenalan suara butuh koneksi internet. Periksa koneksi, lalu coba lagi.',
        aborted: 'Mikrofon dihentikan.',
        'audio-capture': 'Mikrofon tidak ditemukan. Periksa mikrofon perangkat.',
    },
    help: 'Perintah: baca halaman. Daftar tindakan. Nomor satu sampai dua belas. Lanjut. Kembali. Ulangi. Buka, lalu nama halaman, misalnya buka kartu. Isi, untuk mengetik di kolom yang dipilih, dan selesai isi. Berhenti.',
};

const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;

/** Lowercase, no punctuation, single spaces: for local matching only. */
export function normalize(text) {
    return String(text || '')
        .toLowerCase()
        .replace(/[^\p{L}\p{N}\s]+/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** The accessible name of an element, close to what a screen reader announces. */
export function accessibleName(el) {
    if (!el) return '';
    const label = el.getAttribute('aria-label');
    if (label) return label.trim();
    const labelledby = el.getAttribute('aria-labelledby');
    if (labelledby) {
        const text = labelledby
            .split(/\s+/)
            .map((id) => document.getElementById(id)?.textContent || '')
            .join(' ');
        if (text.trim()) return text.replace(/\s+/g, ' ').trim();
    }
    if (el.id) {
        const forLabel = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
        if (forLabel) return forLabel.textContent.replace(/\s+/g, ' ').trim();
    }
    const wrapping = el.closest('label');
    if (wrapping) return wrapping.textContent.replace(/\s+/g, ' ').trim();
    const clone = el.cloneNode(true);
    clone.querySelectorAll('[aria-hidden="true"]').forEach((n) => n.remove());
    return clone.textContent.replace(/\s+/g, ' ').trim();
}

/** Visible, enabled, not inside a closed <details> or a <template>. */
export function isUsable(el) {
    if (!el || el.disabled || el.closest('[disabled], template, [aria-hidden="true"], [inert]')) return false;
    if (el.getAttribute('aria-disabled') === 'true') return false;
    if (el.closest('details:not([open])') && !el.matches('summary')) return false;
    return el.getClientRects().length > 0;
}

const VoiceMode = {
    adapters: {},
    active: false,
    listening: false,
    speaking: false,
    recognition: null,
    audioCtx: null,
    pending: null, // { text, onYes, onNo }
    actions: [],
    actionPage: 0,
    lastSpoken: [],
    reminders: 0,
    silenceTimer: null,
    dictating: null,
    warnedZh: false,
    endpoint: null,
    routes: {},

    supported() {
        return !!Recognition && 'speechSynthesis' in window;
    },

    /** Registers a page adapter: { matches(), readPage?(), handleTranscript?(text, raw), handleCommand?(cmd), handleUnknown?(raw), onActivate?() }. */
    register(name, adapter) {
        this.adapters[name] = adapter;
    },

    adapter() {
        const page = document.querySelector('[data-voice-page]')?.getAttribute('data-voice-page');
        return (page && this.adapters[page]) || null;
    },

    announce(text) {
        const region = document.getElementById('mode-suara-status');
        if (region) region.textContent = text;
    },

    consented() {
        try {
            return localStorage.getItem(CONSENT_KEY) === '1';
        } catch (e) {
            return false;
        }
    },

    giveConsent() {
        try {
            localStorage.setItem(CONSENT_KEY, '1');
        } catch (e) {}
    },

    remembered() {
        try {
            return sessionStorage.getItem(SESSION_KEY) === 'aktif';
        } catch (e) {
            return false;
        }
    },

    remember(on) {
        try {
            if (on) sessionStorage.setItem(SESSION_KEY, 'aktif');
            else sessionStorage.removeItem(SESSION_KEY);
        } catch (e) {}
    },

    /** Turns Mode pembaca layar off (they are mutually exclusive). */
    turnOffScreenReaderMode() {
        if (!document.documentElement.classList.contains('mode-pembaca-layar')) return false;
        document.documentElement.classList.remove('mode-pembaca-layar');
        try {
            const tampilan = JSON.parse(localStorage.getItem('tampilan') || '{}') || {};
            localStorage.setItem('tampilan', JSON.stringify({ ...tampilan, pembacaLayar: false }));
        } catch (e) {}
        window.dispatchEvent(new CustomEvent('mode-pembaca-layar-mati'));
        return true;
    },

    async start() {
        if (!this.supported()) {
            this.announce(MESSAGES.unsupported);
            return;
        }
        const srOff = this.turnOffScreenReaderMode();
        this.active = true;
        this.reminders = 0;
        this.remember(true);
        window.dispatchEvent(new CustomEvent('mode-suara', { detail: { aktif: true } }));
        const intro = (srOff ? 'Mode pembaca layar dimatikan karena Mode suara aktif. ' : '') + 'Mode suara aktif. Katakan perintah untuk daftar perintah.';
        this.announce(intro);
        await this.say([{ text: intro, lang: 'id-ID' }]);
        this.adapter()?.onActivate?.();
        this.listen();
    },

    /** Stops everything: microphone, speech, timers. Safe to call any time. */
    stop(message = 'Mode suara mati.') {
        this.active = false;
        this.dictating = null;
        this.pending = null;
        this.remember(false);
        this.cleanup();
        window.dispatchEvent(new CustomEvent('mode-suara', { detail: { aktif: false } }));
        if (message) this.announce(message);
    },

    cleanup() {
        clearTimeout(this.silenceTimer);
        this.silenceTimer = null;
        if (this.recognition) {
            this.recognition.onresult = this.recognition.onerror = this.recognition.onend = null;
            try {
                this.recognition.abort();
            } catch (e) {}
            this.recognition = null;
        }
        this.listening = false;
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        this.speaking = false;
        if (this.audioCtx) {
            this.audioCtx.close().catch(() => {});
            this.audioCtx = null;
        }
    },

    /** Speaks segments in order, switching voice language per segment. Resolves when done. */
    say(segments) {
        if (!('speechSynthesis' in window)) return Promise.resolve();
        this.stopListening();
        this.lastSpoken = segments;
        this.speaking = true;
        window.speechSynthesis.cancel();

        if (!this.warnedZh && segments.some((s) => s.lang === 'zh-CN')) {
            const voices = window.speechSynthesis.getVoices();
            if (voices.length && !voices.some((v) => v.lang.toLowerCase().startsWith('zh'))) {
                this.warnedZh = true;
                segments = [{ text: MESSAGES.noZhVoice, lang: 'id-ID' }, ...segments];
            }
        }

        return new Promise((resolve) => {
            const items = segments.filter((s) => s.text && String(s.text).trim());
            if (!items.length) {
                this.speaking = false;
                resolve();
                return;
            }
            items.forEach((segment, i) => {
                const u = new SpeechSynthesisUtterance(String(segment.text));
                u.lang = segment.lang || 'id-ID';
                if (u.lang.startsWith('zh')) u.rate = 0.85;
                if (i === items.length - 1) {
                    u.onend = u.onerror = () => {
                        this.speaking = false;
                        resolve();
                    };
                }
                window.speechSynthesis.speak(u);
            });
        });
    },

    /** Says text in Indonesian and also puts it in the status region. */
    async tell(text) {
        this.announce(text);
        await this.say([{ text, lang: 'id-ID' }]);
    },

    beep() {
        try {
            this.audioCtx = this.audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.frequency.value = 880;
            gain.gain.value = 0.08;
            osc.connect(gain).connect(this.audioCtx.destination);
            osc.start();
            osc.stop(this.audioCtx.currentTime + 0.12);
            return new Promise((r) => setTimeout(r, 160));
        } catch (e) {
            return Promise.resolve();
        }
    },

    stopListening() {
        clearTimeout(this.silenceTimer);
        if (this.recognition) {
            this.recognition.onresult = this.recognition.onerror = this.recognition.onend = null;
            try {
                this.recognition.abort();
            } catch (e) {}
            this.recognition = null;
        }
        this.listening = false;
    },

    /** Opens the microphone for one utterance (never while speaking). */
    async listen() {
        if (!this.active || this.speaking || this.listening) return;
        await this.beep();
        if (!this.active || this.speaking) return;

        const rec = new Recognition();
        rec.lang = 'id-ID';
        rec.maxAlternatives = 3;
        rec.interimResults = false;
        rec.continuous = false;
        this.recognition = rec;
        let gotResult = false;

        rec.onresult = (event) => {
            gotResult = true;
            clearTimeout(this.silenceTimer);
            const result = event.results[0];
            const alternatives = Array.from({ length: result.length }, (_, i) => ({
                transcript: result[i].transcript,
                confidence: result[i].confidence,
            }));
            this.listening = false;
            this.recognition = null;
            this.reminders = 0;
            this.onUtterance(alternatives);
        };

        rec.onerror = (event) => {
            if (event.error === 'no-speech' || event.error === 'aborted') return; // handled by the silence timer / onend
            this.stop(MESSAGES.errors[event.error] || 'Mode suara berhenti karena galat. Keyboard tetap bisa dipakai.');
        };

        rec.onend = () => {
            this.listening = false;
            this.recognition = null;
            if (!gotResult && this.active && !this.speaking) this.onSilence();
        };

        try {
            rec.start();
            this.listening = true;
            this.silenceTimer = setTimeout(() => {
                if (this.recognition === rec) rec.stop();
            }, SILENCE_MS);
        } catch (e) {
            // Browsers can refuse to open the microphone without a user gesture after a page load.
            this.listening = false;
            this.announce(MESSAGES.resume);
            document.getElementById('tombol-mode-suara')?.focus();
        }
    },

    async onSilence() {
        this.reminders++;
        if (this.reminders > MAX_REMINDERS) {
            await this.say([{ text: MESSAGES.silenceStop, lang: 'id-ID' }]);
            this.stop(MESSAGES.silenceStop);
            return;
        }
        await this.tell(MESSAGES.reminder);
        this.listen();
    },

    /** Low confidence or two different candidates: ask before acting. */
    async onUtterance(alternatives) {
        const best = alternatives[0] || { transcript: '', confidence: 0 };
        const heard = best.transcript.trim();
        const second = alternatives[1] ? normalize(alternatives[1].transcript) : null;
        const unsure =
            !this.dictating &&
            !this.pending &&
            ((typeof best.confidence === 'number' && best.confidence > 0 && best.confidence < MIN_CONFIDENCE) ||
                (second && second !== normalize(heard)));

        if (unsure && heard) {
            this.pending = {
                onYes: () => this.handle(heard),
                onNo: () => this.tell('Baik, silakan ulangi.'),
            };
            await this.tell(`Saya dengar: ${heard}. Benar? Katakan ya atau tidak`);
            this.listen();
            return;
        }

        await this.handle(heard);
        this.listen();
    },

    async handle(raw) {
        const text = normalize(raw);

        if (this.dictating) {
            if (['selesai isi', 'berhenti isi', 'selesai ketik', 'berhenti ketik'].includes(text)) {
                const name = accessibleName(this.dictating);
                this.dictating = null;
                await this.tell(`Selesai mengisi ${name}. Teks tidak dikirim otomatis.`);
                return;
            }
            const field = this.dictating;
            field.value = (field.value ? field.value + ' ' : '') + raw.trim();
            field.dispatchEvent(new Event('input', { bubbles: true }));
            await this.tell('Ditambahkan.');
            return;
        }

        const adapter = this.adapter();
        if (adapter?.handleTranscript && (await adapter.handleTranscript(text, raw, this))) return;

        const cmd = await this.parse(raw);

        if (this.pending && (cmd.type === 'confirm_yes' || cmd.type === 'confirm_no')) {
            const pending = this.pending;
            this.pending = null;
            await (cmd.type === 'confirm_yes' ? pending.onYes() : pending.onNo());
            return;
        }
        this.pending = null;

        if (adapter?.handleCommand && (await adapter.handleCommand(cmd, this))) return;
        if (cmd.type === 'unknown' && adapter?.handleUnknown && (await adapter.handleUnknown(raw, this))) return;

        await this.run(cmd);
    },

    async parse(raw) {
        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ transcript: String(raw).slice(0, 200) }),
            });
            if (!response.ok) return { type: 'unknown', target: null, index: null };
            return await response.json();
        } catch (e) {
            return { type: 'unknown', target: null, index: null };
        }
    },

    async run(cmd) {
        switch (cmd.type) {
            case 'navigate':
                if (this.routes[cmd.target]) {
                    await this.tell('Membuka halaman.');
                    window.location.href = this.routes[cmd.target];
                }
                return;
            case 'not_available':
                return this.tell('Halaman itu belum tersedia.');
            case 'back':
                await this.tell('Kembali.');
                window.history.back();
                return;
            case 'repeat':
                return this.say(this.lastSpoken.length ? this.lastSpoken : [{ text: 'Belum ada yang dibacakan.', lang: 'id-ID' }]);
            case 'read_page':
                return this.readPage();
            case 'list_actions':
                this.actions = this.collectActions();
                this.actionPage = 0;
                return this.readActions();
            case 'more':
                this.actionPage++;
                return this.readActions();
            case 'choose_number':
                return this.choose(cmd.index);
            case 'confirm_yes':
            case 'confirm_no':
                return this.tell('Tidak ada yang perlu dikonfirmasi.');
            case 'stop':
                await this.say([{ text: 'Mode suara mati. Keyboard tetap bisa dipakai.', lang: 'id-ID' }]);
                this.stop('Mode suara mati. Keyboard tetap bisa dipakai.');
                return;
            case 'help':
                return this.tell(MESSAGES.help);
            case 'dictate_start':
                return this.startDictation();
            case 'dictate_stop':
                return this.tell('Tidak sedang mengisi kolom.');
            default:
                return this.tell('Maaf, perintah tidak dikenali. Katakan perintah untuk daftar perintah.');
        }
    },

    main() {
        return document.querySelector('main') || document.body;
    },

    /** h1, intro paragraph, section headings in <main>, and any non-empty status text. */
    readPage() {
        const adapter = this.adapter();
        if (adapter?.readPage) return adapter.readPage(this);

        const main = this.main();
        const parts = [];
        const h1 = main.querySelector('h1');
        if (h1) {
            parts.push(h1.textContent.trim());
            const intro = h1.parentElement.querySelector('h1 ~ p');
            if (intro && isUsable(intro)) parts.push(intro.textContent.trim());
        }
        main.querySelectorAll('h2, h3').forEach((h) => {
            if (isUsable(h) && !h.classList.contains('sr-only')) parts.push(h.textContent.trim());
        });
        parts.push(...this.statusTexts());
        return this.tell(parts.filter(Boolean).join('. ') || 'Halaman ini belum punya judul.');
    },

    statusTexts() {
        return Array.from(this.main().querySelectorAll('[role="status"], [role="alert"]'))
            .map((el) => el.textContent.replace(/\s+/g, ' ').trim())
            .filter(Boolean);
    },

    /** Numbered list of usable controls in <main>: buttons, links, radios (as options), labelled fields. */
    collectActions() {
        const selector = 'a[href], button, summary, input:not([type="hidden"]):not([type="file"]), textarea, select, [role="button"][tabindex]';
        return Array.from(this.main().querySelectorAll(selector))
            .filter((el) => isUsable(el) || (el.matches('input[type="radio"]') && isUsable(el.closest('label') || el)))
            .map((el) => ({ el, name: accessibleName(el) }))
            .filter((a) => a.name);
    },

    readActions() {
        if (!this.actions.length) return this.tell('Tidak ada tindakan di halaman ini.');
        const start = this.actionPage * GROUP_SIZE;
        const group = this.actions.slice(start, start + GROUP_SIZE);
        if (!group.length) {
            this.actionPage = 0;
            return this.tell('Itu semua tindakan. Katakan daftar tindakan untuk mendengar dari awal.');
        }
        const lines = group.map((a, i) => `${start + i + 1}. ${this.describe(a)}`);
        const more = start + GROUP_SIZE < this.actions.length ? ' Katakan lanjut untuk berikutnya.' : '';
        const intro = start === 0 ? `Ada ${this.actions.length} tindakan. ` : '';
        return this.tell(`${intro}${lines.join('. ')}. Katakan nomornya.${more}`);
    },

    describe(action) {
        const el = action.el;
        if (el.matches('input[type="radio"]')) return `pilihan ${action.name}${el.checked ? ', terpilih' : ''}`;
        if (el.matches('input, textarea, select')) return `kolom ${action.name}`;
        if (el.matches('a')) return `tautan ${action.name}`;
        return action.name;
    },

    async choose(index) {
        if (!this.actions.length) this.actions = this.collectActions();
        const action = this.actions[index];
        if (!action || !isUsable(action.el.matches('input[type="radio"]') ? action.el.closest('label') || action.el : action.el)) {
            return this.tell('Nomor itu tidak ada. Katakan daftar tindakan.');
        }
        return this.activate(action.el, action.name);
    },

    /** Click, select, or focus; irreversible actions ask first. */
    async activate(el, name = accessibleName(el)) {
        if (el.matches('input[type="radio"], input[type="checkbox"]')) {
            el.click();
            return this.afterAction(`${name} dipilih.`);
        }
        if (el.matches('input, textarea, select')) {
            el.focus();
            return this.tell(`Kolom ${name}. Katakan isi untuk mulai mengisi.`);
        }
        if (DANGEROUS.test(name)) {
            this.pending = {
                onYes: async () => {
                    el.click();
                    await this.afterAction(`${name} ditekan.`);
                },
                onNo: () => this.tell('Dibatalkan.'),
            };
            return this.tell('Yakin? Katakan ya atau tidak');
        }
        el.click();
        return this.afterAction(`${name} ditekan.`);
    },

    /** Waits for Livewire to update, then reads what appeared in the status regions. */
    async afterAction(prefix) {
        await new Promise((r) => setTimeout(r, 900));
        this.actions = [];
        const status = this.statusTexts().join('. ');
        return this.tell([prefix, status].filter(Boolean).join(' '));
    },

    startDictation() {
        const field = document.activeElement;
        if (!field || !field.matches('input:not([type="radio"]):not([type="checkbox"]), textarea')) {
            return this.tell('Pilih kolom isian dulu lewat daftar tindakan.');
        }
        this.dictating = field;
        return this.tell(`Mengisi ${accessibleName(field)}. Bicaralah. Katakan selesai isi untuk berhenti.`);
    },
};

window.VoiceMode = VoiceMode;

// Stop the microphone and speech whenever the page goes away.
window.addEventListener('pagehide', () => VoiceMode.cleanup());

// Mutually exclusive with Mode pembaca layar: switching that on stops this.
window.addEventListener('mode-pembaca-layar-aktif', () => {
    if (VoiceMode.active) VoiceMode.stop('Mode suara dimatikan karena Mode pembaca layar aktif.');
});

/** Alpine component for the global "Mode suara" controls in layouts/study.blade.php. */
window.voiceModeBar = function (endpoint, routes) {
    return {
        aktif: false,
        didukung: VoiceMode.supported(),
        mintaSetuju: false,

        init() {
            VoiceMode.endpoint = endpoint;
            VoiceMode.routes = routes;
            window.addEventListener('mode-suara', (e) => (this.aktif = e.detail.aktif));
            if (!this.didukung) return;
            if (VoiceMode.remembered()) {
                // Resume after a page load; browsers may need a tap to reopen the microphone.
                this.aktif = true;
                VoiceMode.active = true;
                VoiceMode.announce(MESSAGES.resume);
                this.$nextTick(() => this.$refs.tombol.focus());
            }
        },

        toggle() {
            if (!this.didukung) {
                VoiceMode.announce(MESSAGES.unsupported);
                return;
            }
            // Pressing it while the microphone or speech is running turns the mode off;
            // pressing it after a page load (mode remembered, microphone closed) resumes it.
            if (this.aktif && (VoiceMode.listening || VoiceMode.speaking)) {
                VoiceMode.stop();
                return;
            }
            if (!VoiceMode.consented()) {
                this.mintaSetuju = true;
                this.$nextTick(() => this.$refs.setuju.focus());
                return;
            }
            VoiceMode.start();
        },

        setuju() {
            VoiceMode.giveConsent();
            this.mintaSetuju = false;
            VoiceMode.start();
        },

        batal() {
            this.mintaSetuju = false;
            this.$nextTick(() => this.$refs.tombol.focus());
        },

        berhenti() {
            VoiceMode.stop();
            this.$nextTick(() => this.$refs.tombol.focus());
        },
    };
};

export default VoiceMode;
