<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Middleware\SetLocale;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Unlocalised operational endpoint: monitoring should not have to pick a language.
Route::get('/health', [AnalysisController::class, 'health'])->name('health');

// A bare visit is sent to the best language for that visitor.
Route::get('/', function (Request $request) {
    return redirect()->to('/' . Locales::preferred($request));
})->name('root');

Route::prefix('{locale}')
    ->middleware(SetLocale::class)
    ->group(function () {
        Route::get('/', [AnalysisController::class, 'index'])->name('analysis.index');
        Route::post('/analyze', [AnalysisController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('analysis.store');

        Route::get('/result/{analysis}', [AnalysisController::class, 'show'])->name('analysis.show');
        Route::get('/result/{analysis}/print', [AnalysisController::class, 'print'])->name('analysis.print');
        Route::get('/result/{analysis}/export.csv', [AnalysisController::class, 'csv'])->name('analysis.csv');
        Route::get('/result/{analysis}/export.json', [AnalysisController::class, 'json'])->name('analysis.json');
    });
