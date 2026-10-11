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
        <section aria-labelledby="akun-demo-judul" class="mb-6 rounded-xl border-2 border-navy bg-navy/5 p-4 text-ink"
                 x-data="{ terisi: false }">
            <h2 id="akun-demo-judul" class="font-700 text-sm text-navy">Akun demo (hanya lingkungan lokal)</h2>
            <dl class="mt-2 text-sm grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                <dt class="font-700">Email</dt>
                <dd class="break-all">{{ config('demo.email') }}</dd>
                <dt class="font-700">Kata sandi</dt>
                <dd class="break-all">{{ config('demo.password') }}</dd>
            </dl>
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

    <h1 class="font-display font-600 text-xl text-ink mb-5">Masuk ke akunmu</h1>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Kata Sandi')" />
            <x-text-input wire:model="form.password" id="password" class="block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
        </div>

        <!-- Remember Me -->
        <div>
            <label for="remember" class="inline-flex items-center gap-2 cursor-pointer">
                <input wire:model="form.remember" id="remember" type="checkbox"
                       class="rounded border-line-card text-navy focus:ring-navy" name="remember">
                <span class="text-sm text-ink-soft">{{ __('Ingat saya') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between pt-1">
            @if (Route::has('password.request'))
                <a class="text-sm text-mandarin hover:underline focus:outline-none"
                   href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Lupa kata sandi?') }}
                </a>
            @endif

            <x-primary-button class="px-6">
                {{ __('Masuk') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-5 pt-5 border-t border-line-card text-center text-sm text-ink-soft">
        Belum punya akun?
        <a href="{{ route('register') }}" wire:navigate class="text-mandarin font-700 hover:underline ml-1">Daftar sekarang</a>
    </div>
</div>
