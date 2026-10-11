<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <h1 class="font-display font-600 text-xl text-ink mb-4">Lupa Kata Sandi?</h1>
    <p class="mb-5 text-sm text-ink-soft">
        {{ __('Masukkan emailmu dan kami akan mengirimkan link untuk reset kata sandi.') }}
    </p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-4">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block w-full" type="email" name="email" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="flex items-center justify-end pt-1">
            <x-primary-button class="px-6">
                {{ __('Kirim Link Reset') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-5 pt-5 border-t border-line-card text-center text-sm text-ink-soft">
        Ingat kata sandimu?
        <a href="{{ route('login') }}" wire:navigate class="text-mandarin font-700 hover:underline ml-1">Masuk</a>
    </div>
</div>
