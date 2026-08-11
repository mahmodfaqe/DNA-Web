<?php

namespace Tests\Feature;

use App\Models\Simulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimulatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A stand-in for the analysis service's response.
     *
     * Deliberately hand-written rather than captured from a real run: it is the
     * *contract* between the two services, and a fixture recorded from the
     * backend would keep passing after a rename that breaks the page.
     */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'ok' => true,
            'request' => [
                'preset' => 'crosstalk_pair',
                'cells' => 60,
                'minutes' => 60.0,
                'induction' => 1.0,
                'crosstalk' => 0.4,
                'variability' => 0.2,
                'resource_coupling' => true,
                'seed' => 4242,
            ],
            'network' => [
                'preset' => 'crosstalk_pair',
                'genes' => [
                    ['id' => 'A', 'label' => 'reporter_a', 'k_on' => 0.02, 'k_off' => 0.02,
                     'k_tx' => 0.05, 'k_tl' => 0.05, 'd_m' => 0.0058, 'd_p' => 0.00035,
                     'leak' => 0.02, 'basal' => 0.0, 'burst_size' => 8.62,
                     'protein_half_life_minutes' => 33.0],
                    ['id' => 'B', 'label' => 'reporter_b', 'k_on' => 0.02, 'k_off' => 0.02,
                     'k_tx' => 0.05, 'k_tl' => 0.05, 'd_m' => 0.0058, 'd_p' => 0.00035,
                     'leak' => 0.02, 'basal' => 0.12, 'burst_size' => 8.62,
                     'protein_half_life_minutes' => 33.0],
                ],
                'links' => [['source' => 'A', 'target' => 'B', 'weight' => 0.6,
                             'k_half' => 400.0, 'hill' => 1, 'kind' => 'crosstalk']],
                'inducers' => [['target' => 'A', 'weight' => 1.0, 'kind' => 'cognate']],
                'ribosome_capacity' => 200.0,
                'dual_reporters' => null,
                'bistable_pair' => null,
            ],
            'time' => [
                'grid_minutes' => [0.0, 15.0, 30.0, 45.0, 60.0],
                'burn_in_index' => 2,
                'burn_in_minutes' => 24.0,
                'total_minutes' => 60.0,
            ],
            'trajectories' => [
                'A' => ['mean' => [500, 520, 540, 530, 545], 'sd' => [90, 95, 100, 98, 102],
                        'examples' => [[480, 610, 500, 470, 590]], 'mrna_mean' => [4.2, 4.4, 4.3, 4.5, 4.4]],
                'B' => ['mean' => [300, 310, 325, 318, 330], 'sd' => [70, 74, 80, 78, 82],
                        'examples' => [[260, 380, 300, 290, 350]], 'mrna_mean' => [2.5, 2.6, 2.6, 2.7, 2.6]],
            ],
            'distributions' => [
                'A' => ['edges' => [300, 400, 500, 600, 700], 'counts' => [10, 40, 60, 30], 'min' => 300, 'max' => 700],
                'B' => ['edges' => [150, 250, 350, 450, 550], 'counts' => [15, 50, 55, 20], 'min' => 150, 'max' => 550],
            ],
            'statistics' => [
                'A' => [
                    'id' => 'A', 'label' => 'reporter_a', 'mean_protein' => 540.0, 'sd_protein' => 100.0,
                    'cv' => 0.185, 'cv_squared' => 0.0342, 'fano' => 18.5, 'mean_mrna' => 4.3,
                    'fano_mrna' => 1.45, 'burst_size' => 8.62, 'analytic_fano' => 9.13,
                    'drift' => 0.01, 'drift_threshold' => 0.12, 'samples' => 3600,
                    'effective_samples' => 60.0, 'precision' => 0.18,
                    'noise_budget' => ['floor' => 0.0019, 'bursting' => 0.0151, 'extrinsic' => 0.04,
                                       'promoter' => 0.0, 'coupling' => -0.0228, 'total' => 0.0342],
                ],
                'B' => [
                    'id' => 'B', 'label' => 'reporter_b', 'mean_protein' => 325.0, 'sd_protein' => 90.0,
                    'cv' => 0.277, 'cv_squared' => 0.0767, 'fano' => 24.9, 'mean_mrna' => 2.6,
                    'fano_mrna' => 1.5, 'burst_size' => 8.62, 'analytic_fano' => 9.13,
                    'drift' => 0.01, 'drift_threshold' => 0.12, 'samples' => 3600,
                    'effective_samples' => 60.0, 'precision' => 0.18,
                    'noise_budget' => ['floor' => 0.0031, 'bursting' => 0.0248, 'extrinsic' => 0.04,
                                       'promoter' => 0.089, 'coupling' => -0.0802, 'total' => 0.0767],
                ],
            ],
            'crosstalk' => [
                'genes' => ['A', 'B'],
                'attribution' => [
                    'A' => ['transcripts' => 5200.0, 'cognate' => 0.98, 'crosstalk' => 0.0, 'leak' => 0.02],
                    'B' => ['transcripts' => 3100.0, 'cognate' => 0.26, 'crosstalk' => 0.69, 'leak' => 0.05],
                ],
                'correlation' => [[1.0, 0.503], [0.503, 1.0]],
                'partial' => [[1.0, 0.058], [0.058, 1.0]],
                'samples' => 4320,
            ],
            'decomposition' => null,
            'switching' => null,
            'performance' => [
                'cells' => 60, 'events' => 296990, 'control_ensemble' => true,
                'availability' => 0.9662, 'wall_ms' => 383.5,
            ],
            'diagnostics' => [
                ['code' => 'crosstalk_dominates', 'severity' => 'warning',
                 'params' => ['gene' => 'B', 'percent' => 69.0], 'span' => 'B'],
                ['code' => 'seed_recorded', 'severity' => 'info', 'params' => ['seed' => 4242], 'span' => null],
            ],
            'diagnostic_counts' => ['error' => 0, 'warning' => 1, 'info' => 1],
        ], $overrides);
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'preset' => 'crosstalk_pair',
            'cells' => 60,
            'minutes' => 60,
            'induction' => 1.0,
            'crosstalk' => 0.4,
            'variability' => 0.2,
            'resource_coupling' => '1',
        ], $overrides);
    }

    private function stored(array $overrides = []): Simulation
    {
        $result = $this->payload($overrides);

        return Simulation::create([
            'preset' => $result['request']['preset'],
            'cells' => $result['request']['cells'],
            'minutes' => (int) $result['request']['minutes'],
            'seed' => $result['request']['seed'],
            'succeeded' => $result['ok'],
            'result' => $result,
        ]);
    }

    // ----------------------------------------------------------------------
    // The tab exists and connects to the others
    // ----------------------------------------------------------------------

    public function test_the_simulator_tab_renders_in_every_language(): void
    {
        foreach (['ku', 'ar', 'en'] as $locale) {
            $this->get("/{$locale}/simulator")
                ->assertOk()
                ->assertSee(trans('simulator.form.submit', [], $locale));
        }
    }

    public function test_all_three_tabs_are_reachable_from_each_other(): void
    {
        $this->get('/en')->assertOk()->assertSee('/en/simulator', false);
        $this->get('/en/compiler')->assertOk()->assertSee('/en/simulator', false);

        $this->get('/en/simulator')
            ->assertOk()
            ->assertSee('/en/compiler', false)
            ->assertSee('href="http://localhost/en"', false);
    }

    public function test_every_network_is_offered_with_the_question_it_answers(): void
    {
        $response = $this->get('/en/simulator')->assertOk();

        foreach (Simulation::PRESETS as $preset) {
            $response->assertSee(trans("simulator.presets.{$preset}.name", [], 'en'));
            $response->assertSee(trans("simulator.presets.{$preset}.question", [], 'en'), false);
        }
    }

    // ----------------------------------------------------------------------
    // Running one
    // ----------------------------------------------------------------------

    public function test_a_run_is_stored_and_redirects_to_its_own_url(): void
    {
        Http::fake(['*/api/v1/simulate' => Http::response($this->payload(), 200)]);

        $response = $this->post('/en/simulator', $this->valid());

        $this->assertDatabaseCount('simulations', 1);
        $simulation = Simulation::sole();

        $response->assertRedirect("/en/simulation/{$simulation->id}");
        $this->assertTrue($simulation->succeeded);
        $this->assertSame('crosstalk_pair', $simulation->preset);
    }

    /**
     * Every run is random, so a result page that could not be returned to would
     * be a result nobody could cite. The seed is the reason it can be.
     */
    public function test_the_seed_is_stored_and_shown_so_the_run_can_be_repeated(): void
    {
        Http::fake(['*/api/v1/simulate' => Http::response($this->payload(), 200)]);

        $this->post('/en/simulator', $this->valid());
        $simulation = Simulation::sole();

        $this->assertSame(4242, $simulation->seed);
        $this->get("/en/simulation/{$simulation->id}")->assertOk()->assertSee('4242');
    }

    public function test_the_settings_reach_the_backend_unchanged(): void
    {
        Http::fake(['*/api/v1/simulate' => Http::response($this->payload(), 200)]);

        $this->post('/en/simulator', $this->valid([
            'cells' => 40, 'minutes' => 90, 'crosstalk' => 0.75, 'seed' => 99,
        ]));

        Http::assertSent(function ($request) {
            return $request['preset'] === 'crosstalk_pair'
                && $request['cells'] === 40
                && $request['minutes'] === 90
                && $request['crosstalk'] === 0.75
                && $request['seed'] === 99
                && $request['resource_coupling'] === true;
        });
    }

    /**
     * An unchecked checkbox is absent from the request body, not false. Read
     * carelessly that becomes null, and the backend's own default — coupling on
     * — silently overrides what the user asked for.
     */
    public function test_unchecking_shared_ribosomes_actually_turns_them_off(): void
    {
        Http::fake(['*/api/v1/simulate' => Http::response($this->payload(), 200)]);

        $payload = $this->valid();
        $payload['resource_coupling'] = '0';

        $this->post('/en/simulator', $payload);

        Http::assertSent(fn ($request) => $request['resource_coupling'] === false);
    }

    public function test_a_blank_seed_is_sent_as_absent_rather_than_zero(): void
    {
        Http::fake(['*/api/v1/simulate' => Http::response($this->payload(), 200)]);

        $this->post('/en/simulator', $this->valid(['seed' => '']));

        Http::assertSent(fn ($request) => $request['seed'] === null);
    }

    // ----------------------------------------------------------------------
    // Validation
    // ----------------------------------------------------------------------

    public function test_an_impossible_population_is_rejected_before_the_backend_is_called(): void
    {
        Http::fake();

        $this->post('/en/simulator', $this->valid(['cells' => 100000]))
            ->assertSessionHasErrors('cells');

        Http::assertNothingSent();
        $this->assertDatabaseCount('simulations', 0);
    }

    public function test_an_unknown_network_is_rejected_before_the_backend_is_called(): void
    {
        Http::fake();

        $this->post('/en/simulator', $this->valid(['preset' => 'wishful_thinking']))
            ->assertSessionHasErrors('preset');

        Http::assertNothingSent();
    }

    public function test_an_overlong_run_is_rejected(): void
    {
        Http::fake();

        $this->post('/en/simulator', $this->valid(['minutes' => 5000]))
            ->assertSessionHasErrors('minutes');

        Http::assertNothingSent();
    }

    public function test_an_unreachable_backend_produces_a_readable_message(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));

        $this->post('/en/simulator', $this->valid())
            ->assertSessionHasErrors(['preset' => trans('errors.backend.backend_unreachable', [], 'en')]);
    }

    // ----------------------------------------------------------------------
    // Reading the result
    // ----------------------------------------------------------------------

    public function test_the_result_page_reports_the_crosstalk_it_measured(): void
    {
        $simulation = $this->stored();

        $this->get("/en/simulation/{$simulation->id}")
            ->assertOk()
            ->assertSee(trans('simulator.crosstalk.attribution_title', [], 'en'))
            // 69% of gene B's transcripts were driven by the wrong signal.
            ->assertSee('69%');
    }

    public function test_both_correlation_matrices_are_shown_side_by_side(): void
    {
        $simulation = $this->stored();

        $this->get("/en/simulation/{$simulation->id}")
            ->assertOk()
            ->assertSee(trans('simulator.crosstalk.measured', [], 'en'))
            ->assertSee(trans('simulator.crosstalk.partial', [], 'en'))
            ->assertSee('0.50')   // measured
            ->assertSee('0.06');  // after removing cell-to-cell variability
    }

    public function test_diagnostics_are_rendered_in_the_reader_s_language(): void
    {
        $simulation = $this->stored();

        foreach (['ku', 'ar', 'en'] as $locale) {
            $this->get("/{$locale}/simulation/{$simulation->id}")
                ->assertOk()
                ->assertSee(trans('simulator.messages.crosstalk_dominates',
                    ['gene' => 'B', 'percent' => 69.0], $locale));
        }
    }

    public function test_the_standing_limits_of_the_model_reach_the_reader(): void
    {
        $simulation = $this->stored(['diagnostics' => [
            ['code' => 'parameters_illustrative', 'severity' => 'info', 'params' => [], 'span' => null],
        ]]);

        $this->get("/en/simulation/{$simulation->id}")
            ->assertOk()
            ->assertSee(trans('simulator.messages.parameters_illustrative', [], 'en'));
    }

    /**
     * The intrinsic/extrinsic split is only meaningful for two identical
     * reporters. On any other network the same arithmetic produces a number
     * with no meaning, so the section must not appear at all.
     */
    public function test_the_noise_split_appears_only_when_the_network_supports_it(): void
    {
        $without = $this->stored();
        $this->get("/en/simulation/{$without->id}")
            ->assertOk()
            ->assertDontSee(trans('simulator.decomposition.title', [], 'en'));

        $with = $this->stored(['decomposition' => [
            'pair' => ['A', 'B'], 'intrinsic' => 0.024, 'extrinsic' => 0.0203,
            'total' => 0.0443, 'intrinsic_share' => 0.542,
        ]]);
        $this->get("/en/simulation/{$with->id}")
            ->assertOk()
            ->assertSee(trans('simulator.decomposition.title', [], 'en'))
            ->assertSee(trans('simulator.decomposition.intrinsic', [], 'en'));
    }

    public function test_switching_statistics_appear_only_for_a_bistable_network(): void
    {
        $without = $this->stored();
        $this->get("/en/simulation/{$without->id}")
            ->assertOk()
            ->assertDontSee(trans('simulator.switching.title', [], 'en'));

        $with = $this->stored(['switching' => [
            'pair' => ['A', 'B'], 'switches' => 22, 'cells_that_switched' => 19,
            'cells' => 60, 'per_cell_per_hour' => 0.36, 'mean_dwell_minutes' => 163.6,
        ]]);
        $this->get("/en/simulation/{$with->id}")
            ->assertOk()
            ->assertSee(trans('simulator.switching.title', [], 'en'))
            ->assertSee('22');
    }

    public function test_a_failed_run_still_gets_a_page_with_its_diagnostics(): void
    {
        $simulation = $this->stored([
            'ok' => false,
            'diagnostics' => [
                ['code' => 'unknown_preset', 'severity' => 'error',
                 'params' => ['preset' => 'nope', 'available' => 'independent'], 'span' => null],
            ],
            'diagnostic_counts' => ['error' => 1, 'warning' => 0, 'info' => 0],
        ]);

        $this->get("/en/simulation/{$simulation->id}")
            ->assertOk()
            ->assertSee(trans('simulator.result.failed', [], 'en'));
    }

    public function test_switching_language_keeps_you_on_the_same_simulation(): void
    {
        $simulation = $this->stored();

        $this->get("/ku/simulation/{$simulation->id}")
            ->assertOk()
            ->assertSee("/ar/simulation/{$simulation->id}", false)
            ->assertSee("/en/simulation/{$simulation->id}", false);
    }

    // ----------------------------------------------------------------------
    // Exports
    // ----------------------------------------------------------------------

    public function test_the_csv_carries_one_row_per_sampled_moment(): void
    {
        $simulation = $this->stored();

        $response = $this->get("/en/simulation/{$simulation->id}/export.csv")->assertOk();
        $body = $response->streamedContent() ?: $response->getContent();

        $this->assertStringContainsString('A ' . trans('simulator.export.protein_mean', [], 'en'), $body);
        $this->assertStringContainsString('B ' . trans('simulator.export.protein_sd', [], 'en'), $body);

        // Five sampled moments plus the header row.
        $this->assertCount(6, array_filter(explode("\n", trim($body))));
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_a_failed_run_offers_no_csv_to_download(): void
    {
        $simulation = $this->stored(['ok' => false]);

        $this->get("/en/simulation/{$simulation->id}/export.csv")->assertNotFound();
    }

    public function test_the_json_export_is_the_whole_result(): void
    {
        $simulation = $this->stored();

        $this->get("/en/simulation/{$simulation->id}/export.json")
            ->assertOk()
            ->assertJsonPath('crosstalk.attribution.B.crosstalk', 0.69)
            ->assertJsonPath('request.seed', 4242);
    }

    // ----------------------------------------------------------------------
    // Retention
    // ----------------------------------------------------------------------

    public function test_simulations_expire_with_everything_else(): void
    {
        $old = $this->stored();
        $old->forceFill(['created_at' => now()->subDays(45)])->save();
        $recent = $this->stored();

        $this->artisan('analyses:prune', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('simulations', ['id' => $old->id]);
        $this->assertDatabaseHas('simulations', ['id' => $recent->id]);
    }
}
