<?php

namespace Tests\Unit;

use App\Support\Locales;
use PHPUnit\Framework\TestCase;

class LocalesTest extends TestCase
{
    public function test_only_declared_languages_are_supported(): void
    {
        $this->assertTrue(Locales::supports('ku'));
        $this->assertTrue(Locales::supports('ar'));
        $this->assertTrue(Locales::supports('en'));

        $this->assertFalse(Locales::supports('fr'));
        $this->assertFalse(Locales::supports(null));
        $this->assertFalse(Locales::supports(''));
    }

    public function test_kurdish_uses_the_central_kurdish_tag(): void
    {
        // `ku` is a macrolanguage; `ckb` is what a text shaper and a search
        // engine actually need in order to treat this as Sorani.
        $this->assertSame('ckb', Locales::tag('ku'));
    }

    public function test_direction_is_declared_per_language(): void
    {
        $this->assertSame('rtl', Locales::direction('ku'));
        $this->assertSame('rtl', Locales::direction('ar'));
        $this->assertSame('ltr', Locales::direction('en'));
    }

    public function test_every_language_declares_a_complete_metadata_set(): void
    {
        foreach (Locales::SUPPORTED as $code => $meta) {
            foreach (['tag', 'dir', 'script', 'native', 'english'] as $key) {
                $this->assertArrayHasKey($key, $meta, "[{$code}] is missing [{$key}].");
                $this->assertNotSame('', $meta[$key]);
            }
        }
    }
}
