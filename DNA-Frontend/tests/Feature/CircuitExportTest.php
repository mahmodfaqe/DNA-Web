<?php

namespace Tests\Feature;

use App\Models\Circuit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FASTA carries the bases; these carry what the bases are for. A design that
 * leaves this tool as FASTA has lost everything the compiler decided.
 */
class CircuitExportTest extends TestCase
{
    use RefreshDatabase;

    private function circuit(bool $succeeded = true): Circuit
    {
        return Circuit::create([
            'source_text' => 'if lactose then produce green protein',
            'language' => 'en',
            'expression' => 'IF LACTOSE THEN GFP',
            'succeeded' => $succeeded,
            'compiled' => ['ok' => $succeeded, 'units' => [], 'parts' => [], 'totals' => []],
        ]);
    }

    public function test_the_result_page_offers_both_design_formats(): void
    {
        $circuit = $this->circuit();

        $this->get(route('compiler.show', ['circuit' => $circuit->id]))
            ->assertOk()
            ->assertSee(__('compiler.export.sbol'), false)
            ->assertSee(__('compiler.export.genbank'), false)
            // A reader who has never met SBOL needs to be told what opens it.
            ->assertSee('SynBioHub')
            ->assertSee('SnapGene');
    }

    public function test_sbol_downloads_with_an_xml_filename(): void
    {
        Http::fake(['*/api/v1/compile/export' => Http::response([
            'ok' => true, 'format' => 'sbol', 'document' => '<?xml version="1.0"?><rdf:RDF/>',
        ], 200)]);

        $circuit = $this->circuit();

        $this->get(route('compiler.export', ['circuit' => $circuit->id, 'format' => 'sbol']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="circuit-' . $circuit->id . '.xml"')
            ->assertSee('rdf:RDF', false);
    }

    public function test_genbank_downloads_with_a_gb_filename(): void
    {
        Http::fake(['*/api/v1/compile/export' => Http::response([
            'ok' => true, 'format' => 'genbank', 'document' => "LOCUS       unit_1  100 bp\n//\n",
        ], 200)]);

        $circuit = $this->circuit();

        $this->get(route('compiler.export', ['circuit' => $circuit->id, 'format' => 'genbank']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="circuit-' . $circuit->id . '.gb"');
    }

    public function test_the_export_recompiles_from_the_original_sentence(): void
    {
        Http::fake(['*/api/v1/compile/export' => Http::response(
            ['ok' => true, 'document' => 'x'], 200
        )]);

        $circuit = $this->circuit();
        $this->get(route('compiler.export', ['circuit' => $circuit->id, 'format' => 'sbol']));

        Http::assertSent(fn ($request) => $request->data()['text'] === $circuit->source_text);
    }

    public function test_an_unknown_format_is_a_404_rather_than_a_guess(): void
    {
        $circuit = $this->circuit();

        $this->get("/en/circuit/{$circuit->id}/export/snapgene")->assertNotFound();
    }

    public function test_a_circuit_that_failed_to_compile_has_nothing_to_export(): void
    {
        $circuit = $this->circuit(succeeded: false);

        $this->get(route('compiler.export', ['circuit' => $circuit->id, 'format' => 'sbol']))
            ->assertNotFound();
    }
}
