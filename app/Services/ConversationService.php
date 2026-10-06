<?php

namespace App\Services;

use App\Contracts\AiClient;
use App\Contracts\SessionAnalyzer;
use App\Jobs\AnalyzeSessionJob;
use App\Models\ConversationApiCall;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\User;
use App\Models\WeekTopic;
use App\Support\HskVocabularyPrompt;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orchestrates the AI Conversation Partner: scenario lookup, session
 * lifecycle, the daily message quota, and turning stored turns into a
 * bounded LLM request. All calls go through the bound AiClient (see
 * AppServiceProvider), whose key never leaves the backend.
 */
class ConversationService
{
    public const DAILY_MESSAGE_LIMIT = 50;

    private const HISTORY_WINDOW = 10;

    /**
     * Appended to every scenario's system_prompt_template so the
     * correction-without-breaking-character behavior can't drift per scenario
     * (applied unconditionally in systemPromptFor() — scripted and "Ngobrol
     * Bebas" both go through the same call, so this can't drift between them
     * either). The correction explanation is required to be in Indonesian
     * specifically because it's aimed at beginners: a correction phrased only
     * in the target language (Mandarin/English) doesn't help someone who
     * doesn't understand that language well enough to have made the mistake
     * in the first place.
     */
    private const BAKU_INSTRUCTION = "Balas natural sesuai skenario, tetap dalam karakter — jangan interupsi jadi mode 'guru'. Kalau user salah ucap/tulis kata atau grammar, sisipkan koreksi singkat (1-2 kalimat) DALAM BAHASA INDONESIA yang menyebutkan: (1) kata/kalimat yang salah dari user, (2) kata/kalimat yang benar, (3) arti dari kata yang benar itu — dan kalau kata yang salah tadi ternyata kata lain yang beda arti sama sekali (bukan cuma typo/salah ucap kecil), sebutkan juga artinya supaya user sadar itu beda kata, bukan cuma kurang tepat. Contoh gaya koreksi: \"Ngomong-ngomong, kamu bilang '你号' tadi — itu artinya 'nomor kamu', beda sama '你好' (halo) yang kayaknya kamu maksud. Coba diulang: 你好!\". Setelah koreksi, lanjutkan percakapan seperti biasa. Kalau user TIDAK bikin kesalahan di giliran ini, JANGAN cari-cari kesalahan buat dikoreksi — lanjut ngobrol natural aja. Jawaban maksimal 3-4 kalimat total (termasuk koreksi kalau ada).";

    /**
     * Appended only for Mandarin sessions so every assistant reply carries
     * its own Indonesian translation — one API call, not two. The marker is
     * parsed back out in parseAssistantReply(); if the model doesn't follow
     * the format, the whole reply is just used as-is with no translation
     * rather than showing garbage.
     */
    private const MANDARIN_TRANSLATION_INSTRUCTION = "PENTING: Setelah balasanmu dalam Mandarin, tulis baris baru berisi PERSIS '###TERJEMAHAN_ID###', lalu di baris berikutnya tulis HANYA terjemahan Indonesia dari balasanmu itu, tanpa tambahan penjelasan apapun.";

    private const MANDARIN_TRANSLATION_MARKER = '###TERJEMAHAN_ID###';

    public function __construct(private readonly AiClient $client) {}

    /** @return array<string, array{label: string, target_lang_code: string, system_prompt_template: string}> */
    public function scenarios(string $lang): array
    {
        return config("scenarios.{$lang}", []);
    }

    public function scenario(string $lang, string $scenarioKey): array
    {
        return config("scenarios.{$lang}.{$scenarioKey}")
            ?? throw new InvalidArgumentException("Unknown scenario: {$lang}/{$scenarioKey}");
    }

    public function startSession(User $user, string $lang, string $scenarioKey): ConversationSession
    {
        $this->scenario($lang, $scenarioKey); // throws if unknown

        return ConversationSession::create([
            'user_id' => $user->id,
            'lang' => $lang,
            'scenario_key' => $scenarioKey,
            'started_at' => now(),
            'last_message_at' => now(),
        ]);
    }

