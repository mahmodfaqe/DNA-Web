<?php

use App\Models\Analysis;
use App\Models\Circuit;
use App\Models\MemoryDesign;
use App\Models\Simulation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Uploaded sequence data is personal research material. Keeping it forever is a
 * liability, not a feature, so results expire on a documented schedule.
 *
 * The window is read from config rather than hard-coded, because the same
 * number has to appear in three places — the scheduled run, the footer the
 * visitor reads, and whatever the department agreed to. One source keeps the
 * promise on the page and the behaviour of the job from drifting apart.
 */
Artisan::command('analyses:prune {--days=}', function () {
    $days = (int) ($this->option('days') ?: config('services.retention_days', 30));
    $cutoff = now()->subDays($days);

    $analyses = Analysis::where('created_at', '<', $cutoff)->delete();
    $circuits = Circuit::where('created_at', '<', $cutoff)->delete();
    $simulations = Simulation::where('created_at', '<', $cutoff)->delete();
    $designs = MemoryDesign::where('created_at', '<', $cutoff)->delete();

    $this->info(
        "Removed {$analyses} analyses, {$circuits} circuits, {$simulations} simulations "
        . "and {$designs} memory designs older than {$days} days."
    );
})->purpose('Delete stored results past the retention window');

// Runs in the `scheduler` container, which exists only to call `schedule:work`.
// Before it did, this line was registered and never executed: the web container
// starts Apache and nothing ever asked Laravel what was due.
Schedule::command('analyses:prune')
    ->daily()
    ->withoutOverlapping();
