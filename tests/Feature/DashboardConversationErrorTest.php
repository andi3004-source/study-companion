<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\User;
use App\Support\AiErrorMessage;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardConversationErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_ai_failure_keeps_the_modal_open_with_a_friendly_message_and_no_session(): void
    {
        Config::set('services.gemini.key', 'test-key');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)]);

        $user = User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
        $component = Livewire::actingAs($user)->test(Dashboard::class)->call('goToDay', 1);
        $speakingItem = collect($component->get('items'))->firstWhere('type', 'speaking');

        $component->call('openItem', $speakingItem['id'])
            ->call('startConversationFromItem', $speakingItem['id'])
            ->assertOk()
            ->assertNoRedirect()
            ->assertSet('activeItemId', $speakingItem['id'])
            ->assertSet('conversationError', AiErrorMessage::BUSY)
            ->assertSee('Layanan AI sedang penuh, coba lagi sebentar.')
            ->call('closeItem')
            ->assertSet('conversationError', null);

        $this->assertDatabaseCount('conversation_sessions', 0);
    }
}
