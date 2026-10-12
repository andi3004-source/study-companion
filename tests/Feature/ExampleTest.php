<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_public_home_page(): void
    {
        $this->get('/')->assertOk()->assertViewIs('welcome');
    }

    public function test_logged_in_users_are_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->create(['onboarded' => true, 'start_date' => now()->toDateString(), 'track' => 'both']);

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }
}
