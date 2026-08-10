<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Views reference @vite, which throws if no manifest exists. Tests should
        // not require `npm run build` to have been run first — they are testing
        // routing, translation and persistence, not the asset pipeline.
        $this->withoutVite();
    }
}
