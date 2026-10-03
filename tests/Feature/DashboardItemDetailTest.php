<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The detail modal (resources/views/livewire/partials/item-detail.blade.php)
 * only renders once openItem() is called — SmokeTest's "dashboard loads"
 * checks never actually open it, so a rendering bug in any @case branch
 * (undefined array key, wrong route param, ...) would pass every existing
 * test while still 500ing for a real user. This exercises openItem() for
 * every item type the scheduler can produce across the full HSK1 (26 day)
 * and IELTS (62 day) cycles, both tracks.
 */
class DashboardItemDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function onboardedUser(): User
    {
        $this->seed(ReferenceDataSeeder::class);

        return User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }

    public function test_every_checklist_item_across_the_full_schedule_opens_without_error(): void
    {
        $user = $this->onboardedUser();
        $component = Livewire::actingAs($user)->test(Dashboard::class);

        $seenTypes = [];

        for ($day = 1; $day <= 62; $day++) {
            $component->call('goToDay', $day);

            foreach ($component->get('items') as $item) {
                $component->call('openItem', $item['id'])->assertOk();
                $seenTypes[$item['type']] = true;
            }
        }

        // Sanity check that this loop actually exercised the interesting
        // types, not just a subset — if the scheduler changes and stops
        // producing one of these, this should fail loudly rather than the
        // test silently covering less than it claims to.
        $expectedTypes = [
            'vocab', 'grammar', 'conversation', 'speaking', 'writing',
            'listening', 'reading', 'dictation', 'review', 'quiz', 'mock', 'info',
        ];
        foreach ($expectedTypes as $type) {
            $this->assertArrayHasKey($type, $seenTypes, "Scheduler never produced a '{$type}' item across days 1-62 — test coverage gap.");
        }
    }

    public function test_vocab_item_practice_button_links_to_the_right_batch(): void
    {
        $user = $this->onboardedUser();
        $component = Livewire::actingAs($user)->test(Dashboard::class)->call('goToDay', 4); // first Mandarin vocab day

        $vocabItem = collect($component->get('items'))->firstWhere('type', 'vocab');
        $this->assertNotNull($vocabItem);

        $component->call('openItem', $vocabItem['id'])
            ->assertSee('Latihan Kartu Kata Ini')
            ->assertSee('batch='.$vocabItem['payload']['batch_number'], false);
    }

    public function test_review_item_practice_button_links_to_the_weeks_batch_range(): void
    {
        $user = $this->onboardedUser();
        $component = Livewire::actingAs($user)->test(Dashboard::class)->call('goToDay', 9); // first weekly review day

        $reviewItem = collect($component->get('items'))->firstWhere('type', 'review');
        $this->assertNotNull($reviewItem);

        $component->call('openItem', $reviewItem['id'])
            ->assertSee('Mulai Latihan Isian')
            ->assertSee('batchFrom='.$reviewItem['payload']['isian_batch_from'], false);
    }

    public function test_speaking_item_shows_conversation_and_pronunciation_buttons(): void
    {
        $user = $this->onboardedUser();
        $component = Livewire::actingAs($user)->test(Dashboard::class)->call('goToDay', 1); // English cycle 1: vocab, listening, speaking

        $speakingItem = collect($component->get('items'))->firstWhere('type', 'speaking');
        $this->assertNotNull($speakingItem);

        $component->call('openItem', $speakingItem['id'])
            ->assertSee('Latihan Ngobrol')
            ->assertSee('Cek Pengucapan')
            ->assertSee('/pronunciation-check', false);
    }

    public function test_starting_a_conversation_from_a_speaking_item_closes_the_modal_and_redirects_into_a_real_session(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Hi there!']]]]],
                'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
            ], 200),
        ]);
        Config::set('services.gemini.key', 'test-key');

        $user = $this->onboardedUser();
        $component = Livewire::actingAs($user)->test(Dashboard::class)->call('goToDay', 1);

        $speakingItem = collect($component->get('items'))->firstWhere('type', 'speaking');

        $component->call('openItem', $speakingItem['id'])
            ->assertSet('activeItemId', $speakingItem['id'])
            ->call('startConversationFromItem', $speakingItem['id'])
            ->assertSet('activeItemId', null) // modal closed before the redirect, not left open
            ->assertRedirect();

        $this->assertDatabaseCount('conversation_sessions', 1);
        $this->assertDatabaseHas('conversation_sessions', ['user_id' => $user->id, 'lang' => 'english']);
    }

    public function test_listening_item_has_a_dengar_ulangi_button_with_the_days_words(): void
    {
        $user = $this->onboardedUser();
        $component = Livewire::actingAs($user)->test(Dashboard::class)->call('goToDay', 1);

        $listeningItem = collect($component->get('items'))->firstWhere('type', 'listening');
        $this->assertNotNull($listeningItem);
        $this->assertNotEmpty($listeningItem['payload']['words']);

        $component->call('openItem', $listeningItem['id'])
            ->assertSee('Dengar & Ulangi', false)
            ->assertSee('speakSequence', false);
    }

    public function test_pinyin_foundation_day_has_a_dengar_contoh_nada_button(): void
    {
        $user = $this->onboardedUser();
        $component = Livewire::actingAs($user)->test(Dashboard::class)->call('goToDay', 1);

        $infoItem = collect($component->get('items'))->firstWhere('type', 'info');
        $this->assertNotNull($infoItem);

        $component->call('openItem', $infoItem['id'])
            ->assertSee('Dengar Contoh Nada')
            ->assertSee('妈');
    }

    public function test_external_links_are_labeled_as_secondary_reference(): void
    {
        $user = $this->onboardedUser();
        $component = Livewire::actingAs($user)->test(Dashboard::class)->call('goToDay', 4);

        $vocabItem = collect($component->get('items'))->firstWhere('type', 'vocab');

        $component->call('openItem', $vocabItem['id'])
            ->assertSee('Referensi Tambahan')
            ->assertDontSee('BUKA PLATFORM BELAJAR');
    }
}
