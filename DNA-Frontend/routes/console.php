<?php

use App\Models\Analysis;
use App\Models\Circuit;
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

    $this->info("Removed {$analyses} analyses and {$circuits} circuits older than {$days} days.");
})->purpose('Delete stored analyses and circuits past the retention window');

Schedule::command('analyses:prune')->daily();
