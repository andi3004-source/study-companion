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

/** Ujian, choosing a mode: "mulai" lists the modes ([data-voice-start]); a mode name opens it. */
VoiceMode.register(
    'ujian-picker',
    (() => {
        const MODES = { 'tes hari ini': /^tes hari ini$/i, 'hari ini': /^tes hari ini$/i, mandarin: /mandarin$/i, inggris: /english$/i, english: /english$/i };
        return {
            hint: 'mulai, tes hari ini, mandarin, inggris, riwayat, kembali, berhenti',
            async handleTranscript(t, raw, engine) {
                const mode = Object.keys(MODES).find((m) => t === m || t === `ujian ${m}`);
                if (mode) {
                    const link = Array.from(root().querySelectorAll('[data-voice-start]')).find((a) => MODES[mode].test(a.getAttribute('data-voice-start')));
                    if (!link) {
                        await engine.tell('Mode itu tidak tersedia hari ini.');
                        return true;
                    }
                    await engine.tell(`Membuka ${link.getAttribute('data-voice-start')}.`);
                    link.click();
                    return true;
                }
                if (t === 'riwayat') {
                    const rows = Array.from(root().querySelectorAll('[data-riwayat] > div')).map(spoken);
                    await engine.tell(rows.length ? `Riwayat terakhir: ${rows.join('. ')}.` : 'Belum ada riwayat ujian.');
                    return true;
                }
                return false;
            },
        };
    })(),
);

/**
 * Ujian, answering: one question at a time ("soal N", "lanjut", "sebelumnya"),
 * "satu" to "empat" presses that option, then the next unanswered question is
 * read. Same rules as the mouse: answers can change until "kumpulkan", which
 * always asks first; after that the options are only read, with the right answer.
 */