    /**
     * "Ngobrol Bebas": picks a random topic (or uses $topicOverride, when the
     * dashboard's checklist detail modal already knows the day's topic and
     * wants that instead of a random one — see startSessionForTopic()),
     * creates the session, and has Claude open with the first message — the
     * user never has to type first. The opener isn't a scripted string; it's
     * the same system-prompt + Claude-call path as every other reply, just
     * seeded with a one-off instruction that's never stored as a real user
     * turn (so it never counts against the daily quota).
     */
    public function startRandomSession(User $user, string $lang, ?string $topicOverride = null): ConversationSession
    {
        $topic = $topicOverride ?? $this->randomTopic($lang);

        $session = ConversationSession::create([
            'user_id' => $user->id,
            'lang' => $lang,
            'scenario_key' => 'random',
            'topic_label' => $topic,
            'started_at' => now(),
            'last_message_at' => now(),
        ]);

        $result = $this->client->sendMessage(
            model: config('services.active_ai_model'),
            system: $this->systemPromptFor($session),
            messages: [['role' => 'user', 'content' => 'Mulai obrolan santai duluan dengan 1 pertanyaan pembuka singkat terkait topik ini.']],
            maxTokens: $this->maxTokensFor($session),
        );

        $parsed = $this->parseAssistantReply($session, $result['text']);

        ConversationTurn::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $parsed['content'],
            'translation' => $parsed['translation'],
        ]);

        ConversationApiCall::create([
            'session_id' => $session->id,
            'tokens_in' => $result['tokens_in'],
            'tokens_out' => $result['tokens_out'],
        ]);

        return $session;
    }

    /**
     * Entry point for the dashboard checklist's "Latihan Ngobrol" button
     * (a "speaking" item already carries its day's topic): reuses a scripted
     * scenario when its persona genuinely matches the topic, otherwise
     * starts "Ngobrol Bebas" with that topic assigned directly instead of a
     * random one. In practice scripted scenarios are situational roleplay
     * (job interview, hotel check-in, ...) while checklist topics are
     * subject areas (Family & Hometown, Technology, ...) — different axes
     * that rarely overlap — so this almost always takes the random-mode
     * branch, which is the honest behavior given the data, not a fallback
     * that's failing.
     */
    public function startSessionForTopic(User $user, string $lang, ?string $topic): ConversationSession
    {
        $scenarioKey = $topic ? $this->matchingScenarioKey($lang, $topic) : null;

        return $scenarioKey
            ? $this->startSession($user, $lang, $scenarioKey)
            : $this->startRandomSession($user, $lang, $topic);
    }

    /** Word-overlap match between the topic and a scenario's label — see startSessionForTopic(). */
    private function matchingScenarioKey(string $lang, string $topic): ?string
    {
        $topicWords = collect(preg_split('/[^a-zA-Z]+/', strtolower($topic)))->filter();

        foreach ($this->scenarios($lang) as $key => $scenario) {
            $labelWords = collect(preg_split('/[^a-zA-Z]+/', strtolower($scenario['label'])))->filter();

            if ($topicWords->intersect($labelWords)->isNotEmpty()) {
                return $key;
            }
        }

        return null;
    }

    private function randomTopic(string $lang): string
    {
        if ($lang === 'mandarin') {
            $topics = config('conversation_topics.mandarin');
            $topic = $topics[array_rand($topics)];

            return "{$topic['hanzi']} ({$topic['meaning_id']})";
        }

        // week_number 9 is "Review Semua Topik", not a real speaking topic.
        return WeekTopic::where('week_number', '<=', 8)->inRandomOrder()->value('topic_name');
    }

    /**
     * Normalized label + TTS lang code for display, regardless of whether
     * the session is a scripted scenario or a random-topic chat.
     *
     * @return array{label: string, target_lang_code: string}
     */
    public function scenarioDisplay(ConversationSession $session): array
    {
        if ($session->scenario_key === 'random') {
            return [
                'label' => '🎲 Ngobrol Bebas: '.$session->topic_label,
                'target_lang_code' => $session->lang === 'mandarin' ? 'zh-CN' : 'en-US',
            ];
        }

        return $this->scenario($session->lang, $session->scenario_key);
    }

    public function messagesSentToday(User $user): int
    {
        return ConversationTurn::where('role', 'user')
            ->whereHas('session', fn ($q) => $q->where('user_id', $user->id))
            ->whereDate('created_at', today())
            ->count();
    }

    public function remainingMessagesToday(User $user): int
    {
        return max(0, self::DAILY_MESSAGE_LIMIT - $this->messagesSentToday($user));
    }

    /**
     * Persists the user's turn, calls Claude with only the last 10 turns of
     * this session as context, persists the assistant's reply, and logs the
     * call's token usage. Caller is responsible for validation and the rate
     * limit check — this method always calls the API if invoked. $inputMode
     * ('text'|'voice') is recorded on the user turn only.
     */
    public function sendMessage(ConversationSession $session, string $content, string $inputMode = 'text'): ConversationTurn
    {
        ConversationTurn::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $content,
            'input_mode' => $inputMode,
        ]);

        $result = $this->client->sendMessage(
            model: config('services.active_ai_model'),
            system: $this->systemPromptFor($session),
            messages: $this->recentMessages($session),
            maxTokens: $this->maxTokensFor($session),
        );

        $parsed = $this->parseAssistantReply($session, $result['text']);

        $assistantTurn = ConversationTurn::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $parsed['content'],
            'translation' => $parsed['translation'],
            'input_mode' => 'text',
        ]);

        ConversationApiCall::create([
            'session_id' => $session->id,
            'tokens_in' => $result['tokens_in'],
            'tokens_out' => $result['tokens_out'],
        ]);

        $session->update(['last_message_at' => now()]);

        return $assistantTurn;
    }

    /**
     * Marks the session finished and, when the API is available, asks Claude
     * for 2-3 short feedback notes on the transcript. Feedback is best-effort:
     * an API failure still ends the session, just without notes. Mandarin
     * sessions then get their error analysis queued (see queueErrorAnalysis).
     *
     * @return array{exchange_count: int, notes: ?string, analysis_queued: bool}
     */
    public function endSession(ConversationSession $session): array
    {
        $exchangeCount = ConversationTurn::where('session_id', $session->id)->where('role', 'user')->count();

        $notes = null;

        if ($this->client->isConfigured() && $exchangeCount > 0) {
            try {
                $notes = $this->generateFeedback($session);
            } catch (RuntimeException $e) {
                report($e);
            }
        }

        $session->update(['ended_at' => now(), 'summary' => $notes]);

        return [
            'exchange_count' => $exchangeCount,
            'notes' => $notes,
            'analysis_queued' => $this->queueErrorAnalysis($session, $exchangeCount),
        ];
    }

    /**
     * Queues the "Kesalahan terbanyakmu" analysis (AnalyzeSessionJob) for a
     * finished Mandarin session with at least one user turn — never run
     * inline in this request. With QUEUE_CONNECTION=sync it's deferred until
     * after the response is sent, so finishing a session never waits on the
     * AI call. Skipped when no analyzer is configured at all (no Langflow and
     * no AI key), so the UI doesn't promise an analysis that can't happen. A
     * dispatch failure is reported and never stops the session from ending.
     */
    private function queueErrorAnalysis(ConversationSession $session, int $exchangeCount): bool
    {
        if ($session->lang !== 'mandarin' || $exchangeCount === 0) {
            return false;
        }

        try {
            if (! app(SessionAnalyzer::class)->isConfigured()) {
                return false;
            }

            $job = new AnalyzeSessionJob($session->id);

            config('queue.default') === 'sync' ? Bus::dispatchAfterResponse($job) : Bus::dispatch($job);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function generateFeedback(ConversationSession $session): string
    {
        $transcript = $session->turns()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ConversationTurn $t) => ($t->role === 'user' ? 'User' : 'AI').': '.$t->content)
            ->implode("\n");

        $result = $this->client->sendMessage(
            model: config('services.active_ai_model'),
            system: 'Kamu adalah asisten bahasa yang memberi feedback singkat dalam Bahasa Indonesia. Berdasarkan transkrip latihan percakapan berikut, berikan 2-3 catatan feedback singkat (grammar/kosakata yang perlu diperbaiki), sebagai bullet points singkat.',
            messages: [['role' => 'user', 'content' => $transcript]],
            maxTokens: 200,
        );

        ConversationApiCall::create([
            'session_id' => $session->id,
            'tokens_in' => $result['tokens_in'],
            'tokens_out' => $result['tokens_out'],
        ]);

        return $result['text'];
    }

    /**
     * Persona/setting differs between scripted scenarios and "Ngobrol Bebas",
     * but the correction instruction and reply-length rule are always the
     * same BAKU_INSTRUCTION appended once here — never duplicated per mode.
     */
    private function systemPromptFor(ConversationSession $session): string
    {
        $persona = $session->scenario_key === 'random'
            ? $this->randomModePersona($session)
            : $this->scenario($session->lang, $session->scenario_key)['system_prompt_template'];

        $prompt = $persona."\n\n".self::BAKU_INSTRUCTION;

        if ($session->lang === 'mandarin') {
            // Before the translation instruction, so the marker format stays the last thing the model reads.
            if ($this->levelLocked($session)) {
                $prompt .= "\n\n".$this->levelLockInstruction();
            }

            $prompt .= "\n\n".self::MANDARIN_TRANSLATION_INSTRUCTION;
        }

        return $prompt;
    }

    /**
     * Mandarin sessions are locked to the HSK1 vocabulary when
     * config('hsk.level_lock') is on — and only when there actually is an
     * HSK1 list to lock to (an empty one would just be a confusing rule).
     * Also drives the "Level: HSK 1 (terkunci)" badge, so the badge never
     * claims a lock the prompt doesn't have.
     */
    public function levelLocked(ConversationSession $session): bool
    {
        return $session->lang === 'mandarin'
            && config('hsk.level_lock')
            && HskVocabularyPrompt::hsk1List() !== '';
    }

    /**
     * A strong request, not a guarantee: the model can still drift outside
     * the list. The no-pinyin rule applies to the Mandarin sentences only —
     * BAKU_INSTRUCTION's Indonesian correction is still allowed before the
     * translation marker.
     */
    public function levelLockInstruction(): string
    {
        $marker = self::MANDARIN_TRANSLATION_MARKER;

        return "KUNCI LEVEL HSK 1: Kalimat Mandarin dalam balasanmu HANYA boleh memakai kata dari DAFTAR KOSAKATA HSK 1 di bawah. Kalau benar-benar terpaksa memakai kata di luar daftar, pakai seminimal mungkin. Kata atau kalimat user yang ada di luar daftar TIDAK dianggap salah — jangan koreksi user hanya karena kata itu tidak ada di daftar.\n"
            ."FORMAT WAJIB (balasan ini dibacakan oleh text-to-speech): kalimat Mandarin ditulis HANYA dengan hanzi dan tanda baca Mandarin — tanpa pinyin, tanpa tanda kurung, tanpa terjemahan di sampingnya. Koreksi singkat berbahasa Indonesia sesuai aturan koreksi di atas tetap boleh. Terjemahan Indonesia hanya ditulis setelah penanda {$marker}.\n"
            .'DAFTAR KOSAKATA HSK 1: '.HskVocabularyPrompt::hsk1List();
    }

    /** Mandarin replies carry an extra Indonesian translation, so they need more room. */
    private function maxTokensFor(ConversationSession $session): int
    {
        return $session->lang === 'mandarin' ? 300 : 200;
    }

    /**
     * Splits "<reply>###TERJEMAHAN_ID###<translation>" for Mandarin sessions.
     * Falls back to the raw text with no translation if the model didn't
     * follow the marker format (English sessions never look for it at all).
     *
     * @return array{content: string, translation: ?string}
     */
    private function parseAssistantReply(ConversationSession $session, string $rawText): array
    {
        if ($session->lang !== 'mandarin') {
            return ['content' => $rawText, 'translation' => null];
        }

        $parts = explode(self::MANDARIN_TRANSLATION_MARKER, $rawText, 2);

        if (count($parts) === 2) {
            return ['content' => trim($parts[0]), 'translation' => trim($parts[1])];
        }

        return ['content' => trim($rawText), 'translation' => null];
    }

    private function randomModePersona(ConversationSession $session): string
    {
        $topic = $session->topic_label;

        if ($session->lang === 'mandarin') {
            return "Kamu teman ngobrol santai berbahasa Mandarin, gunakan level dasar (HSK1) yang sederhana. Obrolan dimulai soal topik '{$topic}', tapi kamu boleh natural pindah ke topik lain kalau user bawa obrolan ke arah situ — persis kayak ngobrol beneran sama teman, jangan maksa balik ke topik awal atau bilang itu di luar topik. Ajukan pertanyaan lanjutan yang genuine, jangan interogasi. Nada santai, bukan formal seperti mode wawancara/hotel.";
        }

        return "You're a laid-back friend having a casual chat in English. The conversation started around the topic '{$topic}', but feel free to naturally drift to whatever the user brings up — just like a real chat with a friend, never force it back to the original topic or say it's off-topic. Ask genuine follow-up questions, not an interrogation. Keep the tone relaxed, not formal like the interview/hotel scenarios.";
    }

    /**
     * Last 10 turns of the session, oldest first, guaranteed to start with a
     * 'user' turn (Claude rejects a leading 'assistant' message) — the window
     * boundary can otherwise land right after a user/assistant pair.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function recentMessages(ConversationSession $session): array
    {
        $turns = ConversationTurn::where('session_id', $session->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_WINDOW)
            ->get()
            ->reverse()
            ->values();

        if ($turns->isNotEmpty() && $turns->first()->role === 'assistant') {
            $turns = $turns->slice(1)->values();
        }

        return $turns->map(fn (ConversationTurn $t) => [
            'role' => $t->role,
            'content' => $t->content,
        ])->all();
    }
}
