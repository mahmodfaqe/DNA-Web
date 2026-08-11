<?php

namespace Tests\Feature;

use App\Models\MemoryDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MemoryTest extends TestCase
{
    use RefreshDatabase;

    /** The contract between the two services, written by hand rather than recorded. */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'ok' => true,
            'request' => [
                'signal' => 'lactose', 'chassis' => 'ecoli', 'hold_hours' => 24.0,
                'signal_minutes' => 60.0, 'must_be_reversible' => false,
                'on_plasmid' => true, 'recombinase' => 'bxb1', 'payload_bp' => 900,
            ],
            'recommendation' => [
                'architecture' => 'toggle', 'score' => 0.9076,
                'runner_up' => 'recombinase', 'gap' => 0.1276, 'orientation' => 'forward',
            ],
            'comparison' => [
                [
                    'architecture' => 'recombinase', 'retention' => 0.99, 'fidelity' => 0.52,
                    'speed' => 0.56, 'cost' => 0.92, 'total' => 0.78,
                    'false_write_share' => 0.478, 'disqualified' => false,
                    'disqualified_reason' => null,
                ],
                [
                    'architecture' => 'toggle', 'retention' => 1.0, 'fidelity' => 1.0,
                    'speed' => 0.44, 'cost' => 0.92, 'total' => 0.9076,
                    'false_write_share' => 0.0, 'disqualified' => false,
                    'disqualified_reason' => null,
                ],
            ],
            'outcomes' => [
                'recombinase' => [
                    'architecture' => 'recombinase', 'written' => true, 'write_fraction' => 0.91,
                    'retained_fraction' => 0.99, 'write_minutes_to_half' => 22.9,
                    'retention_half_life_hours' => null, 'false_write_per_hour' => 0.0548,
                    'generations_held' => 48.0, 'burden_units' => 2, 'reversible' => false,
                    'stores_in_dna' => true,
                    'phases' => [
                        ['name' => 'write', 'minutes' => [0, 30, 60],
                            'series' => ['integrase' => [0, 700, 1288], 'flipped' => [0, 0.6, 0.91]]],
                        ['name' => 'hold', 'minutes' => [0, 720, 1440],
                            'series' => ['integrase' => [1288, 40, 34], 'flipped' => [0.91, 0.98, 0.99]]],
                    ],
                    'detail' => ['leak_steady_integrase' => 34.11, 'plasmid_loss_per_hour' => 0.0],
                ],
                'toggle' => [
                    'architecture' => 'toggle', 'written' => true, 'write_fraction' => 1.0,
                    'retained_fraction' => 1.0, 'write_minutes_to_half' => 26.5,
                    'retention_half_life_hours' => 957000.0, 'false_write_per_hour' => 0.0,
                    'generations_held' => 48.0, 'burden_units' => 2, 'reversible' => true,
                    'stores_in_dna' => false,
                    'phases' => [
                        ['name' => 'write', 'minutes' => [0, 30, 60],
                            'series' => ['set' => [0, 900, 1540], 'reset' => [1708, 300, 50]]],
                        ['name' => 'hold', 'minutes' => [0, 720, 1440],
                            'series' => ['set' => [1540, 1539, 1539], 'reset' => [50, 50, 50]]],
                    ],
                    'detail' => ['bistable' => true, 'barrier' => 276.2, 'burst_size' => 15.0],
                ],
            ],
            'orientation' => [
                'forward' => [
                    'label' => 'forward', 'length' => 35, 'gc_percent' => 48.6, 'gc_skew' => 0.1,
                    'promoters' => [], 'terminators' => [], 'repeats' => [], 'homopolymers' => [],
                    'counts' => ['promoters' => 1, 'terminators' => 0, 'repeats' => 0,
                        'homopolymers' => 0, 'promoters_outward' => 1, 'promoters_inward' => 0],
                    'strongest_outward' => 0.917, 'risk' => 2.75,
                ],
                'reverse' => [
                    'label' => 'reverse', 'length' => 35, 'gc_percent' => 48.6, 'gc_skew' => -0.1,
                    'promoters' => [], 'terminators' => [], 'repeats' => [], 'homopolymers' => [],
                    'counts' => ['promoters' => 1, 'terminators' => 0, 'repeats' => 0,
                        'homopolymers' => 0, 'promoters_outward' => 0, 'promoters_inward' => 1],
                    'strongest_outward' => 0.0, 'risk' => 1.375,
                ],
                'preferred' => 'reverse', 'difference' => 1.375, 'decided_by_sequence' => false,
            ],
            'composition' => [
                'payload_length' => 35, 'gc_ramp' => [48.6], 'entropy' => 1.94,
                'is_default_payload' => true,
            ],
            'constructs' => [[
                'name' => 'register', 'purpose' => 'STORE', 'length' => 120,
                'resolved_percent' => 40.0,
                'sequence' => str_repeat('ATCG', 12) . str_repeat('N', 72),
                'annotations' => [
                    ['part_id' => 'attB_bxb1', 'name' => 'bxb1 attB', 'role' => 'att',
                        'provenance' => 'literal', 'direction' => 'forward', 'start' => 1, 'end' => 38, 'length' => 38],
                    ['part_id' => 'PAYLOAD', 'name' => 'Invertible cargo', 'role' => 'payload',
                        'provenance' => 'literal', 'direction' => 'forward', 'start' => 39, 'end' => 48, 'length' => 10],
                    ['part_id' => 'BBa_E0040', 'name' => 'GFPmut3b reporter', 'role' => 'cds',
                        'provenance' => 'placeholder', 'direction' => 'forward', 'start' => 49, 'end' => 120, 'length' => 72],
                ],
            ]],
            'parts' => [
                ['id' => 'attB_bxb1', 'name' => 'bxb1 attB', 'role' => 'att',
                    'provenance' => 'literal', 'length' => 38, 'registry_url' => null],
                ['id' => 'BBa_E0040', 'name' => 'GFPmut3b reporter', 'role' => 'cds',
                    'provenance' => 'placeholder', 'length' => 720,
                    'registry_url' => 'https://parts.igem.org/Part:BBa_E0040'],
            ],
            'totals' => ['constructs' => 1, 'length' => 120, 'unresolved_bases' => 72,
                'resolved_percent' => 40.0],
            'synthesis' => ['gc_percent' => 48.6, 'longest_homopolymer' => 0, 'repeat_count' => 0,
                'reasons' => [], 'difficult' => false],
            'fasta' => "; DeepBio-Memory Architect design\n>register purpose=STORE\nATCGATCG\n",
            'performance' => ['wall_ms' => 54.7],
            'diagnostics' => [
                ['code' => 'leak_writes_without_signal', 'severity' => 'warning',
                    'params' => ['architecture' => 'recombinase', 'percent' => 47.8,
                        'hours' => 24, 'leak' => 2.0], 'span' => 'recombinase'],
                ['code' => 'not_for_synthesis', 'severity' => 'info', 'params' => [], 'span' => null],
            ],
            'diagnostic_counts' => ['error' => 0, 'warning' => 1, 'info' => 1],
        ], $overrides);
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'signal' => 'lactose', 'chassis' => 'ecoli', 'hold_hours' => 24,
            'signal_minutes' => 60, 'strength' => 0.7, 'on_plasmid' => '1',
        ], $overrides);
    }

    private function stored(array $overrides = []): MemoryDesign
    {
        $result = $this->payload($overrides);

        return MemoryDesign::create([
            'signal' => $result['request']['signal'],
            'chassis' => $result['request']['chassis'],
            'architecture' => $result['recommendation']['architecture'] ?? null,
            'hold_hours' => (int) $result['request']['hold_hours'],
            'succeeded' => $result['ok'],
            'result' => $result,
        ]);
    }

    // ----------------------------------------------------------------------

    public function test_the_memory_tab_renders_in_every_language(): void
    {
        foreach (['ku', 'ar', 'en'] as $locale) {
            $this->get("/{$locale}/memory")
                ->assertOk()
                ->assertSee(trans('memory.form.submit', [], $locale));
        }
    }

    public function test_all_four_tabs_are_reachable_from_each_other(): void
    {
        foreach (['/en', '/en/compiler', '/en/simulator', '/en/memory'] as $page) {
            $response = $this->get($page)->assertOk();
            foreach (['/en/compiler', '/en/simulator', '/en/memory'] as $tab) {
                $response->assertSee($tab, false);
            }
        }
    }

    public function test_a_design_is_stored_and_redirects_to_its_own_url(): void
    {
        Http::fake(['*/api/v1/memory' => Http::response($this->payload(), 200)]);

        $response = $this->post('/en/memory', $this->valid());

        $this->assertDatabaseCount('memory_designs', 1);
        $design = MemoryDesign::sole();

        $response->assertRedirect("/en/design/{$design->id}");
        $this->assertSame('toggle', $design->architecture);
        $this->assertSame('lactose', $design->signal);
    }

    public function test_the_requirements_reach_the_backend_unchanged(): void
    {
        Http::fake(['*/api/v1/memory' => Http::response($this->payload(), 200)]);

        $this->post('/en/memory', $this->valid([
            'hold_hours' => 48, 'signal_minutes' => 120, 'must_be_reversible' => '1',
            'strength' => 0.4,
        ]));

        Http::assertSent(fn ($request) => $request['hold_hours'] === 48.0
            && $request['signal_minutes'] === 120.0
            && $request['must_be_reversible'] === true
            && $request['strength'] === 0.4);
    }

    public function test_unchecking_plasmid_actually_asks_for_a_genomic_integration(): void
    {
        Http::fake(['*/api/v1/memory' => Http::response($this->payload(), 200)]);

        $this->post('/en/memory', $this->valid(['on_plasmid' => '0']));

        Http::assertSent(fn ($request) => $request['on_plasmid'] === false);
    }

    public function test_an_unknown_host_is_rejected_before_the_backend_is_called(): void
    {
        Http::fake();

        $this->post('/en/memory', $this->valid(['chassis' => 'tardigrade']))
            ->assertSessionHasErrors('chassis');

        Http::assertNothingSent();
        $this->assertDatabaseCount('memory_designs', 0);
    }

    public function test_an_impossible_holding_time_is_rejected(): void
    {
        Http::fake();

        $this->post('/en/memory', $this->valid(['hold_hours' => 9999]))
            ->assertSessionHasErrors('hold_hours');

        Http::assertNothingSent();
    }

    public function test_an_unreachable_backend_produces_a_readable_message(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->post('/en/memory', $this->valid())
            ->assertSessionHasErrors(['signal' => trans('errors.backend.backend_unreachable', [], 'en')]);
    }

    // ----------------------------------------------------------------------
    // Reading the result
    // ----------------------------------------------------------------------

    /**
     * The verdict is worth nothing without the reasoning, so the losing
     * architecture has to be on the page in full.
     */
    public function test_the_losing_architecture_is_shown_alongside_the_winner(): void
    {
        $design = $this->stored();

        $this->get("/en/design/{$design->id}")
            ->assertOk()
            ->assertSee(trans('memory.architectures.toggle.name', [], 'en'))
            ->assertSee(trans('memory.architectures.recombinase.name', [], 'en'))
            ->assertSee(trans('memory.compare.title', [], 'en'));
    }

    public function test_the_reason_for_the_recommendation_is_on_the_page(): void
    {
        $design = $this->stored();

        $this->get("/en/design/{$design->id}")
            ->assertOk()
            ->assertSee(trans('memory.architectures.toggle.why', [], 'en'), false);
    }

    public function test_a_leak_that_writes_the_memory_is_reported_as_a_warning(): void
    {
        $design = $this->stored();

        $this->get("/en/design/{$design->id}")
            ->assertOk()
            ->assertSee(trans('memory.messages.leak_writes_without_signal', [
                'architecture' => 'recombinase', 'percent' => 47.8, 'hours' => 24, 'leak' => 2.0,
            ], 'en'), false);
    }

    public function test_diagnostics_are_rendered_in_the_reader_s_language(): void
    {
        $design = $this->stored();

        foreach (['ku', 'ar', 'en'] as $locale) {
            $this->get("/{$locale}/design/{$design->id}")
                ->assertOk()
                ->assertSee(trans('memory.messages.not_for_synthesis', [], $locale), false);
        }
    }

    public function test_a_close_call_is_declared_rather_than_dressed_up(): void
    {
        $close = $this->stored(['recommendation' => ['gap' => 0.004, 'runner_up' => 'recombinase']]);

        $this->get("/en/design/{$close->id}")
            ->assertOk()
            ->assertSee(trans('memory.result.close_call', [
                'other' => trans('memory.architectures.recombinase.name', [], 'en'),
                'gap' => '0.4',
            ], 'en'), false);
    }

    public function test_a_disqualified_architecture_is_marked_excluded_not_merely_last(): void
    {
        $design = $this->stored(['comparison' => [
            ['architecture' => 'recombinase', 'retention' => 0.99, 'fidelity' => 0.52,
                'speed' => 0.56, 'cost' => 0.92, 'total' => 0.0, 'false_write_share' => 0.478,
                'disqualified' => true, 'disqualified_reason' => 'not_reversible'],
            ['architecture' => 'toggle', 'retention' => 1.0, 'fidelity' => 1.0, 'speed' => 0.44,
                'cost' => 0.92, 'total' => 0.9076, 'false_write_share' => 0.0,
                'disqualified' => false, 'disqualified_reason' => null],
        ]]);

        $this->get("/en/design/{$design->id}")
            ->assertOk()
            ->assertSee(trans('memory.compare.excluded.not_reversible', [], 'en'));
    }

    public function test_both_orientations_are_scored_and_shown(): void
    {
        $design = $this->stored();

        $this->get("/en/design/{$design->id}")
            ->assertOk()
            ->assertSee(trans('memory.orientation.forward', [], 'en'))
            ->assertSee(trans('memory.orientation.reverse', [], 'en'))
            ->assertSee(trans('memory.orientation.promoters_outward', [], 'en'));
    }

    public function test_a_refused_design_shows_why_and_offers_no_dna(): void
    {
        $design = $this->stored([
            'ok' => false,
            'diagnostics' => [
                ['code' => 'chassis_parts_unavailable', 'severity' => 'error',
                    'params' => ['chassis' => 'yeast'], 'span' => null],
            ],
            'diagnostic_counts' => ['error' => 1, 'warning' => 0, 'info' => 0],
        ]);

        $this->get("/en/design/{$design->id}")
            ->assertOk()
            ->assertSee(trans('memory.result.refused', [], 'en'))
            ->assertSee(trans('memory.messages.chassis_parts_unavailable', ['chassis' => 'yeast'], 'en'), false);

        $this->get("/en/design/{$design->id}/memory.fasta")->assertNotFound();
    }

    public function test_switching_language_keeps_you_on_the_same_design(): void
    {
        $design = $this->stored();

        $this->get("/ku/design/{$design->id}")
            ->assertOk()
            ->assertSee("/ar/design/{$design->id}", false)
            ->assertSee("/en/design/{$design->id}", false);
    }

    // ----------------------------------------------------------------------
    // Exports and retention
    // ----------------------------------------------------------------------

    public function test_the_fasta_download_carries_the_construct(): void
    {
        $design = $this->stored();

        $response = $this->get("/en/design/{$design->id}/memory.fasta")->assertOk();

        // A plain response, not a streamed one: the construct is a few kilobases
        // and assembling it in memory costs nothing.
        $this->assertStringContainsString('>register', $response->getContent());
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_the_json_export_is_the_whole_design(): void
    {
        $design = $this->stored();

        $this->get("/en/design/{$design->id}/export.json")
            ->assertOk()
            ->assertJsonPath('recommendation.architecture', 'toggle')
            ->assertJsonPath('orientation.preferred', 'reverse');
    }

    public function test_designs_expire_with_everything_else(): void
    {
        $old = $this->stored();
        $old->forceFill(['created_at' => now()->subDays(45)])->save();
        $recent = $this->stored();

        $this->artisan('analyses:prune', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('memory_designs', ['id' => $old->id]);
        $this->assertDatabaseHas('memory_designs', ['id' => $recent->id]);
    }
}
