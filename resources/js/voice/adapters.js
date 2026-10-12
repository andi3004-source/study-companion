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

/** Shared by the three games: read the question and its numbered options, answer through the existing radios + Jawab button. */
function gameAdapter({ question }) {
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

VoiceMode.register('kata-bicara', {
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
    gameAdapter({ question: () => ({ hanzi: text(root().querySelector('#judul-soal ~ p[lang="zh-Hans"]')) }) }),
);
VoiceMode.register(
    'game-error-hunter',
    gameAdapter({
        question: () => ({
            hanzi: text(root().querySelector('#judul-soal ~ p[lang="zh-Hans"]')),
            arti: text(root().querySelector('#judul-soal ~ p[lang="zh-Hans"] + p')),
        }),
    }),
);

VoiceMode.register('chat', {
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
