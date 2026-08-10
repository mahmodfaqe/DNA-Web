<?php

use App\Models\Analysis;
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
    $deleted = Analysis::where('created_at', '<', now()->subDays($days))->delete();

    $this->info("Removed {$deleted} analyses older than {$days} days.");
})->purpose('Delete stored analyses past the retention window');

Schedule::command('analyses:prune')->daily();
