<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/** REGISTRATION_ENABLED and REGISTRATION_INVITE_CODE (config/registration.php). */
class RegistrationGateTest extends TestCase
{
    use RefreshDatabase;

    private const CLOSED = 'Pendaftaran ditutup. Gunakan akun demo atau hubungi tim';

    private function fill(array $extra = [])
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        foreach ($extra as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    public function test_defaults_are_open_and_without_invite_code(): void
    {
        $this->assertTrue(config('registration.enabled'));
        $this->assertSame('', config('registration.invite_code'));

        $this->get('/register')->assertOk()->assertSee('Daftar Sekarang')->assertDontSee('Kode Undangan');
        $this->get('/login')->assertSee('Daftar sekarang');
        $this->get('/')->assertSee('href="'.route('register').'"', false);
    }

    public function test_closed_registration_shows_a_friendly_message_and_hides_links(): void
    {
        config(['registration.enabled' => false]);

        $this->get('/register')->assertOk()->assertSee(self::CLOSED)->assertDontSee('Daftar Sekarang')->assertDontSee('wire:submit="register"', false);
        $this->get('/login')->assertSee(self::CLOSED)->assertDontSee('href="'.route('register').'"', false);
        $this->get('/')->assertDontSee('href="'.route('register').'"', false);
    }

    public function test_closed_registration_refuses_the_action(): void
    {
        config(['registration.enabled' => false]);

        $this->fill()->call('register')->assertForbidden();

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_invite_code_is_required_and_never_rendered(): void
    {
        config(['registration.invite_code' => 'rahasia-undangan-123']);

        $this->get('/register')->assertOk()->assertSee('Kode Undangan')->assertDontSee('rahasia-undangan-123');

        $this->fill()->call('register')->assertHasErrors(['invite_code' => 'required']);
        $this->fill(['invite_code' => 'salah'])->call('register')->assertHasErrors('invite_code')->assertSee('Kode undangan tidak cocok.')->assertDontSee('rahasia-undangan-123');
        $this->assertGuest();

        $this->fill(['invite_code' => 'rahasia-undangan-123'])->call('register')->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_invite_code_is_compared_with_hash_equals(): void
    {
        $source = file_get_contents(resource_path('views/livewire/pages/auth/register.blade.php'));

        $this->assertStringContainsString("hash_equals(config('registration.invite_code'), \$validated['invite_code'])", $source);
        $this->assertStringNotContainsString("{{ config('registration.invite_code') }}", $source);
    }

    public function test_env_files_list_the_defaults(): void
    {
        $this->assertStringContainsString("REGISTRATION_ENABLED=true\nREGISTRATION_INVITE_CODE=\n", file_get_contents(base_path('.env.example')));

        $phpunit = file_get_contents(base_path('phpunit.xml'));
        $this->assertStringContainsString('<env name="REGISTRATION_ENABLED" value="true" force="true"/>', $phpunit);
        $this->assertStringContainsString('<env name="REGISTRATION_INVITE_CODE" value="" force="true"/>', $phpunit);
    }
}
