<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
        $this->call(MandarinHskSeeder::class);
        $this->call(IrregularVerbSeeder::class);
        $this->call(EnglishVocabSeeder::class);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'start_date' => now()->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }
}
