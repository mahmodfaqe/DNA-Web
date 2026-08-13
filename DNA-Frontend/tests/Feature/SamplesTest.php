<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The teaching samples.
 *
 * Whether each sample still *teaches* is checked in Python, by running it
 * through the real analysis and cloning services — see
 * DNA-Backend/tools/build_samples.py, which refuses to write a file whose lesson
 * has stopped working.
 *
 * What is checked here is the half that lives in this application: that every
 * file the manifest promises actually exists, that every key has text in every
 * language, and that the one route the trap sample depends on delivers the
 * reader to a form that is already set up to spring it.
 */
class SamplesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    private function allSamples(): array
    {
        return collect(config('samples'))->flatten(1)->all();
    }

    public function test_every_promised_file_exists(): void
    {
        foreach ($this->allSamples() as $sample) {
            $path = resource_path('samples/' . $sample['file']);

            $this->assertTrue(File::exists($path), "{$sample['file']} is in the manifest but not on disk");
            $this->assertStringStartsWith('>', File::get($path), "{$sample['file']} is not FASTA");
        }
    }

    /**
     * A sample without a question is a sequence nobody knows what to do with.
     * The dataset exists to be a lesson, so text in all three languages is part
     * of the file being present, not a nicety on top of it.
     */
    public function test_every_sample_has_a_question_in_every_language(): void
    {
        foreach (['ku', 'ar', 'en'] as $locale) {
            foreach ($this->allSamples() as $sample) {
                foreach (['title', 'question', 'looking_for'] as $field) {
                    $key = "samples.{$sample['key']}.{$field}";

                    $this->assertNotSame(
                        $key,
                        __($key, [], $locale),
                        "{$key} is missing in {$locale}"
                    );
                }
            }
        }
    }

    public function test_a_sample_downloads_as_fasta(): void
    {
        $response = $this->get(route('samples.download', ['file' => 'variants.fasta']))->assertOk();

        $this->assertStringContainsString('>reference', $response->getContent());
        $this->assertStringContainsString('>variant', $response->getContent());
    }

    /**
     * The whole trap sample depends on this route. Asked to configure the form
     * themselves most readers will choose a different enzyme, never hit the
     * warning, and the exercise teaches nothing.
     */
    public function test_loading_the_trap_sample_arrives_with_the_form_already_set(): void
    {
        $this->get(route('samples.load', ['file' => 'cloning-trap.fasta']))
            ->assertRedirect(route('cloning.index'));

        $this->assertSame('EcoRI', session('_old_input.forward_enzyme'));
        $this->assertSame('XhoI', session('_old_input.reverse_enzyme'));
        $this->assertSame(20, session('_old_input.target_start'));
        $this->assertSame(580, session('_old_input.target_end'));

        // The header line has to be gone: the form takes bases, and the point
        // is that the reader lands on the question rather than on a paste job.
        $this->assertStringNotContainsString('>', session('_old_input.sequence'));
    }

    public function test_the_loaded_sequence_still_contains_the_trap(): void
    {
        $this->get(route('samples.load', ['file' => 'cloning-trap.fasta']));

        $sequence = session('_old_input.sequence');
        $target = substr($sequence, 19, 580 - 19);

        $this->assertStringContainsString(
            'GAATTC',
            $target,
            'the EcoRI site has left the amplified region, so the exercise no longer springs'
        );
    }

    public function test_a_file_outside_the_manifest_is_not_readable(): void
    {
        // Whitelisted against the manifest rather than sanitised, so a path
        // that escapes the directory never gets as far as being cleaned up.
        $this->get('/en/samples/' . urlencode('../../.env'))->assertNotFound();
        $this->get(route('samples.download', ['file' => 'nothing.fasta']))->assertNotFound();
    }

    public function test_only_cloning_samples_can_prefill_the_cloning_form(): void
    {
        $this->get(route('samples.load', ['file' => 'gc-skew.fasta']))->assertNotFound();
    }

    public function test_the_sample_list_appears_on_both_tabs_it_belongs_to(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee(__('samples.heading'), false)
            ->assertSee(__('samples.gc_skew.title'), false);

        $this->get('/en/cloning')
            ->assertOk()
            ->assertSee(__('samples.cloning_trap.title'), false);
    }
}
