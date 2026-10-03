<?php

use App\Livewire\Actions\Logout;
use App\Livewire\Cards\Deck;
use App\Livewire\Conversation\Chat;
use App\Livewire\Conversation\Picker as ConversationPicker;
use App\Livewire\Dashboard;
use App\Livewire\Kamus\Search;
use App\Livewire\LatihanIsian;
use App\Livewire\Onboarding;
use App\Livewire\ProgressOverview;
use App\Livewire\PronunciationCheck;
use App\Livewire\Ujian\Picker;
use App\Livewire\Ujian\Runner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::post('/logout', function (Request $request, Logout $logout) {
    $logout();

    return redirect('/');
})->middleware(['auth'])->name('logout');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', Onboarding::class)->name('onboarding');

    Route::middleware('onboarded')->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/dashboard/day/{day}', Dashboard::class)->name('dashboard.day');

        Route::get('/cards/{deck?}', Deck::class)->name('cards.index');

        Route::get('/kamus', Search::class)->name('kamus');

        Route::get('/ujian', Picker::class)->name('ujian');
        Route::get('/ujian/{mode}', Runner::class)->name('ujian.run');

        Route::get('/latihan-isian', LatihanIsian::class)->name('latihan-isian');

        Route::get('/conversation', ConversationPicker::class)->name('conversation');
        Route::get('/conversation/{session}', Chat::class)->name('conversation.chat');

        Route::get('/progress', ProgressOverview::class)->name('progress');

        Route::get('/pronunciation-check', PronunciationCheck::class)->name('pronunciation-check');
    });
});

require __DIR__.'/auth.php';
