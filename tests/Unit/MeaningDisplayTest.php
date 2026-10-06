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
        $this->assertNull(MeaningDisplay::primary(null, 'airport; airfield; (slang) service provider'));
        $this->assertFalse(MeaningDisplay::primaryIsEnglish(null, 'surname Du'));
    }

    public function test_surname_only_counts_at_the_start_of_the_gloss(): void
    {
        $this->assertFalse(MeaningDisplay::isUnreliableGloss('family name; surname'));
        $this->assertNull(MeaningDisplay::primary(null, null));
    }
}
