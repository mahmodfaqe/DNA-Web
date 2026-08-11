<?php

namespace Tests\Feature;

use App\Models\Analysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function fasta(string $name = 'sample.fasta'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            ">gene_1 first\nATGGCTGCTGCTTAA\n>gene_2 second\nATGGCCGCTGCTTAA\n"
        );
    }

    private function backendPayload(): array
    {
        return [
            'status' => 'success',
            'version' => '3.0.0',
            'checksum' => 'deadbeefdeadbeef',
            'summary' => [
                'total_genes' => 2, 'total_bases' => 30, 'average_length' => 15,
                'average_gc' => 46.67, 'min_length' => 15, 'max_length' => 15,
                'min_gc' => 46.67, 'max_gc' => 46.67, 'unknown_bases' => 0,
                'unknown_fraction' => 0.0, 'records_with_ambiguity' => 0,
            ],
            'genes' => [
                $this->gene('gene_1'),
                $this->gene('gene_2'),
            ],
            'comparisons' => [[
                'reference_id' => 'gene_1', 'alternative_id' => 'gene_2',
                'method' => 'global_alignment', 'identity_percent' => 93.33,
                'aligned_length' => 15, 'total_variants' => 1,
                'counts' => ['substitution' => 1, 'insertion' => 0, 'deletion' => 0, 'length_difference' => 0],
                'effects' => ['synonymous' => 1, 'missense' => 0, 'nonsense' => 0, 'stop_lost' => 0, 'unknown' => 0],
                'frameshift_events' => 0,
                'variants' => [[
                    'type' => 'substitution', 'position' => 6, 'codon' => 2,
                    'reference_base' => 'T', 'alternative_base' => 'C', 'transition' => true,
                    'effect' => 'synonymous', 'ref_codon' => 'GCT', 'alt_codon' => 'GCC',
                    'ref_aa' => 'A', 'alt_aa' => 'A',
                ]],
                'variants_truncated' => false,
            ]],
            'limits' => ['align_max_bp' => 3000, 'orf_max_scan_bp' => 200000, 'tm_nn_max_bp' => 50],
        ];
    }

    private function gene(string $id): array
    {
        return [
            'id' => $id, 'description' => $id, 'length' => 15,
            'gc_content' => 46.67, 'at_content' => 53.33, 'gc_skew' => 0.0,
            'melting_temp' => ['value' => 44.0, 'method' => 'nearest_neighbour', 'reliable' => true],
            'molecular_weight' => 9200.0,
            'base_composition' => ['A' => 4, 'T' => 4, 'C' => 4, 'G' => 3, 'N' => 0, 'ambiguous' => 0, 'known_bases' => 15, 'unknown_bases' => 0],
            'ambiguity_codes' => [],
            'quality' => ['unknown_fraction' => 0.0, 'has_ambiguity' => false],
            'orfs' => [
                'count' => 1, 'truncated' => false, 'scanned_bp' => 15,
                'longest' => ['strand' => '+', 'frame' => 1, 'start' => 1, 'end' => 12, 'length_bp' => 12, 'length_aa' => 4, 'protein' => 'MAAA'],
                'top' => [],
            ],
            'protein_length' => 4, 'protein_sequence' => 'MAAA', 'codon_usage' => [],
        ];
    }

    public function test_a_successful_upload_is_stored_and_redirects_to_its_own_url(): void
    {
        Http::fake(['*/api/v1/analyze' => Http::response($this->backendPayload(), 200)]);

        $response = $this->post('/ku/analyze', ['fasta_file' => $this->fasta()]);

        $this->assertDatabaseCount('analyses', 1);
        $analysis = Analysis::sole();

        $response->assertRedirect("/ku/result/{$analysis->id}");
        $this->assertSame('sample.fasta', $analysis->filename);
        $this->assertSame(2, $analysis->gene_count);
    }

    /**
     * Post/Redirect/Get: the result must survive a refresh without re-uploading.
     */
    public function test_the_result_page_can_be_reloaded_without_resubmitting(): void
    {
        Http::fake(['*/api/v1/analyze' => Http::response($this->backendPayload(), 200)]);
        $this->post('/ku/analyze', ['fasta_file' => $this->fasta()]);

        $analysis = Analysis::sole();
        Http::fake(); // Any further backend call would now fail the test.

        $this->get("/ku/result/{$analysis->id}")->assertOk()->assertSee('gene_1');
        $this->get("/ku/result/{$analysis->id}")->assertOk()->assertSee('gene_2');
    }

    public function test_a_missing_file_is_rejected_before_the_backend_is_called(): void
    {
        Http::fake();

        $this->post('/en/analyze', [])->assertSessionHasErrors('fasta_file');

        Http::assertNothingSent();
        $this->assertDatabaseCount('analyses', 0);
    }

    public function test_a_wrong_extension_is_rejected(): void
    {
        Http::fake();

        $this->post('/en/analyze', ['fasta_file' => UploadedFile::fake()->create('notes.pdf', 10)])
            ->assertSessionHasErrors('fasta_file');

        Http::assertNothingSent();
    }

    /**
     * The i18n contract in one test: the backend sends a code, and the reader
     * sees prose in their own language.
     */
    public function test_a_backend_error_code_is_shown_in_the_current_language(): void
    {
        Http::fake([
            '*/api/v1/analyze' => Http::response([
                'error' => ['code' => 'sequence_invalid_chars', 'params' => ['record_id' => 'gene_1', 'characters' => ['Z', 'Q']]],
            ], 400),
        ]);

        foreach (['ku', 'ar', 'en'] as $locale) {
            $expected = trans('errors.backend.sequence_invalid_chars', [
                'record_id' => 'gene_1',
                'characters' => 'Z, Q',
            ], $locale);

            $this->post("/{$locale}/analyze", ['fasta_file' => $this->fasta()])
                ->assertSessionHasErrors(['fasta_file' => $expected]);
        }

        $this->assertDatabaseCount('analyses', 0);
    }

    public function test_an_unknown_error_code_degrades_to_the_generic_message(): void
    {
        Http::fake([
            '*/api/v1/analyze' => Http::response(['error' => ['code' => 'something_new', 'params' => []]], 400),
        ]);

        $this->post('/en/analyze', ['fasta_file' => $this->fasta()])
            ->assertSessionHasErrors(['fasta_file' => trans('errors.backend.internal_error', [], 'en')]);
    }

    public function test_an_unreachable_backend_produces_a_readable_message(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->post('/en/analyze', ['fasta_file' => $this->fasta()])
            ->assertSessionHasErrors(['fasta_file' => trans('errors.backend.backend_unreachable', [], 'en')]);
    }

    public function test_csv_export_carries_a_bom_so_excel_reads_kurdish_headers(): void
    {
        $analysis = Analysis::create([
            'filename' => 'sample.fasta', 'size_bytes' => 60,
            'checksum' => 'deadbeefdeadbeef', 'gene_count' => 2,
            'payload' => $this->backendPayload(),
        ]);

        $content = $this->get("/ku/result/{$analysis->id}/export.csv")
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('gene_1', $content);
        $this->assertStringContainsString(trans('analysis.table.length', [], 'ku'), $content);
    }

    public function test_json_export_returns_the_stored_payload(): void
    {
        $analysis = Analysis::create([
            'filename' => 'sample.fasta', 'size_bytes' => 60,
            'checksum' => 'deadbeefdeadbeef', 'gene_count' => 2,
            'payload' => $this->backendPayload(),
        ]);

        $this->get("/en/result/{$analysis->id}/export.json")
            ->assertOk()
            ->assertJsonPath('summary.total_genes', 2)
            ->assertJsonPath('comparisons.0.identity_percent', 93.33);
    }

    public function test_an_expired_result_id_returns_a_translated_404(): void
    {
        $this->get('/ku/result/' . Str::uuid())
            ->assertNotFound();
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
