<?php

namespace Tests\Feature;

use App\Livewire\Conversation\Chat;
use App\Livewire\Conversation\Picker;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\User;
use App\Services\ConversationService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function onboardedUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ], $overrides));
    }

    protected function fakeGeminiReply(string $text = 'This is a mocked reply.'): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $text]]]],
                ],
                'usageMetadata' => ['promptTokenCount' => 42, 'candidatesTokenCount' => 17],
            ], 200),
        ]);
    }

    public function test_selecting_a_scenario_creates_a_session_and_redirects_to_chat(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Picker::class)
            ->set('lang', 'english')
            ->call('selectScenario', 'job_interview')
            ->assertRedirect();

        $this->assertDatabaseHas('conversation_sessions', [
            'user_id' => $user->id,
            'lang' => 'english',
            'scenario_key' => 'job_interview',
        ]);
    }

    public function test_sending_a_message_replies_in_character_and_stores_both_turns(): void
    {
        $this->fakeGeminiReply('Great, tell me about your previous experience.');
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Hello, I am here for the interview.')
            ->call('sendMessage')
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id, 'role' => 'user', 'content' => 'Hello, I am here for the interview.',
        ]);
        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id, 'role' => 'assistant', 'content' => 'Great, tell me about your previous experience.',
        ]);

        // The system prompt sent to Gemini carries this scenario's persona.
        Http::assertSent(function ($request) {
            return str_contains($request['systemInstruction']['parts'][0]['text'], 'hiring manager');
        });

        $this->assertDatabaseCount('conversation_api_calls', 1);
        $this->assertDatabaseHas('conversation_api_calls', [
            'session_id' => $session->id, 'tokens_in' => 42, 'tokens_out' => 17,
        ]);
    }

    public function test_system_prompt_always_includes_the_correction_instruction(): void
    {
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'restaurant_ordering', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', '我要一个个米饭') // deliberately awkward grammar
            ->call('sendMessage');

        Http::assertSent(function ($request) {
            $system = $request['systemInstruction']['parts'][0]['text'];

            return str_contains($system, 'sisipkan koreksi singkat')
                && str_contains($system, 'jangan interupsi jadi mode')
                && str_contains($system, 'restoran'); // scenario persona still present
        });
    }

    /**
     * A correction phrased only in Mandarin/English doesn't help a beginner
     * who didn't understand the target language well enough to avoid the
     * mistake in the first place — the explanation itself must be Indonesian.
     */
    public function test_correction_instruction_requires_an_indonesian_explanation_with_meaning(): void
    {
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'restaurant_ordering', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', '我要一个个米饭')
            ->call('sendMessage');

        Http::assertSent(function ($request) {
            $system = $request['systemInstruction']['parts'][0]['text'];

            return str_contains($system, 'DALAM BAHASA INDONESIA')
                && str_contains($system, 'kata/kalimat yang salah dari user')
                && str_contains($system, 'kata/kalimat yang benar')
                && str_contains($system, 'arti dari kata yang benar');
        });
    }

    /** Same requirement for English sessions — not just Mandarin. */
    public function test_correction_instruction_also_applies_to_english_sessions(): void
    {
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'I have many experience in this field')
            ->call('sendMessage');

        Http::assertSent(function ($request) {
            $system = $request['systemInstruction']['parts'][0]['text'];

            return str_contains($system, 'DALAM BAHASA INDONESIA')
                && str_contains($system, 'arti dari kata yang benar');
        });
    }

    /** The model must not manufacture a correction when the user made no mistake. */
    public function test_correction_instruction_does_not_force_finding_a_mistake(): void
    {
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'I have five years of experience in marketing.')
            ->call('sendMessage');

        Http::assertSent(function ($request) {
            $system = $request['systemInstruction']['parts'][0]['text'];

            return str_contains($system, 'JANGAN cari-cari kesalahan');
        });
    }

    public function test_51st_message_today_is_rejected_without_calling_the_api(): void
    {
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        for ($i = 0; $i < 50; $i++) {
            ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => "msg {$i}"]);
        }

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'One more message please')
            ->call('sendMessage')
            ->assertSet('errorMessage', 'Kuota harian habis, lanjut besok.');

        Http::assertNothingSent();
        $this->assertSame(50, ConversationTurn::where('session_id', $session->id)->where('role', 'user')->count());
    }

    public function test_only_last_ten_turns_are_sent_for_a_long_session(): void
    {
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        // 12 prior exchanges (24 turns), each a second apart so ordering is stable.
        $t = now()->subMinutes(30);
        for ($i = 0; $i < 12; $i++) {
            ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => "user-{$i}", 'created_at' => $t]);
            $t = $t->copy()->addSecond();
            ConversationTurn::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => "assistant-{$i}", 'created_at' => $t]);
            $t = $t->copy()->addSecond();
        }

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'the newest message')
            ->call('sendMessage');

        Http::assertSent(function ($request) {
            $contents = $request['contents'];

            // Bounded to at most 10, first message must be 'user' (Gemini requirement,
            // same as Claude's), and it must NOT include the earliest turns from this
            // 24-turn session.
            $this->assertLessThanOrEqual(10, count($contents));
            $this->assertSame('user', $contents[0]['role']);
            $this->assertSame('the newest message', end($contents)['parts'][0]['text']);

            // AC2: stored 'assistant' turns must be mapped to Gemini's 'model' role,
            // never sent through verbatim as 'assistant' (which Gemini doesn't use).
            $roles = array_column($contents, 'role');
            $this->assertContains('model', $roles);
            $this->assertNotContains('assistant', $roles);

            foreach ($contents as $c) {
                $this->assertNotSame('user-0', $c['parts'][0]['text']);
                $this->assertNotSame('assistant-0', $c['parts'][0]['text']);
            }

            return true;
        });
    }

    public function test_empty_and_overlong_messages_are_rejected_without_calling_the_api(): void
    {
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', '   ')
            ->call('sendMessage')
            ->assertSet('errorMessage', 'Pesan tidak boleh kosong.');

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', str_repeat('a', 501))
            ->call('sendMessage')
            ->assertSet('errorMessage', 'Pesan maksimal 500 karakter.');

        Http::assertNothingSent();
        $this->assertSame(0, ConversationTurn::where('session_id', $session->id)->count());
    }

    public function test_finishing_a_session_marks_it_ended_and_generates_feedback_notes(): void
    {
        $this->fakeGeminiReply('Some feedback notes.');
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);
        ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'hi']);
        ConversationTurn::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => 'hello']);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->call('finishSession')
            ->assertSet('summary.exchange_count', 1)
            ->assertSet('summary.notes', 'Some feedback notes.');

        $this->assertNotNull($session->fresh()->ended_at);
    }

    public function test_the_configured_gemini_model_is_the_one_actually_requested(): void
    {
        // Regression guard: ConversationService previously called Claude's model
        // config (config('services.anthropic.model')) even after the provider was
        // switched to Gemini, so every real request 404'd with
        // "models/claude-haiku-4-5-20251001 is not found". This asserts the model
        // in the request URL matches the actually-configured Gemini model, not a
        // leftover provider-specific config key.
        config(['services.anthropic.model' => 'claude-marker-should-never-be-sent']);
        config(['services.active_ai_model' => 'gemini-regression-test-model']);
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Hello')
            ->call('sendMessage');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-regression-test-model:generateContent')
                && ! str_contains($request->url(), 'claude-marker-should-never-be-sent');
        });
    }

    public function test_missing_gemini_key_shows_a_graceful_error_not_a_crash(): void
    {
        config(['services.gemini.key' => null]);
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Hello?')
            ->call('sendMessage')
            ->assertOk()
            ->assertSet('errorMessage', 'AI sedang tidak tersedia. Coba lagi nanti.');

        // The user's own message is still saved even though the AI call failed.
        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id, 'role' => 'user', 'content' => 'Hello?',
        ]);
    }

    public function test_user_cannot_open_another_users_session(): void
    {
        $owner = $this->onboardedUser(['email' => 'owner@example.com']);
        $intruder = $this->onboardedUser(['email' => 'intruder@example.com']);
        $session = ConversationSession::create([
            'user_id' => $owner->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        $this->actingAs($intruder)->get(route('conversation.chat', $session))->assertForbidden();
    }

    public function test_api_key_never_appears_in_rendered_html(): void
    {
        $this->fakeGeminiReply();
        config(['services.gemini.key' => 'gemini-super-secret-value']);
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'hotel_checkin', 'started_at' => now(),
        ]);

        $picker = $this->actingAs($user)->get(route('conversation'));
        $chat = $this->actingAs($user)->get(route('conversation.chat', $session));

        $picker->assertDontSee('gemini-super-secret-value');
        $chat->assertDontSee('gemini-super-secret-value');
    }

    public function test_speaker_button_uses_the_scenario_target_lang_code(): void
    {
        $this->fakeGeminiReply('你好，请问你叫什么名字？');
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'hotel_checkin', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', '你好')
            ->call('sendMessage')
            ->assertSee('speak(', false)
            ->assertSee('zh-CN', false);
    }

    public function test_mandarin_reply_is_split_into_hanzi_content_and_indonesian_translation(): void
    {
        $this->fakeGeminiReply("你好！今天天气怎么样？\n###TERJEMAHAN_ID###\nHalo! Cuaca hari ini gimana?");
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'hotel_checkin', 'started_at' => now(),
        ]);

        $reply = Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', '你好')
            ->call('sendMessage');

        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => '你好！今天天气怎么样？',
            'translation' => 'Halo! Cuaca hari ini gimana?',
        ]);

        // Auto-play/TTS must only ever get the Mandarin text, never the marker or translation.
        $reply->assertDispatched('ai-replied', text: '你好！今天天气怎么样？', langCode: 'zh-CN');

        // System prompt asked for the marker format.
        Http::assertSent(fn ($request) => str_contains(
            $request['systemInstruction']['parts'][0]['text'],
            '###TERJEMAHAN_ID###'
        ));
    }

    public function test_mandarin_reply_without_the_marker_falls_back_to_plain_text_no_translation(): void
    {
        $this->fakeGeminiReply('你好！很高兴认识你。'); // no marker — model didn't follow the format
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'hotel_checkin', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', '你好')
            ->call('sendMessage');

        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => '你好！很高兴认识你。',
            'translation' => null,
        ]);
    }

    public function test_english_sessions_never_request_a_translation(): void
    {
        $this->fakeGeminiReply('Great, tell me more about that.');
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Hello')
            ->call('sendMessage');

        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id, 'role' => 'assistant',
            'content' => 'Great, tell me more about that.', 'translation' => null,
        ]);

        Http::assertSent(fn ($request) => ! str_contains(
            $request['systemInstruction']['parts'][0]['text'],
            '###TERJEMAHAN_ID###'
        ));
    }

    public function test_random_mode_mandarin_opener_also_gets_split_into_translation(): void
    {
        $this->fakeGeminiReply("你好！你喜欢什么运动？\n###TERJEMAHAN_ID###\nHalo! Kamu suka olahraga apa?");
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Picker::class)
            ->set('lang', 'mandarin')
            ->call('startRandom');

        $session = ConversationSession::where('user_id', $user->id)->firstOrFail();

        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => '你好！你喜欢什么运动？',
            'translation' => 'Halo! Kamu suka olahraga apa?',
        ]);
    }

    public function test_voice_transcript_argument_goes_through_the_same_send_message_method(): void
    {
        $this->fakeGeminiReply('Nice to meet you too.');
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        // Simulates the Alpine voiceInput component calling $wire.sendMessage(transcript)
        // after recognition.onend — no separate property assignment, no separate method.
        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->call('sendMessage', 'Nice to meet you, my name is Jordan.')
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id, 'role' => 'user', 'content' => 'Nice to meet you, my name is Jordan.',
        ]);

        // Same rate-limit counter the text path uses.
        $service = app(ConversationService::class);
        $this->assertSame(1, $service->messagesSentToday($user));
    }

    public function test_user_turns_record_whether_they_were_spoken_or_typed(): void
    {
        $this->fakeGeminiReply('Nice to meet you too.');
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        // Voice: the Alpine voiceInput component calls $wire.sendMessage(transcript).
        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->call('sendMessage', 'Spoken hello.');

        // Text: the form submits sendMessage() with no argument.
        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Typed hello.')
            ->call('sendMessage');

        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id, 'role' => 'user', 'content' => 'Spoken hello.', 'input_mode' => 'voice',
        ]);
        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id, 'role' => 'user', 'content' => 'Typed hello.', 'input_mode' => 'text',
        ]);
        $this->assertSame(0, ConversationTurn::where('role', 'assistant')->where('input_mode', '!=', 'text')->count());
        $this->assertSame(2, ConversationTurn::where('role', 'assistant')->count());
    }

    public function test_voice_path_respects_the_same_daily_quota_as_text(): void
    {
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        for ($i = 0; $i < 50; $i++) {
            ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => "msg {$i}"]);
        }

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->call('sendMessage', 'one more spoken message')
            ->assertSet('errorMessage', 'Kuota harian habis, lanjut besok.');

        Http::assertNothingSent();
    }

    public function test_ai_replied_event_carries_the_reply_text_and_scenario_lang_code(): void
    {
        $this->fakeGeminiReply('你叫什么名字？');
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'hotel_checkin', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', '你好')
            ->call('sendMessage')
            ->assertDispatched('ai-replied', text: '你叫什么名字？', langCode: 'zh-CN');
    }

    public function test_autoplay_defaults_on_and_is_toggleable(): void
    {
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->assertSet('autoPlay', true)
            ->set('autoPlay', false)
            ->assertSet('autoPlay', false);
    }

    public function test_chat_renders_voice_controls_and_the_unsupported_browser_fallback_markup(): void
    {
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);

        // Both the mic button and the "unsupported browser" note are always
        // rendered server-side; Alpine's x-show toggles between them at
        // runtime based on a client-side SpeechRecognition feature check,
        // which a server-side test can't simulate directly.
        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->assertSee('toggleListening', false)
            ->assertSee('Voice input butuh Chrome/Edge');
    }

    public function test_random_mode_creates_a_session_with_a_mandarin_topic_from_the_18_topic_pool(): void
    {
        $this->fakeGeminiReply('你好！今天天气怎么样？');
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Picker::class)
            ->set('lang', 'mandarin')
            ->call('startRandom')
            ->assertRedirect();

        $session = ConversationSession::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('mandarin', $session->lang);
        $this->assertSame('random', $session->scenario_key);
        $this->assertNotNull($session->topic_label);

        $mandarinTopics = collect(config('conversation_topics.mandarin'))
            ->map(fn ($t) => "{$t['hanzi']} ({$t['meaning_id']})");
        $this->assertTrue($mandarinTopics->contains($session->topic_label));
    }

    public function test_random_mode_has_the_ai_send_the_first_message_automatically(): void
    {
        $this->seed(ReferenceDataSeeder::class); // populates week_topics, the English topic pool
        $this->fakeGeminiReply('Hey! So what do you like doing in your free time?');
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Picker::class)
            ->set('lang', 'english')
            ->call('startRandom');

        $session = ConversationSession::where('user_id', $user->id)->firstOrFail();

        $this->assertDatabaseHas('conversation_turns', [
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Hey! So what do you like doing in your free time?',
        ]);

        // The AI's opener isn't a user turn, so it must not eat into the daily quota.
        $service = app(ConversationService::class);
        $this->assertSame(0, $service->messagesSentToday($user));
    }

    public function test_random_mode_topic_varies_across_repeated_calls(): void
    {
        $this->fakeGeminiReply('Ni hao!');
        $user = $this->onboardedUser();

        $topics = collect();
        for ($i = 0; $i < 10; $i++) {
            Livewire::actingAs($user)->test(Picker::class)
                ->set('lang', 'mandarin')
                ->call('startRandom');

            $topics->push(ConversationSession::where('user_id', $user->id)->latest('id')->value('topic_label'));
        }

        $this->assertGreaterThanOrEqual(3, $topics->unique()->count(), 'Expected at least 3 distinct topics across 10 random sessions.');
    }

    public function test_random_mode_system_prompt_lets_the_conversation_drift_and_still_includes_correction_instruction(): void
    {
        $this->fakeGeminiReply('Oh nice, what kind of food do you like?');
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'random',
            'topic_label' => 'Hobbies & Free Time', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', "Actually I don't really have hobbies, I love food though")
            ->call('sendMessage');

        Http::assertSent(function ($request) {
            $system = $request['systemInstruction']['parts'][0]['text'];

            return str_contains($system, 'naturally drift')
                && str_contains($system, 'never force it back')
                && str_contains($system, 'sisipkan koreksi singkat') // shared BAKU_INSTRUCTION, not duplicated
                && str_contains($system, 'jangan interupsi jadi mode')
                && str_contains($system, 'DALAM BAHASA INDONESIA'); // same instruction, not a scripted-only variant
        });
    }

    public function test_random_mode_voice_and_autoplay_work_the_same_as_scripted_mode(): void
    {
        $this->fakeGeminiReply('你叫什么名字？');
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'random',
            'topic_label' => '爱好 (hobi)', 'started_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->assertSet('autoPlay', true)
            ->assertSee('toggleListening', false) // same voiceInput component reused
            ->call('sendMessage', '我喜欢看书')
            ->assertDispatched('ai-replied', text: '你叫什么名字？', langCode: 'zh-CN');
    }

    public function test_random_mode_shares_the_same_daily_quota_as_scripted_mode(): void
    {
        $this->fakeGeminiReply();
        $user = $this->onboardedUser();

        $scriptedSession = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);
        $randomSession = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'random',
            'topic_label' => 'Hobbies & Free Time', 'started_at' => now(),
        ]);

        // 30 messages via the scripted session, 20 via random — 50 total, shared quota.
        for ($i = 0; $i < 30; $i++) {
            ConversationTurn::create(['session_id' => $scriptedSession->id, 'role' => 'user', 'content' => "s{$i}"]);
        }
        for ($i = 0; $i < 20; $i++) {
            ConversationTurn::create(['session_id' => $randomSession->id, 'role' => 'user', 'content' => "r{$i}"]);
        }

        $service = app(ConversationService::class);
        $this->assertSame(0, $service->remainingMessagesToday($user));

        Livewire::actingAs($user)->test(Chat::class, ['session' => $randomSession])
            ->set('newMessage', 'one more')
            ->call('sendMessage')
            ->assertSet('errorMessage', 'Kuota harian habis, lanjut besok.');

        Http::assertNothingSent();
    }
}
