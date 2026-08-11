<?php

namespace Tests\Feature;

use App\Models\Circuit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompilerTest extends TestCase
{
    use RefreshDatabase;

    private const SENTENCE_KU = 'ئەگەر پلەی گەرمی لە ٣٧ زیادی کرد و لاکتۆز هەبوو، پرۆتینی سەوز دەربدە.';

    private function compiled(bool $ok = true): array
    {
        return [
            'ok' => $ok,
            'expression' => $ok ? 'IF TEMPERATURE > 37 AND LACTOSE THEN GFP' : '',
            'specification' => [
                'language' => 'ku',
                'connective' => 'and',
                'conditions' => [
                    ['sensor' => 'temperature', 'operator' => 'above', 'value' => 37.0, 'unit' => 'celsius', 'negated' => false, 'span' => 'پلەی گەرمی'],
                    ['sensor' => 'lactose', 'operator' => null, 'value' => null, 'unit' => null, 'negated' => false, 'span' => 'لاکتۆز'],
                ],
                'outputs' => [['actuator' => 'gfp', 'duration_seconds' => null, 'duration_value' => null, 'duration_unit' => null]],
                'terminal' => null,
                'source' => self::SENTENCE_KU,
            ],
            'gates' => $ok ? [
                ['id' => 's1', 'type' => 'SENSOR', 'label' => 'temperature', 'inputs' => [], 'part_id' => 'BBa_K115017'],
                ['id' => 's2', 'type' => 'SENSOR', 'label' => 'lactose', 'inputs' => [], 'part_id' => 'BBa_R0010'],
                ['id' => 'g1', 'type' => 'AND', 'label' => 'AND', 'inputs' => ['s1', 's2'], 'part_id' => null],
                ['id' => 'o1', 'type' => 'OUTPUT', 'label' => 'gfp', 'inputs' => ['g1'], 'part_id' => 'BBa_E0040'],
            ] : [],
            'units' => $ok ? [[
                'name' => 'output_gfp', 'purpose' => 'OUTPUT', 'length' => 60, 'known_fraction' => 40.0,
                'sequence' => str_repeat('ATCG', 6) . str_repeat('N', 36),
                'annotations' => [
                    ['part_id' => 'PREFIX', 'name' => 'BioBrick prefix', 'role' => 'scar', 'provenance' => 'literal', 'start' => 1, 'end' => 24],
                    ['part_id' => 'BBa_E0040', 'name' => 'GFPmut3b', 'role' => 'cds', 'provenance' => 'placeholder', 'start' => 25, 'end' => 60],
                ],
            ]] : [],
            'parts' => $ok ? [
                ['id' => 'BBa_R0010', 'name' => 'pLac', 'role' => 'promoter', 'provenance' => 'literal', 'length' => 200, 'registry_url' => 'https://parts.igem.org/Part:BBa_R0010'],
                ['id' => 'BBa_E0040', 'name' => 'GFPmut3b', 'role' => 'cds', 'provenance' => 'placeholder', 'length' => 720, 'registry_url' => 'https://parts.igem.org/Part:BBa_E0040'],
            ] : [],
            'totals' => ['units' => $ok ? 1 : 0, 'length' => $ok ? 60 : 0, 'unresolved_bases' => 36, 'resolved_percent' => 40.0],
            'fasta' => $ok ? ">output_gfp\nATCGATCG\n" : '',
            'diagnostics' => $ok
                ? [['code' => 'hybrid_promoter_uncharacterised', 'severity' => 'warning', 'params' => ['inputs' => 2], 'span' => null]]
                : [['code' => 'no_condition_found', 'severity' => 'error', 'params' => [], 'span' => null]],
            'diagnostic_counts' => $ok ? ['error' => 0, 'warning' => 1, 'info' => 0] : ['error' => 1, 'warning' => 0, 'info' => 0],
        ];
    }

    public function test_the_compiler_tab_renders_in_every_language(): void
    {
        foreach (['ku', 'ar', 'en'] as $locale) {
            $this->get("/{$locale}/compiler")
                ->assertOk()
                ->assertSee(trans('compiler.hero.submit', [], $locale));
        }
    }

    public function test_both_tabs_are_reachable_from_each_other(): void
    {
        $this->get('/en')->assertOk()->assertSee('/en/compiler', false);
        $this->get('/en/compiler')->assertOk()->assertSee('/en', false);
    }

    public function test_a_successful_compile_is_stored_and_redirects(): void
    {
        Http::fake(['*/api/v1/compile' => Http::response($this->compiled(), 200)]);

        $response = $this->post('/ku/compiler', ['description' => self::SENTENCE_KU]);

        $this->assertDatabaseCount('circuits', 1);
        $circuit = Circuit::sole();

        $response->assertRedirect("/ku/circuit/{$circuit->id}");
        $this->assertTrue($circuit->succeeded);
        $this->assertSame('IF TEMPERATURE > 37 AND LACTOSE THEN GFP', $circuit->expression);
    }

    /**
     * A sentence that failed to compile is still worth a URL: the diagnostics
     * saying why are the most useful thing the tool produced.
     */
    public function test_a_failed_compile_is_stored_and_shows_its_diagnostics(): void
    {
        Http::fake(['*/api/v1/compile' => Http::response($this->compiled(false), 200)]);

        $this->post('/en/compiler', ['description' => 'please make the cells happy']);

        $circuit = Circuit::sole();
        $this->assertFalse($circuit->succeeded);

        $this->get("/en/circuit/{$circuit->id}")
            ->assertOk()
            ->assertSee(trans('compiler.result.failed', [], 'en'))
            ->assertSee(trans('compiler.messages.no_condition_found', [], 'en'));
    }

    public function test_diagnostics_are_rendered_in_the_reader_s_language(): void
    {
        Http::fake(['*/api/v1/compile' => Http::response($this->compiled(), 200)]);
        $this->post('/en/compiler', ['description' => self::SENTENCE_KU]);
        $circuit = Circuit::sole();

        foreach (['ku', 'ar', 'en'] as $locale) {
            $this->get("/{$locale}/circuit/{$circuit->id}")
                ->assertOk()
                ->assertSee(trans('compiler.messages.hybrid_promoter_uncharacterised', ['inputs' => 2], $locale));
        }
    }

    public function test_a_short_description_is_rejected_before_the_backend_is_called(): void
    {
        Http::fake();

        $this->post('/en/compiler', ['description' => 'hi'])
            ->assertSessionHasErrors('description');

        Http::assertNothingSent();
        $this->assertDatabaseCount('circuits', 0);
    }

    public function test_fasta_download_carries_the_compiled_sequence(): void
    {
        $circuit = Circuit::create([
            'source_text' => self::SENTENCE_KU, 'language' => 'ku',
            'expression' => 'IF LACTOSE THEN GFP', 'succeeded' => true,
            'compiled' => $this->compiled(),
        ]);

        $response = $this->get("/ku/circuit/{$circuit->id}/circuit.fasta")->assertOk();

        $this->assertStringContainsString('>output_gfp', $response->streamedContent() ?: $response->getContent());
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_a_failed_circuit_offers_no_fasta_to_download(): void
    {
        $circuit = Circuit::create([
            'source_text' => 'nonsense', 'language' => 'en',
            'expression' => '', 'succeeded' => false,
            'compiled' => $this->compiled(false),
        ]);

        $this->get("/en/circuit/{$circuit->id}/circuit.fasta")->assertNotFound();
    }

    public function test_switching_language_keeps_you_on_the_same_circuit(): void
    {
        $circuit = Circuit::create([
            'source_text' => self::SENTENCE_KU, 'language' => 'ku',
            'expression' => 'IF LACTOSE THEN GFP', 'succeeded' => true,
            'compiled' => $this->compiled(),
        ]);

        $this->get("/ku/circuit/{$circuit->id}")
            ->assertOk()
            ->assertSee("/ar/circuit/{$circuit->id}", false)
            ->assertSee("/en/circuit/{$circuit->id}", false);
    }

    public function test_an_unreachable_backend_produces_a_readable_message(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->post('/en/compiler', ['description' => 'if lactose then produce green protein'])
            ->assertSessionHasErrors(['description' => trans('errors.backend.backend_unreachable', [], 'en')]);
    }
}
