<?php

namespace Tests\Unit;

use App\Support\MeaningDisplay;
use Tests\TestCase;

class MeaningDisplayTest extends TestCase
{
    public function test_indonesian_meaning_always_wins(): void
    {
        $this->assertSame('semua / juga', MeaningDisplay::primary('semua / juga', 'surname Du'));
        $this->assertFalse(MeaningDisplay::primaryIsEnglish('semua / juga', 'all'));
    }

    public function test_falls_back_to_a_trustworthy_english_gloss(): void
    {
        $this->assertSame('to listen', MeaningDisplay::primary(null, 'to listen'));
        $this->assertSame('to listen', MeaningDisplay::primary('', 'to listen'));
        $this->assertTrue(MeaningDisplay::primaryIsEnglish(null, 'to listen'));
    }

    public function test_surname_and_slang_glosses_are_never_the_main_meaning(): void
    {
        $this->assertNull(MeaningDisplay::primary(null, 'surname Du'));
        $this->assertNull(MeaningDisplay::primary(null, 'Surname Huang or Hwang'));
        $this->assertNull(MeaningDisplay::primary(null, 'surname Ma; abbr. for Malaysia'));
        $this->assertNull(MeaningDisplay::primary(null, '(slang) to cheat; green'));
        $this->assertFalse(MeaningDisplay::primaryIsEnglish(null, 'surname Du'));
    }

    public function test_a_slang_sense_after_the_first_one_does_not_hide_the_gloss(): void
    {
        // 机场 and 绿: normal words whose dictionary entry lists a slang sense later on.
        $airport = 'airport; airfield; (slang) service provider for Shadowsocks';
        $green = "green; (slang) (derived from 绿帽子) to cheat on (one's spouse)";

        $this->assertFalse(MeaningDisplay::isUnreliableGloss($airport));
        $this->assertFalse(MeaningDisplay::isUnreliableGloss($green));
        $this->assertSame($airport, MeaningDisplay::primary(null, $airport));
    }

    public function test_surname_only_counts_at_the_start_of_the_gloss(): void
    {
        $this->assertFalse(MeaningDisplay::isUnreliableGloss('family name; surname'));
        $this->assertNull(MeaningDisplay::primary(null, null));
    }
}
