<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * phpunit.xml pins every behaviour-changing setting, so tests never depend
 * on the developer's .env or on variables set in the shell.
 */
class TestEnvironmentIsolationTest extends TestCase
{
    public function test_behaviour_changing_settings_are_pinned(): void
    {
        $this->assertFalse(config('scan.enabled'));
        $this->assertFalse(config('demo.enabled'));
        $this->assertEmpty(config('demo.password'));
        $this->assertEmpty(config('services.gemini.key'));
        $this->assertEmpty(config('services.anthropic.key'));
        $this->assertEmpty(config('services.langflow.url'));
        $this->assertSame('', config('game.items_path'));
        $this->assertSame('ml', config('services.pronunciation_service.driver'));
        $this->assertTrue(config('hsk.level_lock'));
        $this->assertSame(6, config('scan.rate_limit_per_minute'));
    }
}
