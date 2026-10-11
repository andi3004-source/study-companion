<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <h1 class="font-display font-600 text-xl text-ink mb-4">Verifikasi Email</h1>
    <p class="mb-4 text-sm text-ink-soft">
        {{ __('Terima kasih sudah mendaftar! Sebelum mulai, verifikasi emailmu dengan mengklik link yang sudah kami kirim. Jika belum menerima, kami bisa kirim ulang.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 p-3 rounded-xl bg-english-soft text-english text-sm font-600">
            {{ __('Link verifikasi baru sudah dikirim ke emailmu.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between gap-4">
        <x-primary-button wire:click="sendVerification" class="px-5">
            {{ __('Kirim Ulang Email') }}
        </x-primary-button>

        <button wire:click="logout" type="button" class="text-sm text-ink-soft hover:text-ink underline">
            {{ __('Keluar') }}
        </button>
    </div>
</div>
