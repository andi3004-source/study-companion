<?php

namespace Tests\Feature;

use App\Livewire\ScanBenda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** After every Gemini attempt answered 503, the error offers "Foto lagi"; other errors do not. */
class ScanBusyRetakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_error_shows_a_retake_button_only_for_a_busy_503(): void
    {
        config(['scan.enabled' => true]);
        $user = User::factory()->create(['onboarded' => true, 'start_date' => now()->toDateString(), 'track' => 'both']);

        Livewire::actingAs($user)->test(ScanBenda::class)
            ->assertSeeHtml('<button type="button" x-show="galat && galatSibuk" x-cloak x-on:click="fotoLagi()"');
    }

    public function test_the_script_sets_the_busy_flag_from_the_503_and_clears_it(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('this.galatSibuk = response.status === 503;', $js);
        // Cleared on a new photo and on "Foto lagi", so it never sticks to a later, different error.
        $this->assertSame(2, substr_count($js, 'this.galatSibuk = false;'));
    }
}
