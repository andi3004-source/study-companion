<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Sleep;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Gemini retries wait between attempts; no test should really sleep.
        // syncWithCarbon lets the retry budget see the faked waits.
        Sleep::fake(syncWithCarbon: true);
    }
}
