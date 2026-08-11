<?php

use App\Models\Analysis;
use App\Models\Circuit;
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
 */
Artisan::command('analyses:prune {--days=30}', function () {
    $days = (int) $this->option('days');
    $cutoff = now()->subDays($days);

    $analyses = Analysis::where('created_at', '<', $cutoff)->delete();
    $circuits = Circuit::where('created_at', '<', $cutoff)->delete();
    $simulations = Simulation::where('created_at', '<', $cutoff)->delete();

    $this->info(
        "Removed {$analyses} analyses, {$circuits} circuits and {$simulations} simulations "
        . "older than {$days} days."
    );
})->purpose('Delete stored analyses, circuits and simulations past the retention window');

Schedule::command('analyses:prune')->daily();
