<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class VoiceCommandEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function user(array $overrides = []): User
    {
        return User::factory()->create($overrides + ['onboarded' => true, 'start_date' => now()->toDateString(), 'track' => 'both']);
    }

    public function test_returns_only_type_target_and_index(): void
    {
        $this->actingAs($this->user())->postJson(route('voice.command'), ['transcript' => 'Buka kamus'])
            ->assertOk()
            ->assertExactJson(['type' => 'navigate', 'target' => 'kamus', 'index' => null]);

        $this->actingAs($this->user())->postJson(route('voice.command'), ['transcript' => 'nomor tiga'])
            ->assertExactJson(['type' => 'choose_number', 'target' => null, 'index' => 2]);
    }

    public function test_empty_transcript_is_unknown_and_long_text_is_rejected(): void
    {
        $this->actingAs($this->user())->postJson(route('voice.command'), ['transcript' => ''])
            ->assertOk()->assertExactJson(['type' => 'unknown', 'target' => null, 'index' => null]);

        $this->actingAs($this->user())->postJson(route('voice.command'), ['transcript' => str_repeat('a', 201)])
            ->assertStatus(422);
    }

    public function test_transcript_is_not_logged_or_stored(): void
    {
        Log::spy();
        DB::enableQueryLog();

        $this->actingAs($this->user())->postJson(route('voice.command'), ['transcript' => 'rahasia-ucapan-xyz'])->assertOk();

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('warning');
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('rahasia-ucapan-xyz', json_encode($query['bindings']));
        }
    }

    public function test_requires_auth_verified_and_onboarded(): void
    {
        $this->postJson(route('voice.command'), ['transcript' => 'kamus'])->assertUnauthorized();

        $this->actingAs($this->user(['onboarded' => false]))->post(route('voice.command'), ['transcript' => 'kamus'])->assertRedirect();
    }

    public function test_is_throttled_at_sixty_per_minute(): void
    {
        $user = $this->user();

        foreach (range(1, 60) as $ignored) {
            $this->actingAs($user)->postJson(route('voice.command'), ['transcript' => 'ya'])->assertOk();
        }
        $this->actingAs($user)->postJson(route('voice.command'), ['transcript' => 'ya'])->assertStatus(429);
    }

    public function test_scan_flag_off_is_not_available(): void
    {
        $this->actingAs($this->user())->postJson(route('voice.command'), ['transcript' => 'kamera'])
            ->assertExactJson(['type' => 'not_available', 'target' => 'scan.object', 'index' => null]);
    }
}
