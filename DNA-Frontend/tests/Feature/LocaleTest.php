<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_a_supported_language(): void
    {
        $this->get('/')->assertRedirect('/' . Locales::FALLBACK);
    }

    public function test_browser_language_is_honoured_on_first_visit(): void
    {
        $this->withHeader('Accept-Language', 'ar,en;q=0.8')
            ->get('/')
            ->assertRedirect('/ar');
    }

    public function test_unknown_language_segment_falls_back_instead_of_404(): void
    {
        $this->get('/fr')->assertRedirect('/' . Locales::FALLBACK);
    }

    /**
     * Direction and language tags are the two things a screen reader and a text
     * shaper actually rely on, so they are asserted per locale rather than
     * assumed.
     */
    public function test_each_language_renders_with_the_right_tag_and_direction(): void
    {
        $expected = [
            'ku' => ['ckb', 'rtl'],
            'ar' => ['ar', 'rtl'],
            'en' => ['en', 'ltr'],
        ];

        foreach ($expected as $locale => [$tag, $direction]) {
            $this->get("/{$locale}")
                ->assertOk()
                ->assertSee('lang="' . $tag . '"', false)
                ->assertSee('dir="' . $direction . '"', false);
        }
    }

    public function test_every_page_advertises_all_three_languages(): void
    {
        $response = $this->get('/en')->assertOk();

        foreach (Locales::codes() as $code) {
            $response->assertSee('hreflang="' . Locales::tag($code) . '"', false);
        }
    }

    /**
     * The regression this guards: previously results lived only in a POST
     * response, so changing language discarded them.
     */
    public function test_switching_language_keeps_you_on_the_same_result(): void
    {
        $analysis = Analysis::create([
            'filename' => 'sample.fasta',
            'size_bytes' => 120,
            'checksum' => 'abc123',
            'gene_count' => 1,
            'payload' => $this->payload(),
        ]);

        $this->get("/ku/result/{$analysis->id}")
            ->assertOk()
            ->assertSee("/ar/result/{$analysis->id}", false)
            ->assertSee("/en/result/{$analysis->id}", false);
    }

    public function test_interface_text_actually_differs_between_languages(): void
    {
        $kurdish = $this->get('/ku')->assertOk()->getContent();
        $arabic = $this->get('/ar')->assertOk()->getContent();
        $english = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString(trans('common.hero.submit', [], 'ku'), $kurdish);
        $this->assertStringContainsString(trans('common.hero.submit', [], 'ar'), $arabic);
        $this->assertStringContainsString(trans('common.hero.submit', [], 'en'), $english);
    }

    /**
     * A missing key renders as "common.hero.submit" on the page, which looks
     * broken and is easy to miss in a language you do not read. This compares
     * every locale's key set against the reference locale.
     */
    public function test_no_translation_keys_are_missing_from_any_language(): void
    {
        $reference = $this->flatten(lang_path('en'));

        foreach (['ku', 'ar'] as $locale) {
            $keys = $this->flatten(lang_path($locale));

            $this->assertSame(
                [],
                array_values(array_diff($reference, $keys)),
                "Missing translation keys in [{$locale}]."
            );

            $this->assertSame(
                [],
                array_values(array_diff($keys, $reference)),
                "Extra translation keys in [{$locale}] that English does not define."
            );
        }
    }

    /** @return string[] */
    private function flatten(string $directory): array
    {
        $keys = [];

        foreach (glob($directory . '/*.php') as $file) {
            $group = pathinfo($file, PATHINFO_FILENAME);
            $keys = array_merge($keys, $this->dot(require $file, $group));
        }

        sort($keys);

        return $keys;
    }

    /** @return string[] */
    private function dot(array $items, string $prefix): array
    {
        $keys = [];

        foreach ($items as $key => $value) {
            $path = $prefix . '.' . $key;
            $keys = is_array($value)
                ? array_merge($keys, $this->dot($value, $path))
                : array_merge($keys, [$path]);
        }

        return $keys;
    }

    private function payload(): array
    {
        return [
            'status' => 'success',
            'checksum' => 'abc123',
            'summary' => [
                'total_genes' => 1, 'total_bases' => 12, 'average_length' => 12,
                'average_gc' => 50.0, 'min_length' => 12, 'max_length' => 12,
                'min_gc' => 50.0, 'max_gc' => 50.0, 'unknown_bases' => 0,
                'unknown_fraction' => 0.0, 'records_with_ambiguity' => 0,
            ],
            'genes' => [[
                'id' => 'gene_1', 'description' => 'gene_1', 'length' => 12,
                'gc_content' => 50.0, 'at_content' => 50.0, 'gc_skew' => 0.0,
                'melting_temp' => ['value' => 38.0, 'method' => 'wallace', 'reliable' => true],
                'molecular_weight' => 7000.0,
                'base_composition' => ['A' => 3, 'T' => 3, 'C' => 3, 'G' => 3, 'N' => 0, 'ambiguous' => 0, 'known_bases' => 12, 'unknown_bases' => 0],
                'ambiguity_codes' => [],
                'quality' => ['unknown_fraction' => 0.0, 'has_ambiguity' => false],
                'orfs' => ['count' => 0, 'truncated' => false, 'scanned_bp' => 12, 'longest' => null, 'top' => []],
                'protein_length' => 0, 'protein_sequence' => '', 'codon_usage' => [],
            ]],
            'comparisons' => [],
            'limits' => ['align_max_bp' => 3000, 'orf_max_scan_bp' => 200000, 'tm_nn_max_bp' => 50],
        ];
    }
}