VoiceMode.register(
    'ujian-run',
    (() => {
        let current = 0;
        const questions = () => Array.from(root().querySelectorAll('[data-soal]'));
        const optionsOf = (q) => Array.from(q?.querySelectorAll('button[aria-pressed]') || []);
        const answered = (q) => optionsOf(q).some((o) => o.getAttribute('aria-pressed') === 'true');
        const submitted = () => !!root().querySelector('[data-skor]');
        const firstUnanswered = (from = 0) => {
            const all = questions();
            const i = all.findIndex((q, n) => n >= from && !answered(q));
            return i === -1 ? all.findIndex((q) => !answered(q)) : i;
        };

        const readQuestion = (engine, index) => {
            const all = questions();
            if (!all.length) return engine.tell(text(root().querySelector('h1')) + '. Tidak ada soal.');
            current = Math.max(0, Math.min(index, all.length - 1));
            const q = all[current];
            const segments = [{ text: `Soal ${current + 1} dari ${all.length}.`, lang: 'id-ID' }, ...mixedSegments(text(q.querySelector('[data-pertanyaan]')))];
            optionsOf(q).forEach((o, i) => {
                const pressed = o.getAttribute('aria-pressed') === 'true' ? ', terpilih' : '';
                segments.push({ text: `${i + 1}.`, lang: 'id-ID' }, ...mixedSegments(accessibleName(o) + pressed));
            });
            if (!submitted()) segments.push({ text: 'Katakan nomor jawaban.', lang: 'id-ID' });
            engine.announce(segments.map((s) => s.text).join(' '));
            return engine.say(segments);
        };

        const answer = async (engine, index) => {
            if (submitted()) return engine.tell('Ujian sudah dikumpulkan, jawaban tidak bisa diubah. Katakan coba lagi untuk ujian baru.');
            const option = optionsOf(questions()[current])[index];
            if (!option) return engine.tell('Pilihan itu tidak ada.');
            option.click();
            await wait(700);
            const next = firstUnanswered(current + 1);
            if (next === -1) return engine.tell(`Jawaban ${index + 1} dipilih. Semua soal sudah dijawab. Katakan kumpulkan untuk mengumpulkan jawaban.`);
            await engine.tell(`Jawaban ${index + 1} dipilih.`);
            return readQuestion(engine, next);
        };

        const collect = (engine) => {
            const submit = buttonNamed(/^kumpulkan jawaban$/i);
            if (!submit) return engine.tell('Ujian sudah dikumpulkan.');
            const open = questions().filter((q) => !answered(q)).length;
            engine.pending = {
                onYes: async () => {
                    submit.click();
                    await wait(1200);
                    current = 0;
                    await engine.tell(`${spoken(root().querySelector('[data-skor]'))}. Katakan soal satu untuk memeriksa jawaban, atau coba lagi.`);
                },
                onNo: () => engine.tell('Belum dikumpulkan.'),
            };
            return engine.tell(`${open ? `${open} soal belum dijawab. ` : ''}Kumpulkan jawaban sekarang? Katakan ya atau tidak`);
        };

        return {
            hint: 'satu sampai empat untuk menjawab, lanjut, sebelumnya, soal lalu nomornya, belum dijawab, ulangi, kumpulkan, coba lagi, kembali, berhenti',
            onActivate: () => {
                const first = firstUnanswered();
                return readQuestion(VoiceMode, first === -1 ? 0 : first);
            },
            readPage: (engine) => readQuestion(engine, current),
            async handleTranscript(t, raw, engine) {
                const soal = t.match(/^soal (.+)$/);
                if (soal && toNumber(soal[1])) {
                    await readQuestion(engine, toNumber(soal[1]) - 1);
                    return true;
                }
                if (t === 'sebelumnya' || t === 'soal sebelumnya') {
                    await readQuestion(engine, current - 1);
                    return true;
                }
                if (t === 'kumpulkan' || t === 'kumpulkan jawaban') {
                    await collect(engine);
                    return true;
                }
                if (t === 'belum dijawab') {
                    const open = questions().map((q, i) => (answered(q) ? null : i + 1)).filter(Boolean);
                    await engine.tell(open.length ? `Belum dijawab: soal ${open.join(', ')}.` : 'Semua soal sudah dijawab.');
                    return true;
                }
                if (t === 'coba lagi') {
                    const retry = submitted() ? buttonNamed(/^ulangi$/i) : null;
                    if (!retry) {
                        await engine.tell('Coba lagi tersedia setelah jawaban dikumpulkan.');
                        return true;
                    }
                    retry.click();
                    await wait(1200);
                    await readQuestion(engine, 0);
                    return true;
                }
                return false;
            },
            async handleCommand(cmd, engine) {
                if (cmd.type === 'choose_number') {
                    await answer(engine, cmd.index);
                    return true;
                }
                if (cmd.type === 'more') {
                    await readQuestion(engine, current + 1);
                    return true;
                }
                if (cmd.type === 'repeat' || cmd.type === 'read_page' || cmd.type === 'start') {
                    await readQuestion(engine, current);
                    return true;
                }
                return false;
            },
        };
    })(),
);

/**
 * Latihan Isian: one question at a time. "isi <pinyin>" fills the pinyin field
 * with the spoken text; "isi hanzi" listens to the next answer in Mandarin
 * (zh-CN), so recognition writes hanzi. Answers are still graded by the server
 * ("analisis"); "reset" asks first, like the button.
 */
