<?php

namespace Tests\Unit;

use App\Services\AnswerChecker;
use Tests\TestCase;

class AnswerCheckerTest extends TestCase
{
    private AnswerChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new AnswerChecker;
    }

    public function test_pinyin_matches_regardless_of_tone_marks(): void
    {
        $this->assertTrue($this->checker->pinyinMatches('hao', 'hǎo'));
        $this->assertTrue($this->checker->pinyinMatches('HAO', 'hǎo'));
        $this->assertTrue($this->checker->pinyinMatches(' kan bing ', 'kànbìng'));
    }

    public function test_pinyin_does_not_match_different_syllables(): void
    {
        $this->assertFalse($this->checker->pinyinMatches('hai', 'hǎo'));
    }

    public function test_meaning_matches_any_variant(): void
    {
        $this->assertTrue($this->checker->meaningMatches('suka', 'cinta / suka'));
        $this->assertTrue($this->checker->meaningMatches('cinta', 'cinta / suka'));
        $this->assertFalse($this->checker->meaningMatches('benci', 'cinta / suka'));
    }

    public function test_meaning_does_not_match_blank_input(): void
    {
        $this->assertFalse($this->checker->meaningMatches('  ', 'cinta / suka'));
    }

    public function test_hanzi_requires_exact_match_but_trims_whitespace(): void
    {
        $this->assertTrue($this->checker->hanziMatches(' 你好 ', '你好'));
        $this->assertFalse($this->checker->hanziMatches('你号', '你好'));
    }
}
