<?php

namespace Tests\Unit;

use App\Services\SttMatchPronunciationChecker;
use Tests\TestCase;

class SttMatchPronunciationCheckerTest extends TestCase
{
    private SttMatchPronunciationChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new SttMatchPronunciationChecker;
    }

    public function test_matches_when_transcript_is_close_to_target(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', 'Hello, how are you?', 'english', 'hello how are you');

        $this->assertTrue($result['isCorrect']);
    }

    public function test_does_not_match_when_transcript_is_far_from_target(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', 'Hello, how are you?', 'english', 'goodbye see you later');

        $this->assertFalse($result['isCorrect']);
    }

    public function test_is_incorrect_when_nothing_was_heard(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', 'Hello', 'english', '');

        $this->assertFalse($result['isCorrect']);
    }

    public function test_matches_mandarin_characters_exactly(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', '你好', 'mandarin', '你好');

        $this->assertTrue($result['isCorrect']);
    }

    public function test_a_single_wrong_character_fails_even_if_mostly_similar(): void
    {
        // similar_text alone would score "你好" vs "你号" as ~50% similar (still
        // "close"); Mandarin correctness requires an exact character match since
        // each Hanzi carries its own meaning/tone.
        $result = $this->checker->check('/tmp/audio.wav', '你好', 'mandarin', '你号');

        $this->assertFalse($result['isCorrect']);
    }

    public function test_multi_character_mismatch_points_at_the_exact_character(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', '你好', 'mandarin', '你号', 'nǐ hǎo');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringContainsString('karakter ke-2', $result['feedback']);
        $this->assertStringContainsString('好', $result['feedback']);
        $this->assertStringContainsString('号', $result['feedback']);
        $this->assertStringContainsString('nǐ hǎo', $result['feedback']);
    }

    public function test_single_character_mismatch_does_not_use_the_per_character_wording(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', '妈', 'mandarin', '马');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringNotContainsString('karakter ke-', $result['feedback']);
    }

    public function test_punctuation_only_transcript_is_reported_as_an_engine_limitation_not_a_wrong_answer(): void
    {
        // Chrome's zh-CN engine sometimes returns "——" for short, flat-tone
        // syllables — that's the STT engine failing to transcribe anything,
        // not the user mispronouncing the word, so it must not get the
        // "Kurang tepat" wording that implies the user's answer was wrong.
        $result = $this->checker->check('/tmp/audio.wav', '一', 'mandarin', '——');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringNotContainsString('Kurang tepat', $result['feedback']);
        $this->assertStringContainsString('Mesin pengenalan suara kesulitan', $result['feedback']);
    }

    public function test_digit_only_transcript_is_also_reported_as_an_engine_limitation(): void
    {
        // Chrome's zh-CN engine has also been observed misreading the short
        // syllable "一" (yī) as the digit "1111" instead of Hanzi — still not
        // a real transcript of what was said, just a different failure shape.
        $result = $this->checker->check('/tmp/audio.wav', '一', 'mandarin', '1111');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringNotContainsString('Kurang tepat', $result['feedback']);
        $this->assertStringContainsString('Mesin pengenalan suara kesulitan', $result['feedback']);
    }

    public function test_punctuation_only_transcript_is_detected_for_english_too(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', 'a', 'english', '...');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringContainsString('Mesin pengenalan suara kesulitan', $result['feedback']);
    }
}
