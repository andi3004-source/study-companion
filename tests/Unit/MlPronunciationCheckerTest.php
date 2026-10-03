<?php

namespace Tests\Unit;

use App\Services\MlPronunciationChecker;
use App\Services\SttMatchPronunciationChecker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MlPronunciationCheckerTest extends TestCase
{
    private function audioFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pronunciation-test-');
        file_put_contents($path, 'fake-audio-bytes');

        return $path;
    }

    public function test_converts_a_successful_mandarin_response(): void
    {
        Http::fake([
            '*/check/mandarin' => Http::response([
                'is_correct' => true,
                'predicted_tone' => 1,
                'target_tone' => 1,
            ], 200),
        ]);

        $checker = new MlPronunciationChecker(new SttMatchPronunciationChecker, 'http://pronunciation-service.test');

        $result = $checker->check($this->audioFile(), '妈', 'mandarin');

        $this->assertTrue($result['isCorrect']);
    }

    public function test_falls_back_to_stt_match_when_the_service_is_unreachable(): void
    {
        Http::fake([
            '*/check/english' => Http::response([], 500),
        ]);

        $checker = new MlPronunciationChecker(new SttMatchPronunciationChecker, 'http://pronunciation-service.test');

        $result = $checker->check($this->audioFile(), 'Hello', 'english', 'hello');

        // Falls through to SttMatchPronunciationChecker instead of raising/500ing.
        $this->assertTrue($result['isCorrect']);
    }

    public function test_falls_back_to_stt_match_on_connection_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $checker = new MlPronunciationChecker(new SttMatchPronunciationChecker, 'http://pronunciation-service.test');

        $result = $checker->check($this->audioFile(), 'Hello', 'english', 'goodbye');

        // Still gets an answer (from the fallback), not an exception.
        $this->assertFalse($result['isCorrect']);
    }

    public function test_multi_character_mandarin_targets_skip_the_ml_service_entirely(): void
    {
        // tone-me's model only classifies a single syllable's tone — sending
        // it a multi-character clip would score the wrong audio span. This
        // should never even attempt the HTTP call.
        Http::fake();

        $checker = new MlPronunciationChecker(new SttMatchPronunciationChecker, 'http://pronunciation-service.test');

        $result = $checker->check($this->audioFile(), '你好', 'mandarin', '你号', 'nǐ hǎo');

        Http::assertNothingSent();
        $this->assertFalse($result['isCorrect']);
        $this->assertStringContainsString('karakter ke-2', $result['feedback']);
    }
}
