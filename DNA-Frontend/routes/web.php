<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\CompilerController;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\SimulatorController;
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

        // Second tab: natural language -> genetic circuit.
        Route::get('/compiler', [CompilerController::class, 'index'])->name('compiler.index');
        Route::post('/compiler', [CompilerController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('compiler.store');

        Route::get('/circuit/{circuit}', [CompilerController::class, 'show'])->name('compiler.show');
        Route::get('/circuit/{circuit}/circuit.fasta', [CompilerController::class, 'fasta'])->name('compiler.fasta');
        Route::get('/circuit/{circuit}/export.json', [CompilerController::class, 'json'])->name('compiler.json');

        // Third tab: stochastic simulation of expression noise and crosstalk.
        Route::get('/simulator', [SimulatorController::class, 'index'])->name('simulator.index');
        // Throttled harder than the other two. An analysis is bounded by the
        // file it was given; a simulation is bounded only by what the user
        // asked for, and each one is seconds of backend CPU.
        Route::post('/simulator', [SimulatorController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('simulator.store');

        Route::get('/simulation/{simulation}', [SimulatorController::class, 'show'])->name('simulator.show');
        Route::get('/simulation/{simulation}/export.csv', [SimulatorController::class, 'csv'])->name('simulator.csv');
        Route::get('/simulation/{simulation}/export.json', [SimulatorController::class, 'json'])->name('simulator.json');

        // Fourth tab: choose a memory architecture, and build it.
        Route::get('/memory', [MemoryController::class, 'index'])->name('memory.index');
        Route::post('/memory', [MemoryController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('memory.store');

        Route::get('/design/{design}', [MemoryController::class, 'show'])->name('memory.show');
        Route::get('/design/{design}/memory.fasta', [MemoryController::class, 'fasta'])->name('memory.fasta');
        Route::get('/design/{design}/export.json', [MemoryController::class, 'json'])->name('memory.json');
    });
