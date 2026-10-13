/**
 * Page adapters for "Mode suara". Each page marks its root with
 * data-voice-page="<name>"; the engine asks the matching adapter first and
 * falls back to the generic actions (baca halaman, daftar tindakan, nomor N).
 * Adapters only press the controls that already exist (same click path as
 * the mouse and keyboard), so validation stays on the server.
 */
import VoiceMode, { accessibleName, isUsable, normalize } from './engine';

const LANG = { indonesia: 'id-ID', inggris: 'en-US', mandarin: 'zh-CN' };
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const text = (el) => (el ? el.textContent.replace(/\s+/g, ' ').trim() : '');
const root = () => document.querySelector('[data-voice-page]');
const buttonNamed = (pattern) =>
    Array.from(root()?.querySelectorAll('button, a[href]') || []).find((b) => isUsable(b) && pattern.test(accessibleName(b)));

/** Text a screen reader would hear: aria-hidden parts (emoji, arrows) left out. */
const spoken = (el) => {
    if (!el) return '';
    const clone = el.cloneNode(true);
    clone.querySelectorAll('[aria-hidden="true"]').forEach((n) => n.remove());
    return clone.textContent.replace(/\s+/g, ' ').trim();
};

/** Hanzi read by a Mandarin voice, everything else in Indonesian ("Arti dari 你好?"). */
export function mixedSegments(value) {
    return String(value || '')
        .split(/([\p{Script=Han}，。？！、]+)/u)
        .filter((part) => part.trim())
        .map((part) => ({ text: part, lang: /\p{Script=Han}/u.test(part) ? 'zh-CN' : 'id-ID' }));
}

const ANGKA = { nol: 0, satu: 1, dua: 2, tiga: 3, empat: 4, lima: 5, enam: 6, tujuh: 7, delapan: 8, sembilan: 9, sepuluh: 10, sebelas: 11, seratus: 100 };

/** "12", "dua belas", "dua puluh tiga", "ketiga" → number; anything else → null. */
export function toNumber(value) {
    const t = normalize(value).replace(/^(nomor|soal|ke)\s*/, '').replace(/^ke/, '');
    if (/^\d+$/.test(t)) return Number(t);
    let total = 0;
    let current = 0;
    for (const word of t.split(' ')) {
        if (word in ANGKA) {
            const v = ANGKA[word];
            if (v >= 10) {
                total += current + v;
                current = 0;
            } else {
                current += v;
            }
        } else if (word === 'belas') {
            total += current + 10;
            current = 0;
        } else if (word === 'puluh') {
            total += current * 10;
            current = 0;
        } else if (word === 'ratus') {
            total += current * 100;
            current = 0;
        } else {
            return null;
        }
    }
    return total + current || null;
}

