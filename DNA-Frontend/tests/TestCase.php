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

        // Symfony's Request::create(), which the test client builds every request
        // with, invents `Accept-Language: en-us,en;q=0.5` when the test did not
        // ask for one. That silently turns "a visitor who stated no preference"
        // into "a visitor who asked for English", so the fallback language could
        // never be reached. Tests that care about content negotiation set the
        // header themselves, and that still wins over this.
        $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => '']);
    }
}
