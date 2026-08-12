<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Circuit;
use App\Models\MemoryDesign;
use App\Models\Simulation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Operational promises: the ones a visitor is shown, and the ones a deployment
 * depends on. None of these is about what an analysis computes; all of them are
 * about what the system does when nobody is watching it.
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['records' => [], 'metrics' => []];
    }

    private function seedOldAndNew(): void
    {
        foreach ([100, 1] as $daysAgo) {
            Analysis::create([
                'filename' => "{$daysAgo}.fasta",
                'size_bytes' => 10,
                'checksum' => str_repeat((string) ($daysAgo % 10), 64),
                'gene_count' => 1,
                'payload' => $this->payload(),
            ])->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

            Circuit::create([
                'source_text' => 'if lactose then gfp',
                'language' => 'en',
                'succeeded' => true,
                'compiled' => $this->payload(),
            ])->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

            Simulation::create([
                'preset' => 'crosstalk',
                'cells' => 10,
                'minutes' => 10,
                'seed' => 1,
                'succeeded' => true,
                'result' => $this->payload(),
            ])->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

            MemoryDesign::create([
                'signal' => 'lactose',
                'chassis' => 'ecoli',
                'architecture' => 'toggle',
                'hold_hours' => 24,
                'succeeded' => true,
                'result' => $this->payload(),
            ])->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }
    }

    // -- Retention ---------------------------------------------------------

    /**
     * The schedule was registered for months and never ran once: the web
     * container starts Apache and nothing called Laravel's scheduler, so
     * uploaded sequence data was kept forever. A `scheduler` service now runs
     * `schedule:work`; this asserts the entry it is there to execute exists.
     */
    public function test_pruning_is_actually_scheduled(): void
    {
        // Console routes are loaded when the console kernel bootstraps, which an
        // HTTP test does not do on its own. Any artisan call forces it.
        Artisan::call('schedule:list');

        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'analyses:prune'));

        $this->assertCount(1, $events, 'analyses:prune is not on the schedule');
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }

    public function test_pruning_deletes_every_kind_of_stored_result(): void
    {
        $this->seedOldAndNew();

        Artisan::call('analyses:prune', ['--days' => 30]);

        $this->assertSame(1, Analysis::count());
        $this->assertSame(1, Circuit::count());
        $this->assertSame(1, Simulation::count());
        $this->assertSame(1, MemoryDesign::count());
    }

    public function test_pruning_defaults_to_the_configured_window(): void
    {
        config(['services.retention_days' => 7]);
        $this->seedOldAndNew();

        Artisan::call('analyses:prune');

        // Both the 100-day and the 1-day rows are compared against 7 days, so
        // only the recent one survives — and it survives for the configured
        // reason, not because 30 happened to be hard-coded in two places.
        $this->assertSame(1, Analysis::count());
    }

    /**
     * The footer tells the visitor how long their sequences are kept. If that
     * number and the job's window can drift apart, the page is making a promise
     * the code does not keep.
     */
    public function test_the_footer_promises_the_window_the_job_enforces(): void
    {
        config(['services.retention_days' => 14]);

        $this->get('/en')
            ->assertOk()
            ->assertSee(__('common.footer.retention', ['days' => 14]), false);
    }

    // -- Browser hardening -------------------------------------------------

    public function test_security_headers_are_present_on_every_page(): void
    {
        $response = $this->get('/en')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $policy);
        // default-src does not cover either of these.
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("worker-src 'none'", $policy);
    }

    /**
     * Sending HSTS from a plain-HTTP development server is ignored by browsers,
     * but a year-long pin on a shared hostname is not the kind of mistake that
     * is easy to undo, so it is worth asserting it only goes out over TLS.
     */
    public function test_hsts_is_sent_only_over_tls(): void
    {
        $this->get('http://localhost/en')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');

        $secure = $this->get('https://localhost/en')->assertOk();

        $this->assertStringContainsString(
            'max-age=31536000',
            $secure->headers->get('Strict-Transport-Security')
        );
    }

    // -- Titles ------------------------------------------------------------

    /**
     * The layout used to append the app name to a default that was already the
     * app name, so any page that did not set a title rendered it twice.
     */
    public function test_a_page_title_names_the_page_then_the_app_once(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<title>(.*?)</title>#s', $html, $matches));
        $this->assertSame(
            __('common.hero.title') . ' — ' . __('common.app.name'),
            trim($matches[1])
        );
    }
}
