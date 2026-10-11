<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="font-display font-600 text-xl text-ink mb-5">Buat akun baru</h1>

    <form wire:submit="register" class="space-y-4">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Nama Lengkap')" />
            <x-text-input wire:model="name" id="name" class="block w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Kata Sandi')" />
            <x-text-input wire:model="password" id="password" class="block w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Konfirmasi Kata Sandi')" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <div class="pt-1">
            <x-primary-button class="w-full flex justify-center py-3">
                {{ __('Daftar Sekarang') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-5 pt-5 border-t border-line-card text-center text-sm text-ink-soft">
        Sudah punya akun?
        <a class="text-mandarin font-700 hover:underline ml-1" href="{{ route('login') }}" wire:navigate>
            {{ __('Masuk') }}
        </a>
    </div>
</div>
