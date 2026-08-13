<?php

namespace Tests\Feature;

use App\Models\CloningPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A backend response shaped like the real one.
     *
     * Written out rather than recorded, so a change to the backend's contract
     * shows up here as a failing assertion instead of as a page that renders
     * blanks.
     *
     * @return array<string, mixed>
     */
    private function backendResult(int $cutsInsideAmplicon = 0): array
    {
        return [
            'ok' => true,
            'digest' => [
                'length' => 600,
                'topology' => 'linear',
                'searched' => 24,
                'cutter_count' => 2,
                'unique_cutters' => ['EcoRI'],
                'non_cutters' => ['NotI', 'XhoI'],
                'enzymes' => [
                    [
                        'enzyme' => 'EcoRI',
                        'site' => 'GAATTC',
                        'recognition_length' => 6,
                        'cuts_outside_site' => false,
                        'overhang' => ['kind' => 'five_prime', 'length' => 4, 'sequence' => 'AATT'],
                        'cut_count' => 1,
                        'sites' => [['site_start' => 120, 'cut_after' => 120, 'cut_position' => 121]],
                        'fragments' => [480, 120],
                        'invisible_fragments' => [],
                        'unresolvable_pairs' => [],
                        'suppliers' => 5,
                    ],
                    [
                        'enzyme' => 'BamHI',
                        'site' => 'GGATCC',
                        'recognition_length' => 6,
                        'cuts_outside_site' => false,
                        'overhang' => ['kind' => 'five_prime', 'length' => 4, 'sequence' => 'GATC'],
                        'cut_count' => 2,
                        'sites' => [
                            ['site_start' => 200, 'cut_after' => 200, 'cut_position' => 201],
                            ['site_start' => 410, 'cut_after' => 410, 'cut_position' => 411],
                        ],
                        'fragments' => [210, 200, 190],
                        'invisible_fragments' => [],
                        'unresolvable_pairs' => [['larger' => 210, 'smaller' => 200]],
                        'suppliers' => 8,
                    ],
                    [
                        'enzyme' => 'NotI',
                        'site' => 'GCGGCCGC',
                        'recognition_length' => 8,
                        'cuts_outside_site' => false,
                        'overhang' => ['kind' => 'five_prime', 'length' => 4, 'sequence' => 'GGCC'],
                        'cut_count' => 0,
                        'sites' => [],
                        'fragments' => [600],
                        'invisible_fragments' => [],
                        'unresolvable_pairs' => [],
                        'suppliers' => 4,
                    ],
                ],
            ],
            'primers' => [
                'forward' => [
                    'sequence' => 'ATGGCTAGCAAAGGAGAAGA',
                    'length' => 20, 'start' => 20, 'end' => 39, 'direction' => 'forward',
                    'tm' => 59.8, 'gc_percent' => 50.0, 'gc_clamp' => true,
                    'longest_run' => 3, 'self_dimer_bp' => 3, 'hairpin_stem_bp' => 2,
                    'matches_in_template' => 1, 'penalty' => 0.4, 'flags' => [],
                ],
                'reverse' => [
                    'sequence' => 'TTAGGTACCGCTAGCTAGCT',
                    'length' => 20, 'start' => 541, 'end' => 560, 'direction' => 'reverse',
                    'tm' => 60.4, 'gc_percent' => 55.0, 'gc_clamp' => true,
                    'longest_run' => 2, 'self_dimer_bp' => 4, 'hairpin_stem_bp' => 3,
                    'matches_in_template' => 1, 'penalty' => 0.6, 'flags' => [],
                ],
                'pair' => ['tm_delta' => 0.6, 'cross_dimer_bp' => 3, 'annealing_suggestion' => 54.8],
                'amplicon' => [
                    'start' => 20, 'end' => 560, 'length' => 541, 'gc_percent' => 48.2,
                    'sequence' => str_repeat('ATCG', 135) . 'A',
                ],
                'conditions' => [
                    'primer_nM' => 250.0, 'na_mM' => 50.0, 'mg_mM' => 1.5, 'dntp_mM' => 0.2,
                    'model' => 'santalucia_1998_nearest_neighbour',
                    'salt_correction' => 'owczarzy_2008',
                ],
                'criteria' => [
                    'target_tm' => 60.0, 'length_range' => [18, 30],
                    'gc_range' => [40.0, 60.0], 'max_pair_tm_delta' => 3.0,
                ],
            ],
            'tails' => [
                'clamp' => 'TTTTTT',
                'ends' => [
                    'forward' => [
                        'enzyme' => 'EcoRI', 'site' => 'GAATTC',
                        'overhang' => ['kind' => 'five_prime', 'length' => 4, 'sequence' => 'AATT'],
                        'sequence' => 'TTTTTTGAATTCATGGCTAGCAAAGGAGAAGA',
                        'length' => 32,
                        'binding_region' => 'ATGGCTAGCAAAGGAGAAGA',
                        'binding_tm' => 59.8,
                        'full_length_tm' => 66.2,
                        'cuts_inside_amplicon' => $cutsInsideAmplicon,
                    ],
                ],
            ],
            'diagnostics' => [
                ['code' => 'not_a_specificity_check', 'severity' => 'info', 'params' => [], 'span' => null],
                ['code' => 'panel_selected', 'severity' => 'info', 'params' => ['panel' => 'teaching', 'enzymes' => 24], 'span' => null],
            ],
            'diagnostic_counts' => ['error' => 0, 'warning' => 0, 'info' => 2],
        ];
    }

    private function fakeBackend(array $result): void
    {
        Http::fake([
            '*/api/v1/cloning' => Http::response($result, 200),
        ]);
    }

    private function plan(array $overrides = []): CloningPlan
    {
        return CloningPlan::create(array_merge([
            'label' => 'pUC19 insert',
            'template_length' => 600,
            'panel' => 'teaching',
            'circular' => false,
            'forward_enzyme' => 'EcoRI',
            'succeeded' => true,
            'result' => $this->backendResult(),
        ], $overrides));
    }

    // -- The form --------------------------------------------------------

    public function test_the_tab_is_reachable_in_every_language(): void
    {
        foreach (['ku', 'ar', 'en'] as $locale) {
            $this->get("/{$locale}/cloning")
                ->assertOk()
                ->assertSee(__('cloning.hero.title', [], $locale), false);
        }
    }

    public function test_a_sequence_is_required(): void
    {
        $this->post('/en/cloning', ['panel' => 'teaching'])
            ->assertSessionHasErrors('sequence');
    }

    public function test_an_unknown_panel_is_refused(): void
    {
        $this->post('/en/cloning', [
            'sequence' => str_repeat('ATCG', 50),
            'panel' => 'everything',
        ])->assertSessionHasErrors('panel');
    }

    public function test_a_region_that_ends_before_it_starts_is_refused(): void
    {
        $this->post('/en/cloning', [
            'sequence' => str_repeat('ATCG', 50),
            'panel' => 'teaching',
            'target_start' => 400,
            'target_end' => 100,
        ])->assertSessionHasErrors('target_end');
    }

    /**
     * A reader pasting from a genome browser pastes the header line too, and
     * being told "invalid character: >" is not help.
     */
    public function test_a_pasted_fasta_record_has_its_header_and_whitespace_stripped(): void
    {
        $this->fakeBackend($this->backendResult());

        $this->post('/en/cloning', [
            'sequence' => ">gene_1 some description\nATCG ATCG\natcgATCG\n" . str_repeat('ATCG', 20),
            'panel' => 'teaching',
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            $sent = $request->data()['sequence'];

            return ! str_contains($sent, '>')
                && ! str_contains($sent, ' ')
                && $sent === strtoupper($sent);
        });
    }

    public function test_a_successful_plan_is_stored_and_redirects_to_it(): void
    {
        $this->fakeBackend($this->backendResult());

        $response = $this->post('/en/cloning', [
            'sequence' => str_repeat('ATCG', 150),
            'panel' => 'teaching',
            'label' => 'my insert',
            'design_primers' => '1',
        ]);

        $plan = CloningPlan::firstOrFail();
        $response->assertRedirect(route('cloning.show', ['plan' => $plan->id]));
        $this->assertSame('my insert', $plan->label);
        $this->assertSame(600, $plan->template_length);
        $this->assertTrue($plan->succeeded);
    }

    public function test_tails_are_only_sent_when_primers_are_being_designed(): void
    {
        $this->fakeBackend($this->backendResult());

        $this->post('/en/cloning', [
            'sequence' => str_repeat('ATCG', 150),
            'panel' => 'teaching',
            'forward_enzyme' => 'EcoRI',
            // design_primers unchecked: there is no amplicon to put a tail on.
        ]);

        Http::assertSent(fn ($request) => ! array_key_exists('tails', $request->data()));
    }

    public function test_a_backend_failure_returns_the_reader_to_the_form_with_a_translated_message(): void
    {
        Http::fake([
            '*/api/v1/cloning' => Http::response(
                ['error' => ['code' => 'rate_limited', 'params' => ['retry_after' => 12]]],
                429
            ),
        ]);

        $this->post('/en/cloning', ['sequence' => str_repeat('ATCG', 150), 'panel' => 'teaching'])
            ->assertRedirect()
            ->assertSessionHasErrors('sequence');

        $this->assertSame(0, CloningPlan::count());
    }

    // -- The result page --------------------------------------------------

    public function test_the_result_page_leads_with_the_two_lists_a_strategy_is_chosen_from(): void
    {
        $plan = $this->plan();

        $this->get(route('cloning.show', ['plan' => $plan->id]))
            ->assertOk()
            ->assertSee(__('cloning.digest.unique_title'), false)
            ->assertSee(__('cloning.digest.absent_title'), false)
            ->assertSee('EcoRI')
            ->assertSee('NotI');
    }

    public function test_a_tail_enzyme_that_does_not_cut_the_fragment_is_marked_safe(): void
    {
        $plan = $this->plan();

        $this->get(route('cloning.show', ['plan' => $plan->id]))
            ->assertOk()
            ->assertSee(__('cloning.tails.cuts_inside_safe'), false);
    }

    /**
     * The warning this whole feature exists to produce. If it can ever fail to
     * reach the page, the tab has no reason to exist.
     */
    public function test_a_tail_enzyme_that_cuts_the_fragment_is_shown_as_a_problem(): void
    {
        $plan = $this->plan(['result' => $this->backendResult(cutsInsideAmplicon: 2)]);

        $this->get(route('cloning.show', ['plan' => $plan->id]))
            ->assertOk()
            ->assertSee(__('cloning.tails.cuts_inside', ['count' => 2]), false)
            ->assertDontSee(__('cloning.tails.cuts_inside_safe'), false);
    }

    public function test_both_temperatures_are_shown_for_a_tailed_primer(): void
    {
        $plan = $this->plan();

        $this->get(route('cloning.show', ['plan' => $plan->id]))
            ->assertOk()
            ->assertSee('59.8')          // binding region alone
            ->assertSee('66.2')          // whole primer
            ->assertSee(__('cloning.tails.why_two'), false);
    }

    public function test_the_conditions_behind_every_temperature_are_on_the_page(): void
    {
        $plan = $this->plan();

        $this->get(route('cloning.show', ['plan' => $plan->id]))
            ->assertOk()
            ->assertSee(__('cloning.conditions.model_value'), false)
            ->assertSee('250')
            ->assertSee(__('cloning.conditions.note'), false);
    }

    public function test_what_the_tool_does_not_do_is_stated_on_the_page_not_only_in_the_docs(): void
    {
        $plan = $this->plan();

        $this->get(route('cloning.show', ['plan' => $plan->id]))
            ->assertOk()
            ->assertSee(__('cloning.limits.specificity'), false)
            ->assertSee(__('cloning.limits.methylation'), false);
    }

    /**
     * The 500 that took a whole result page down.
     *
     * A warning about two gel bands being hard to tell apart carried a nested
     * array, the shared renderer joined it into text, and the reader lost the
     * primers, the enzyme table and everything else on the page — over the
     * least important note on it. The parameter shape is fixed at the backend
     * boundary; this asserts the renderer no longer depends on that being true,
     * because five tools feed it and a new code can arrive with any shape.
     */
    public function test_a_diagnostic_with_a_nested_parameter_does_not_take_the_page_down(): void
    {
        $result = $this->backendResult();
        $result['diagnostics'][] = [
            'code' => 'fragments_unresolvable',
            'severity' => 'warning',
            'params' => ['enzyme' => 'BamHI', 'pairs' => [['larger' => 210, 'smaller' => 200]]],
            'span' => 'BamHI',
        ];
        $result['diagnostic_counts'] = ['error' => 0, 'warning' => 1, 'info' => 2];

        $plan = $this->plan(['result' => $result]);

        $this->get(route('cloning.show', ['plan' => $plan->id]))
            ->assertOk()
            // The rest of the page still has to be there, which is the point.
            ->assertSee(__('cloning.primers.title'), false);
    }

    public function test_the_band_warning_names_both_sizes(): void
    {
        $result = $this->backendResult();
        $result['diagnostics'][] = [
            'code' => 'fragments_unresolvable',
            'severity' => 'warning',
            'params' => ['enzyme' => 'BamHI', 'larger' => 210, 'smaller' => 200, 'pairs' => 1],
            'span' => 'BamHI',
        ];
        $result['diagnostic_counts'] = ['error' => 0, 'warning' => 1, 'info' => 2];

        $plan = $this->plan(['result' => $result]);

        $this->get(route('cloning.show', ['plan' => $plan->id]))
            ->assertOk()
            ->assertSee('210')
            ->assertSee('200');
    }

    public function test_diagnostics_are_rendered_in_the_readers_language(): void
    {
        $plan = $this->plan();

        $this->get("/ku/plan/{$plan->id}")
            ->assertOk()
            ->assertSee(__('cloning.messages.not_a_specificity_check', [], 'ku'), false)
            ->assertDontSee('not_a_specificity_check');
    }

    // -- Exports ----------------------------------------------------------

    /**
     * The tailed sequence is what gets synthesised. Ordering the binding region
     * alone is the specific mistake this file exists to prevent, and the two
     * look almost identical on screen.
     */
    public function test_the_primer_order_exports_the_tailed_sequence(): void
    {
        $plan = $this->plan();

        $body = $this->get(route('cloning.csv', ['plan' => $plan->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('TTTTTTGAATTCATGGCTAGCAAAGGAGAAGA', $body);
    }

    public function test_the_order_falls_back_to_the_bare_primer_when_there_is_no_tail(): void
    {
        $result = $this->backendResult();
        unset($result['tails']);
        $plan = $this->plan(['result' => $result]);

        $body = $this->get(route('cloning.csv', ['plan' => $plan->id]))->streamedContent();

        $this->assertStringContainsString('ATGGCTAGCAAAGGAGAAGA', $body);
        $this->assertStringContainsString('TTAGGTACCGCTAGCTAGCT', $body);
    }

    public function test_the_amplicon_downloads_as_fasta(): void
    {
        $plan = $this->plan();

        $response = $this->get(route('cloning.fasta', ['plan' => $plan->id]))->assertOk();

        $this->assertStringStartsWith('>amplicon_', $response->getContent());
        $this->assertStringContainsString('length=541', $response->getContent());
    }

    public function test_a_plan_with_no_amplicon_has_no_fasta_to_download(): void
    {
        $result = $this->backendResult();
        unset($result['primers']);
        $plan = $this->plan(['result' => $result]);

        $this->get(route('cloning.fasta', ['plan' => $plan->id]))->assertNotFound();
    }

    public function test_plans_expire_with_everything_else(): void
    {
        $this->plan()->forceFill(['created_at' => now()->subDays(100)])->save();
        $this->plan();

        $this->artisan('analyses:prune', ['--days' => 30]);

        $this->assertSame(1, CloningPlan::count());
    }
}
