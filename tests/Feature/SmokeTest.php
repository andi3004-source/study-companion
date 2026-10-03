<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function onboardedUser(array $overrides = []): User
    {
        $this->seed(ReferenceDataSeeder::class);

        return User::factory()->create(array_merge([
            'start_date' => now()->subDays(2)->toDateString(), // day_number = 3
            'track' => 'both',
            'onboarded' => true,
        ], $overrides));
    }

    public function test_onboarding_page_loads_for_new_user(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $user = User::factory()->create(['onboarded' => false, 'start_date' => null]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/onboarding');
        $this->actingAs($user)->get('/onboarding')->assertOk();
    }

    public function test_dashboard_loads_across_many_days(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        foreach ([1, 2, 3, 8, 9, 15, 16, 22, 23, 26, 27, 30, 43, 50, 62, 63] as $day) {
            $user = User::factory()->create([
                'start_date' => now()->subDays($day - 1)->toDateString(),
                'track' => 'both',
                'onboarded' => true,
            ]);
            $response = $this->actingAs($user)->get('/dashboard');
            $response->assertOk();
        }
    }

    public function test_dashboard_day_route_loads(): void
    {
        $user = $this->onboardedUser();
        $this->actingAs($user)->get('/dashboard/day/9')->assertOk();
        $this->actingAs($user)->get('/dashboard/day/26')->assertOk();
        $this->actingAs($user)->get('/dashboard/day/62')->assertOk();
    }

    public function test_cards_decks_load(): void
    {
        $user = $this->onboardedUser();
        $this->actingAs($user)->get('/cards')->assertOk();
        $this->actingAs($user)->get('/cards/mandarin')->assertOk();
        $this->actingAs($user)->get('/cards/verb')->assertOk();
        $this->actingAs($user)->get('/cards/vocab')->assertOk();
    }

    public function test_kamus_loads(): void
    {
        $user = $this->onboardedUser();
        $this->actingAs($user)->get('/kamus')->assertOk();
    }

    public function test_ujian_picker_and_runner_load(): void
    {
        $user = $this->onboardedUser();
        $this->actingAs($user)->get('/ujian')->assertOk();
        $this->actingAs($user)->get('/ujian/mandarin')->assertOk();
        $this->actingAs($user)->get('/ujian/english')->assertOk();
        $this->actingAs($user)->get('/ujian/today')->assertOk();
    }

    public function test_latihan_isian_page_loads(): void
    {
        $user = $this->onboardedUser();
        $this->actingAs($user)->get('/latihan-isian')->assertOk();
    }

    public function test_conversation_picker_loads(): void
    {
        $user = $this->onboardedUser();
        $this->actingAs($user)->get('/conversation')->assertOk();
    }

    public function test_progress_page_loads(): void
    {
        $user = $this->onboardedUser();
        $this->actingAs($user)->get('/progress')->assertOk();
    }

    public function test_pronunciation_check_page_loads(): void
    {
        $user = $this->onboardedUser();
        $this->actingAs($user)->get('/pronunciation-check')->assertOk();
    }

    public function test_english_only_track_dashboard_loads(): void
    {
        $user = $this->onboardedUser(['track' => 'english']);
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_mandarin_only_track_dashboard_loads(): void
    {
        $user = $this->onboardedUser(['track' => 'mandarin']);
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