VoiceMode.register(
    'latihan-isian',
    (() => {
        let current = 0;
        const FIELD = { hanzi: { label: 'Hanzi', lang: 'zh-CN' }, pinyin: { label: 'Pinyin', lang: 'id-ID' } };
        const rows = () => Array.from(root().querySelectorAll('[data-soal]'));
        const fieldsOf = (row) => Array.from(row?.querySelectorAll('[data-isian]') || []);
        const promptLabel = () => text(root().querySelectorAll('thead th')[1]) || 'Petunjuk';
        const clean = (value) => String(value).replace(/[.,!?。，！？]+/gu, ' ').replace(/\s+/g, ' ').trim();

        const readQuestion = (engine, index) => {
            const all = rows();
            if (!all.length) return engine.tell('Tidak ada kata untuk dilatih.');
            current = Math.max(0, Math.min(index, all.length - 1));
            const row = all[current];
            const segments = [
                { text: `Soal ${current + 1} dari ${all.length}. ${promptLabel()}:`, lang: 'id-ID' },
                ...mixedSegments(text(row.querySelector('[data-petunjuk]'))),
            ];
            fieldsOf(row).forEach((field) => {
                const label = FIELD[field.getAttribute('data-isian')]?.label || 'Jawaban';
                segments.push({ text: `${label}:`, lang: 'id-ID' }, ...(field.value ? mixedSegments(field.value) : [{ text: 'kosong', lang: 'id-ID' }]));
            });
            const result = spoken(row.querySelector('[data-hasil-soal]'));
            if (result && result !== '—') segments.push({ text: `Hasil: ${result}`, lang: 'id-ID' });
            engine.announce(segments.map((s) => s.text).join(' '));
            return engine.say(segments);
        };

        const fieldNamed = (name) => fieldsOf(rows()[current]).find((f) => f.getAttribute('data-isian') === name);

        const fillField = (engine, field, value) => {
            const name = field.getAttribute('data-isian');
            fill(field, clean(value));
            return engine.say([
                { text: `${FIELD[name].label} soal ${current + 1}:`, lang: 'id-ID' },
                ...mixedSegments(field.value),
                { text: 'Katakan lanjut untuk soal berikutnya.', lang: 'id-ID' },
            ]);
        };

        const askFor = (engine, field) => {
            const name = field.getAttribute('data-isian');
            engine.captureNext(FIELD[name].lang, (heard) => fillField(engine, field, heard));
            return engine.tell(name === 'hanzi' ? 'Ucapkan jawabannya dalam bahasa Mandarin.' : 'Ucapkan pinyinnya.');
        };

        const setting = async (engine, id, pattern, label) => {
            const chosen = choose(document.getElementById(id), pattern);
            if (!chosen) return engine.tell(`Pilihan ${label} itu tidak ada.`);
            await wait(1500);
            current = 0;
            await engine.tell(`${label}: ${chosen}.`);
            return readQuestion(engine, 0);
        };

        return {
            hint: 'isi lalu pinyinnya, isi hanzi, lanjut, sebelumnya, soal lalu nomornya, ulangi, analisis, reset, mode hanzi, mode pinyin, mode arti, jumlah, urutan acak, kembali, berhenti',
            onActivate: () => readQuestion(VoiceMode, current),
            readPage: (engine) => readQuestion(engine, current),
            async handleTranscript(t, raw, engine) {
                const soal = t.match(/^soal (.+)$/);
                if (soal && toNumber(soal[1])) {
                    await readQuestion(engine, toNumber(soal[1]) - 1);
                    return true;
                }
                if (t === 'sebelumnya' || t === 'soal sebelumnya') {
                    await readQuestion(engine, current - 1);
                    return true;
                }

                const isi = raw.trim().match(/^(?:isi|jawab)(?:\s+(hanzi|pinyin))?(?:\s+(.+))?$/i);
                if (isi) {
                    const fields = fieldsOf(rows()[current]);
                    const named = isi[1] ? fieldNamed(isi[1].toLowerCase()) : fields.length === 1 ? fields[0] : null;
                    if (isi[1] && !named) {
                        await engine.tell(`Soal ini tidak meminta ${isi[1].toLowerCase()}.`);
                    } else if (!named) {
                        await engine.tell('Soal ini meminta hanzi dan pinyin. Katakan isi hanzi atau isi pinyin.');
                    } else if (isi[2] && named.getAttribute('data-isian') === 'pinyin') {
                        await fillField(engine, named, isi[2]);
                    } else {
                        await askFor(engine, named);
                    }
                    return true;
                }

                if (t === 'analisis' || t === 'periksa' || t === 'analisis jawaban') {
                    buttonNamed(/analisis jawaban/i)?.click();
                    await wait(1500);
                    current = 0;
                    await engine.tell(`${text(root().querySelector('p[role="status"]')) || 'Jawaban dianalisis.'} Katakan soal satu untuk mendengar hasil per soal.`);
                    return true;
                }
                if (t === 'reset' || t === 'reset isian') {
                    const reset = buttonNamed(/^reset isian$/i);
                    if (reset) await engine.activate(reset);
                    return true;
                }

                const mode = t.match(/^mode (hanzi|pinyin|arti)$/);
                if (mode) {
                    await setting(engine, 'isian-mode', { hanzi: /^tebak hanzi \(/i, pinyin: /^tebak pinyin/i, arti: /petunjuk: arti/i }[mode[1]], 'Mode soal');
                    return true;
                }
                const jumlah = t.match(/^jumlah (.+)$/);
                if (jumlah) {
                    const n = jumlah[1] === 'semua' ? null : toNumber(jumlah[1]);
                    await setting(engine, 'isian-jumlah', n ? new RegExp(`^${n}$`) : /^semua/i, 'Jumlah soal');
                    return true;
                }
                const urutan = t.match(/^urutan (acak|berurutan)$/);
                if (urutan) {
                    await setting(engine, 'isian-urutan', urutan[1] === 'acak' ? /^acak$/i : /^berurutan/i, 'Urutan');
                    return true;
                }
                if (t === 'pengaturan') {
                    const values = ['isian-mode', 'isian-jumlah', 'isian-urutan'].map((id) => {
                        const select = document.getElementById(id);
                        return `${accessibleName(select)}: ${select?.selectedOptions[0]?.textContent.trim() || '-'}`;
                    });
                    await engine.tell(`${values.join('. ')}.`);
                    return true;
                }
                return false;
            },
            async handleCommand(cmd, engine) {
                if (cmd.type === 'more') {
                    await readQuestion(engine, current + 1);
                    return true;
                }
                if (cmd.type === 'repeat' || cmd.type === 'read_page' || cmd.type === 'start') {
                    await readQuestion(engine, current);
                    return true;
                }
                return false;
            },
        };
    })(),
);

/**
 * Cek Ucapan: "cari <kata>" lists suggestions, "nomor N" picks one, "rekam"
 * presses the existing "Rekam suara" button, records for a few seconds, presses
 * it again ("Berhenti merekam") and reads the result. Mode suara's own
 * microphone stays closed meanwhile, so it never competes with the recorder.
 * The server still judges the pronunciation.
 */
VoiceMode.register(
    'ucapan',
    (() => {
        const RECORD_MS = 3500;
        const suggestions = () => Array.from(root().querySelectorAll('[data-saran]'));
        const selected = () => root().querySelector('[data-kata-terpilih]');
        const resultText = () => spoken(root().querySelector('div[role="status"]'));

        const readSelected = (engine) => {
            const box = selected();
            if (!box) return engine.tell('Belum ada kata dipilih. Katakan cari, lalu katanya.');
            const word = text(box.querySelector('p'));
            const lang = /[\p{Script=Han}]/u.test(word) ? 'zh-CN' : 'en-US';
            const details = Array.from(box.querySelectorAll('p')).slice(1).map(spoken).filter(Boolean);
            return engine.say([
                { text: 'Kata target:', lang: 'id-ID' },
                { text: word, lang },
                { text: `${details.join('. ')}. Katakan rekam untuk mulai, atau dengarkan untuk contoh.`, lang: 'id-ID' },
            ]);
        };

        const record = async (engine) => {
            const button = buttonNamed(/rekam suara/i);
            if (!button) {
                return engine.tell(selected() ? 'Hasil sudah ada. Katakan coba lagi untuk merekam ulang.' : 'Pilih kata dulu. Katakan cari, lalu katanya.');
            }
            await engine.tell('Setelah bunyi, ucapkan katanya. Rekaman sekitar tiga detik.');
            await engine.beep();
            button.click();
            await wait(RECORD_MS);
            buttonNamed(/berhenti merekam/i)?.click();
            for (let waited = 0; waited < 20000 && !resultText(); waited += 500) await wait(500);
            const error = text(root().querySelector('[role="alert"]'));
            return engine.tell(`${resultText() || error || 'Hasil belum keluar. Coba lagi.'} Katakan coba lagi, dengarkan, atau ganti kata.`);
        };

        return {
            hint: 'cari lalu katanya, nomor saran, rekam, dengarkan, coba lagi, ganti kata, bahasa mandarin, bahasa inggris, kembali, berhenti',
            readPage: (engine) => readSelected(engine),
            async handleTranscript(t, raw, engine) {
                const cari = raw.trim().match(/^cari\s+(.+)$/i);
                if (cari) {
                    if (!document.getElementById('ucapan-cari')) buttonNamed(/ganti kata|cari kata lain/i)?.click();
                    await wait(600);
                    const field = document.getElementById('ucapan-cari');
                    if (!field) return false;
                    fill(field, cari[1].replace(/[.?!。？！]+$/u, ''));
                    await wait(1500);
                    const list = suggestions();
                    if (!list.length) {
                        await engine.tell('Kata tidak ditemukan. Coba kata lain.');
                        return true;
                    }
                    const segments = [{ text: `Ada ${list.length} saran.`, lang: 'id-ID' }];
                    list.slice(0, 5).forEach((b, i) => {
                        segments.push({ text: `${i + 1}.`, lang: 'id-ID' }, ...mixedSegments(spoken(b)));
                    });
                    segments.push({ text: 'Katakan nomor kata yang mau dilatih.', lang: 'id-ID' });
                    await engine.say(segments);
                    return true;
                }
                const bahasa = t.match(/^bahasa (mandarin|inggris|english)$/);
                if (bahasa) {
                    const chosen = choose(document.getElementById('ucapan-bahasa'), bahasa[1] === 'mandarin' ? /^mandarin$/i : /^english$/i);
                    await wait(1000);
                    await engine.tell(chosen ? `Bahasa ${chosen}. Katakan cari, lalu katanya.` : 'Pilihan bahasa tidak ada.');
                    return true;
                }
                if (t === 'rekam' || t === 'mulai rekam') {
                    await record(engine);
                    return true;
                }
                if (t === 'dengarkan' || t === 'contoh' || t === 'dengarkan contoh') {
                    const speak = root().querySelector('button[aria-label^="Dengarkan cara ucap"], button[aria-label^="Dengarkan target"]');
                    if (!speak) {
                        await engine.tell('Pilih kata dulu.');
                        return true;
                    }
                    engine.stopListening();
                    speak.click();
                    await wait(1500);
                    return true;
                }
                if (t === 'coba lagi') {
                    const again = buttonNamed(/coba lagi/i);
                    if (!again) return false;
                    again.click();
                    await wait(1000);
                    await engine.tell('Siap merekam ulang. Katakan rekam.');
                    return true;
                }
                if (t === 'ganti kata' || t === 'cari kata lain') {
                    buttonNamed(/ganti kata|cari kata lain/i)?.click();
                    await wait(1000);
                    await engine.tell('Katakan cari, lalu katanya.');
                    return true;
                }
                return false;
            },
            async handleCommand(cmd, engine) {
                if (cmd.type === 'choose_number' && suggestions().length) {
                    const pick = suggestions()[cmd.index];
                    if (!pick) {
                        await engine.tell('Nomor itu tidak ada di saran.');
                        return true;
                    }
                    pick.click();
                    await wait(1200);
                    await readSelected(engine);
                    return true;
                }
                if (cmd.type === 'repeat' || cmd.type === 'read_page') {
                    await readSelected(engine);
                    return true;
                }
                if (cmd.type === 'start') {
                    await record(engine);
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
