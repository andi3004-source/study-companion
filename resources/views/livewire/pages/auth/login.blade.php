<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    {{-- Local demo account (config/demo.php). Rendered only when enabled, outside production, and a password is set. --}}
    @if (config('demo.enabled') && ! app()->environment('production') && filled(config('demo.password')))
        <section aria-labelledby="akun-demo-judul" class="mb-6 rounded-xl border-2 border-navy bg-card p-4 text-ink"
                 x-data="{ terisi: false }">
            <h2 id="akun-demo-judul" class="font-700 text-sm">Akun demo (hanya lingkungan lokal)</h2>
            <dl class="mt-2 text-sm grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                <dt class="font-700">Email</dt>
                <dd class="break-all">{{ config('demo.email') }}</dd>
                <dt class="font-700">Kata sandi</dt>
                <dd class="break-all">{{ config('demo.password') }}</dd>
            </dl>
            {{-- Only fills the fields (dispatching input so wire:model picks it up); never submits. --}}
            <button type="button"
                    x-on:click="
                        for (const [id, value] of [['email', {{ \Illuminate\Support\Js::from(config('demo.email')) }}], ['password', {{ \Illuminate\Support\Js::from(config('demo.password')) }}]]) {
                            const input = document.getElementById(id);
                            input.value = value;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        terisi = true;
                    "
                    class="mt-3 px-4 rounded-lg bg-navy text-white text-sm font-700 hover:bg-navy-soft">Isi otomatis</button>
            <p role="status" class="mt-2 text-sm"><span x-show="terisi" x-cloak>Email dan kata sandi demo sudah diisi. Tekan Log in untuk masuk.</span></p>
        </section>
    @endif

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</div>
