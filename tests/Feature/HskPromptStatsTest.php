<?php

namespace Tests\Feature;

use App\Console\Commands\HskPromptStats;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HskPromptStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prints_the_size_of_the_hsk1_list_and_lock_block(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->artisan('hsk:prompt-stats')
            ->expectsOutputToContain('HSK1 list: 150 words')
            ->expectsOutputToContain('Whole lock block (instruction + list):')
            ->assertSuccessful();
    }

    public function test_says_so_when_there_is_no_hsk1_data(): void
    {
        $this->artisan('hsk:prompt-stats')
            ->expectsOutputToContain('adds nothing')
            ->assertSuccessful();
    }

    public function test_token_estimate_counts_hanzi_individually_and_latin_text_per_four_characters(): void
    {
        $this->assertSame(3, HskPromptStats::estimateTokens('你 好')); // 2 hanzi + 1 space
        $this->assertSame(2, HskPromptStats::estimateTokens('abcdefgh'));
    }
}
