<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileNavTest extends TestCase
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

    public function test_bottom_bar_shows_four_main_items_plus_a_lainnya_button(): void
    {
        $user = $this->onboardedUser();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Lainnya');
        // The 4 main items link directly, the other 4 only appear inside the sheet.
        $response->assertSeeInOrder(['Hari Ini', 'Kartu', 'Kamus', 'Ngobrol', 'Lainnya']);
    }

    public function test_more_items_are_reachable_from_the_sheet(): void
    {
        $user = $this->onboardedUser();

        $response = $this->actingAs($user)->get('/dashboard');

        foreach (['Ujian', 'Isian', 'Ucapan', 'Progress'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_visiting_a_more_page_highlights_the_lainnya_button(): void
    {
        $user = $this->onboardedUser();

        $onMorePage = $this->actingAs($user)->get('/progress')->getContent();
        $onMainPage = $this->actingAs($user)->get('/dashboard')->getContent();

        // Isolate the "Lainnya" <button>...</button> block specifically (not
        // just "does text-mandarin appear anywhere on the page") and check
        // its own class list flips between the two pages.
        preg_match('/<button type="button" x-on:click="moreOpen = true"(.*?)<\/button>/s', $onMorePage, $moreMatch);
        preg_match('/<button type="button" x-on:click="moreOpen = true"(.*?)<\/button>/s', $onMainPage, $mainMatch);

        $this->assertNotEmpty($moreMatch);
        $this->assertNotEmpty($mainMatch);
        $this->assertStringContainsString('text-mandarin', $moreMatch[1]);
        $this->assertStringNotContainsString('text-mandarin', $mainMatch[1]);
    }

    public function test_desktop_sidebar_still_has_all_eight_items(): void
    {
        $user = $this->onboardedUser();

        $response = $this->actingAs($user)->get('/dashboard');

        foreach (['Hari Ini', 'Kartu', 'Kamus', 'Ujian', 'Isian', 'Ngobrol', 'Ucapan', 'Progress'] as $label) {
            $response->assertSee($label);
        }
    }
}
