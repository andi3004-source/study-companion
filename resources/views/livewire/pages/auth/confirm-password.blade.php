<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="font-display font-600 text-xl text-ink mb-4">Konfirmasi Kata Sandi</h1>
    <p class="mb-5 text-sm text-ink-soft">
        {{ __('Ini adalah area aman. Konfirmasi kata sandimu sebelum melanjutkan.') }}
    </p>

    <form wire:submit="confirmPassword" class="space-y-4">
        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Kata Sandi')" />
            <x-text-input wire:model="password"
                          id="password"
                          class="block w-full"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div class="flex justify-end pt-1">
            <x-primary-button class="px-6">
                {{ __('Konfirmasi') }}
            </x-primary-button>
        </div>
    </form>
</div>
