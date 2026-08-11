<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZzVerifyProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_endpoints_survive_the_relaxed_locale_pattern(): void
    {
        $this->get('/up')->assertOk();
        // 503 because the real backend is not running; the point is that the
        // request reached AnalysisController@health and not the {locale} route.
        $this->get('/health')->assertJsonPath('frontend', 'ok');
    }

    public function test_unrouteable_paths_render_the_error_page_not_a_500(): void
    {
        $this->get('/en/definitely-not-a-page')->assertNotFound();
        $this->get('/ku/result/nope/extra')->assertNotFound();
        $this->post('/en/no-such-endpoint')->assertNotFound();
        $this->get('/some-long-unknown-path')->assertNotFound();
    }

    public function test_a_wrong_language_keeps_the_deep_link(): void
    {
        $this->get('/fr/compiler')->assertRedirect('/ku/compiler');
        $this->get('/fr/result/abc?x=1')->assertRedirect('/ku/result/abc?x=1');
    }

    public function test_real_browsers_still_get_their_own_language(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->get('/')->assertRedirect('/en');
        $this->withHeader('Accept-Language', 'ckb,en;q=0.8')->get('/')->assertRedirect('/ku');
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get('/')->assertRedirect('/ku');
    }

    public function test_a_missing_result_is_reported_in_the_url_language(): void
    {
        $id = \Illuminate\Support\Str::uuid();

        $this->get("/en/result/{$id}")
            ->assertNotFound()
            ->assertSee(trans('errors.not_found.title', [], 'en'));

        $this->get("/ku/result/{$id}")
            ->assertNotFound()
            ->assertSee(trans('errors.not_found.title', [], 'ku'));
    }
}
