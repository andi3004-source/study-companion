<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates (or updates) the local demo account from config/demo.php: verified
 * email, onboarding done, no practice/session data. Runs only when
 * DEMO_ACCOUNT_ENABLED=true and never in production.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo.enabled')) {
            $this->command?->warn('Akun demo dimatikan (DEMO_ACCOUNT_ENABLED=false); tidak ada yang dibuat.');

            return;
        }

        if (app()->environment('production')) {
            throw new RuntimeException('Akun demo tidak boleh dibuat di environment production.');
        }

        $password = config('demo.password');
        if (! is_string($password) || $password === '') {
            throw new RuntimeException('DEMO_ACCOUNT_PASSWORD kosong. Isi kata sandi khusus demo di .env, lalu jalankan ulang seeder ini.');
        }

        $user = User::updateOrCreate(
            ['email' => config('demo.email')],
            [
                'name' => 'Akun Demo',
                'password' => $password,
                'start_date' => now()->toDateString(),
                'track' => 'both',
                'onboarded' => true,
            ],
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $this->command?->info('Akun demo siap: '.$user->email);
    }
}