/** Sets a field the way typing does, so Livewire (wire:model) and Alpine pick it up. */
function fill(field, value) {
    field.value = value;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

/** Picks an <option> whose text matches, then fires change (wire:model.live on <select>). */
function choose(select, pattern) {
    const option = Array.from(select?.options || []).find((o) => pattern.test(o.textContent));
    if (!option) return null;
    fill(select, option.value);
    return option.textContent.trim();
}

/** Shared by the three games: read the question and its numbered options, answer through the existing radios + Jawab button. */
function gameAdapter({ route, question }) {
    const options = () => Array.from(root().querySelectorAll('fieldset input[type="radio"]'));

    const readQuestion = (engine) => {
        const segments = [];
        const q = question();
        if (q.hanzi) segments.push({ text: q.hanzi, lang: 'zh-CN' });
        if (q.arti) segments.push({ text: q.arti, lang: 'id-ID' });
        const legend = text(root().querySelector('fieldset legend'));
        if (legend) segments.push({ text: legend, lang: 'id-ID' });
        options().forEach((radio, i) => segments.push({ text: `${i + 1}. ${accessibleName(radio)}`, lang: 'id-ID' }));
        const feedback = text(root().querySelector('[role="status"]'));
        if (feedback) segments.push({ text: feedback, lang: 'id-ID' });
        if (!segments.length) segments.push({ text: text(root().querySelector('h1, h2')) || 'Tidak ada soal.', lang: 'id-ID' });
        engine.announce(segments.map((s) => s.text).join('. '));
        return engine.say(segments);
    };

    return {
        hint: 'mulai, ulangi, satu sampai empat, penjelasan, lanjut, kembali, berhenti',
        readPage: readQuestion,
        onActivate: () => readQuestion(VoiceMode),
        async handleTranscript(t, raw, engine) {
            if (t === 'penjelasan') {
                await engine.tell(text(root().querySelector('[role="status"]')) || 'Jawab dulu untuk melihat penjelasan.');
                return true;
            }
            return false;
        },
        async handleCommand(cmd, engine) {
            // The game starts on load: "mulai" reads the question, or presses "Main lagi" on the results.
            if (cmd.type === 'start' && (!cmd.target || cmd.target === route)) {
                const again = buttonNamed(/^main lagi$/i);
                if (again) {
                    again.click();
                    await wait(900);
                }
                await readQuestion(engine);
                return true;
            }
            if (cmd.type === 'repeat' || cmd.type === 'read_page') {
                await readQuestion(engine);
                return true;
            }
            if (cmd.type === 'choose_number' && options().length) {
                const radio = options()[cmd.index];
                if (!radio || radio.disabled) {
                    await engine.tell('Pilihan itu tidak ada.');
                    return true;
                }
                radio.click();
                await wait(200);
                buttonNamed(/^jawab$/i)?.click();
                await wait(900);
                await engine.tell(text(root().querySelector('[role="status"]')) || 'Jawaban dikirim.');
                return true;
            }
            if (cmd.type === 'more') {
                const next = buttonNamed(/soal berikutnya|berikutnya|lihat hasil|main lagi/i);
                if (next) {
                    next.click();
                    await wait(900);
                    await readQuestion(engine);
                    return true;
                }
            }
            return false;
        },
    };
}

/**
 * Kamus: "cari <kata>" types into the search box, results are read three at a
 * time ("lanjut" for more, pressing "Tampilkan lebih" when needed), "nomor N"
 * reads one result and plays it. Filters press the existing buttons.
 */
VoiceMode.register(
    'kamus',
    (() => {
        const PER_READ = 3;
        let shown = 0;
        const entries = () => Array.from(root().querySelectorAll('[data-hasil]'));
        const count = () => spoken(root().querySelector('[role="status"]'));

        const entrySegments = (el, n) => {
            const kata = text(el.querySelector('[data-kata]'));
            const lang = el.getAttribute('data-lang-code') || 'id-ID';
            return [
                { text: `${n}.`, lang: 'id-ID' },
                { text: kata, lang },
                { text: text(el.querySelector('[data-bacaan]')), lang: 'id-ID' },
                // The meaning line starts with a 🇮🇩/🇬🇧 flag: say the meaning, not "bendera".
                { text: spoken(el.querySelector('p.text-sm:not([data-kata])')).replace(/[\u{1F1E6}-\u{1F1FF}]/gu, '').trim(), lang: 'id-ID' },
            ];
        };

        const readFrom = async (engine, start) => {
            const all = entries();
            if (!all.length) {
                return engine.tell(spoken(root().querySelector('[data-kosong]')) || 'Ketik atau ucapkan cari, lalu katanya.');
            }
            if (start >= all.length) {
                const more = buttonNamed(/^tampilkan lebih$/i);
                if (!more) return engine.tell('Itu semua hasil. Katakan cari untuk kata lain.');
                more.click();
                await wait(1200);
                return readFrom(engine, start);
            }
            const group = all.slice(start, start + PER_READ);
            shown = start + group.length;
            const segments = [{ text: start === 0 ? `${count()}.` : '', lang: 'id-ID' }];
            group.forEach((el, i) => segments.push(...entrySegments(el, start + i + 1)));
            segments.push({ text: 'Katakan lanjut untuk hasil berikutnya, atau nomor hasil untuk mendengarnya.', lang: 'id-ID' });
            engine.announce(segments.map((s) => s.text).filter(Boolean).join(' '));
            return engine.say(segments);
        };

        const pressFilter = async (engine, label, pattern) => {
            const button = Array.from(root().querySelectorAll('[role="group"] button')).find((b) => pattern.test(text(b)));
            if (!button) return engine.tell(`Filter ${label} tidak ada di sini. Pilih bahasa Mandarin atau Inggris dulu untuk filter level.`);
            button.click();
            await wait(1200);
            shown = 0;
            return engine.tell(`Filter ${label}. ${count()}.`);
        };

        return {
            hint: 'cari lalu katanya, lanjut, ulangi, nomor hasil, bahasa mandarin, bahasa inggris, semua bahasa, level HSK satu sampai enam, kembali, berhenti',
            readPage: (engine) => readFrom(engine, 0),
            async handleTranscript(t, raw, engine) {
                const cari = raw.trim().match(/^cari\s+(.+)$/i);
                if (cari) {
                    fill(document.getElementById('cari-kamus'), cari[1].replace(/[.?!。？！]+$/u, ''));
                    await wait(1500);
                    shown = 0;
                    await readFrom(engine, 0);
                    return true;
                }
                const bahasa = t.match(/^(?:bahasa )?(mandarin|inggris|english|semua bahasa|semua)$/);
                if (bahasa) {
                    const label = { mandarin: 'Mandarin', inggris: 'English', english: 'English' }[bahasa[1]] || 'Semua';
                    await pressFilter(engine, `bahasa ${label}`, new RegExp(`^${label}$`, 'i'));
                    return true;
                }
                const hsk = t.match(/^(?:level )?hsk (.+)$/);
                if (hsk && toNumber(hsk[1])) {
                    await pressFilter(engine, `HSK ${toNumber(hsk[1])}`, new RegExp(`^HSK${toNumber(hsk[1])}$`, 'i'));
                    return true;
                }
                const cefr = t.match(/^level ([abc]) ?([12]|satu|dua)$/);
                if (cefr) {
                    const level = `${cefr[1]}${toNumber(cefr[2])}`.toUpperCase();
                    await pressFilter(engine, `level ${level}`, new RegExp(`^${level}$`, 'i'));
                    return true;
                }
                return false;
            },
            async handleCommand(cmd, engine) {
                if (cmd.type === 'more') {
                    await readFrom(engine, shown);
                    return true;
                }
                if (cmd.type === 'repeat' || cmd.type === 'read_page') {
                    await readFrom(engine, Math.max(0, shown - PER_READ));
                    return true;
                }
                if (cmd.type === 'choose_number') {
                    const el = entries()[cmd.index];
                    if (!el) {
                        await engine.tell('Nomor itu tidak ada di hasil.');
                        return true;
                    }
                    await engine.say(entrySegments(el, cmd.index + 1));
                    el.querySelector('button[aria-label^="Dengarkan"]')?.click();
                    return true;
                }
                return false;
            },
        };
    })(),
);

// Game hub and Ngobrol scenario picker: "mulai" is handled by the engine through their [data-voice-start] controls.
VoiceMode.register('game-hub', {
    hint: 'mulai, mulai dengar dan pilih, mulai pemburu kesalahan, satu atau dua, daftar tindakan, kembali, berhenti',
});

VoiceMode.register('chat-picker', {
    hint: 'mulai, lalu nomor skenario, daftar tindakan, kembali, berhenti',
});

VoiceMode.register('kata-bicara', {
    hint: 'cari, lalu katanya, dengarkan indonesia, dengarkan inggris, dengarkan mandarin, baca halaman, kembali, berhenti',
    async handleTranscript(t, raw, engine) {
        const cari = t.match(/^cari (.+)$/);
        if (cari) {
            const input = document.getElementById('cari-kata');
            input.value = cari[1];
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.form.requestSubmit();
            await wait(1000);
            const article = root().querySelector('article');
            if (!article) {
                await engine.tell(text(document.getElementById('hasil-kata-bicara')) || 'Belum ada hasil.');
                return true;
            }
            const values = Array.from(article.querySelectorAll('dd > span[lang]'));
            await engine.say([
                { text: text(values.find((v) => v.lang === 'id')), lang: 'id-ID' },
                { text: text(values.find((v) => v.lang === 'en')), lang: 'en-US' },
                { text: text(values.find((v) => v.lang === 'zh-Hans')), lang: 'zh-CN' },
            ]);
            return true;
        }
        const dengar = t.match(/^dengarkan (indonesia|inggris|mandarin)$/);
        if (dengar) {
            const lang = { indonesia: 'id', inggris: 'en', mandarin: 'zh-Hans' }[dengar[1]];
            const value = root().querySelector(`article dd > span[lang="${lang}"]`);
            await engine.say([{ text: text(value) || 'Cari kata dulu.', lang: value ? LANG[dengar[1]] : 'id-ID' }]);
            return true;
        }
        return false;
    },
});

VoiceMode.register(
    'game-listen-pick',
    gameAdapter({ route: 'game.listen-pick', question: () => ({ hanzi: text(root().querySelector('#judul-soal ~ p[lang="zh-Hans"]')) }) }),
);
VoiceMode.register(
    'game-error-hunter',
    gameAdapter({
        route: 'game.error-hunter',
        question: () => ({
            hanzi: text(root().querySelector('#judul-soal ~ p[lang="zh-Hans"]')),
            arti: text(root().querySelector('#judul-soal ~ p[lang="zh-Hans"] + p')),
        }),
    }),
);

VoiceMode.register('chat', {
    hint: 'selesai, ulangi, baca halaman, kembali, berhenti. Ucapan lain dikirim sebagai pesan',
    onActivate() {
        // Read every new AI reply, then the engine reopens the microphone.
        if (this.terpasang) return;
        this.terpasang = true;
        window.addEventListener('ai-replied', (event) => {
            if (!VoiceMode.active) return;
            // Wait for Livewire to render the new reply, then read it (reply in its own language, translation in Indonesian).
            setTimeout(async () => {
                const translations = Array.from(document.querySelectorAll('#conversation-scroll span[lang="id"]'));
                const langCode = event.detail?.langCode || 'zh-CN';
                VoiceMode.stopListening();
                await VoiceMode.say([
                    { text: event.detail?.text || '', lang: langCode },
                    { text: langCode.startsWith('zh') ? text(translations.pop()) : '', lang: 'id-ID' },
                ]);
                VoiceMode.listen();
            }, 400);
        });
    },
    async handleTranscript(t, raw, engine) {
        if (t === 'selesai') {
            const finish = buttonNamed(/^selesai dan analisis$/i);
            if (!finish) {
                await engine.tell('Sesi ini sudah selesai.');
                return true;
            }
            engine.pending = {
                onYes: async () => {
                    finish.click();
                    await wait(300);
                    buttonNamed(/^ya, selesaikan$/i)?.click();
                    await engine.tell('Sesi diselesaikan. Analisis sedang diproses.');
                    await wait(6000);
                    const analysis = Array.from(root().querySelectorAll('h2')).find((h) => /analisis kesalahan/i.test(h.textContent));
                    if (analysis) await engine.tell(text(analysis.parentElement));
                },
                onNo: () => engine.tell('Dibatalkan.'),
            };
            await engine.tell('Yakin? Katakan ya atau tidak');
            return true;
        }
        return false;
    },
    async handleUnknown(raw, engine) {
        // Anything that isn't a command is the learner's message, sent through the existing form.
        const field = document.getElementById('kolom-tulis');
        if (!field || field.disabled) return false;
        field.value = raw.trim();
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.form.requestSubmit();
        await engine.tell('Pesan dikirim.');
        return true;
    },
});

VoiceMode.register('cards', {
    hint: 'balik, berikutnya, sebelumnya, dengarkan, baca halaman, daftar tindakan, kembali, berhenti',
    async handleTranscript(t, raw, engine) {
        if (t === 'balik') {
            root().querySelector('.flip-card-inner[role="button"]')?.click();
            await wait(700);
            const face = Array.from(root().querySelectorAll('.flip-card-front, .flip-card-back')).find((f) => !f.hasAttribute('aria-hidden'));
            await engine.tell(text(face) || 'Kartu dibalik.');
            return true;
        }
        if (t === 'berikutnya' || t === 'sebelumnya') {
            buttonNamed(t === 'berikutnya' ? /berikutnya/i : /sebelumnya/i)?.click();
            await wait(700);
            await engine.tell(text(root().querySelector('.flip-card-front')) || 'Kartu berikutnya.');
            return true;
        }
        if (t === 'dengarkan') {
            buttonNamed(/^dengarkan/i)?.click();
            return true;
        }
        return false;
    },
});

VoiceMode.register('dashboard', {
    hint: 'baca halaman, daftar tindakan, satu sampai dua belas, lanjut, kembali, berhenti',
    readPage(engine) {
        const h1 = text(root().querySelector('h1'));
        const tasks = Array.from(root().querySelectorAll('[aria-label^="Tandai selesai:"]')).map((b, i) => {
            const title = b.getAttribute('aria-label').replace('Tandai selesai: ', '');
            return `${i + 1}. ${title}${b.getAttribute('aria-pressed') === 'true' ? ', sudah selesai' : ''}`;
        });
        return engine.tell(`${h1}. ${tasks.length ? `Tugas hari ini: ${tasks.join('. ')}` : 'Tidak ada kegiatan untuk hari ini.'}`);
    },
});

VoiceMode.register('progress', {
    hint: 'baca halaman, daftar tindakan, satu sampai dua belas, kembali, berhenti',
    readPage(engine) {
        const intro = text(root().querySelector('h1 + p'));
        const heading = Array.from(root().querySelectorAll('h2')).find((h) => /kesalahan terbanyak/i.test(h.textContent));
        const box = heading?.nextElementSibling;
        const top = Array.from(box?.querySelectorAll('p.font-700') || []).slice(0, 3).map(text);
        const errors = top.length ? `Tiga kesalahan teratas: ${top.join(', ')}.` : text(box?.querySelector('p'));
        return engine.tell(`Progress. ${intro}. ${errors}`);
    },
});

VoiceMode.register('scan', {
    hint: 'ambil foto, baca halaman, daftar tindakan, kembali, berhenti',
    async handleTranscript(t, raw, engine) {
        if (t === 'ambil foto' || t === 'foto') {
            // Browsers don't let a voice command open the file picker or camera; a key press must.
            buttonNamed(/ambil foto/i)?.focus();
            await engine.tell('Tekan Enter untuk membuka kamera');
            return true;
        }
        return false;
    },
});

export { accessibleName, normalize };
