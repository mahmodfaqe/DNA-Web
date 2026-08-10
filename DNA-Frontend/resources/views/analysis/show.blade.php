@extends('layouts.app')

@section('title', __('analysis.result.title'))

@section('header-actions')
    <div class="hidden items-center gap-2 sm:flex">
        <a href="{{ route('analysis.csv', ['analysis' => $analysis->id]) }}" class="btn btn-quiet btn-sm">
            {{ __('common.actions.csv') }}
        </a>
        <a href="{{ route('analysis.json', ['analysis' => $analysis->id]) }}" class="btn btn-quiet btn-sm">
            {{ __('common.actions.json') }}
        </a>
        <a href="{{ route('analysis.print', ['analysis' => $analysis->id]) }}" class="btn btn-quiet btn-sm">
            {{ __('common.actions.print') }}
        </a>
    </div>
@endsection

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow">{{ __('analysis.result.title') }}</p>
            <h1 class="ltr-data mt-1 truncate text-2xl font-bold tracking-tight">{{ $analysis->filename }}</h1>
            <p class="mt-1 text-xs text-ink-400">
                {{ __('analysis.result.created') }} {{ $analysis->created_at->diffForHumans() }}
                · {{ __('analysis.result.checksum') }}
                <span class="ltr-data">{{ $analysis->checksum }}</span>
            </p>
        </div>

        <a href="{{ route('analysis.index') }}" class="btn btn-quiet no-print">
            {{ __('common.actions.new_analysis') }}
        </a>
    </div>

    <div class="space-y-6">
        @include('partials.metrics')
        @include('partials.tracks')
        @include('partials.records-table')
        @include('partials.comparison')
    </div>

    <div class="mt-6 flex gap-2 sm:hidden">
        <a href="{{ route('analysis.csv', ['analysis' => $analysis->id]) }}" class="btn btn-quiet flex-1">{{ __('common.actions.csv') }}</a>
        <a href="{{ route('analysis.print', ['analysis' => $analysis->id]) }}" class="btn btn-quiet flex-1">{{ __('common.actions.print') }}</a>
    </div>

    {{-- Native <dialog> supplies focus trapping, Escape handling and backdrop
         inertness, so none of it has to be hand-rolled. --}}
    <dialog data-protein-dialog
            class="w-[min(46rem,92vw)] rounded-xl border border-line p-0 backdrop:bg-ink-900/50">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-3">
            <div>
                <h2 class="ltr-data text-sm font-bold" data-protein-title></h2>
                <p class="text-xs text-ink-400">{{ __('analysis.orf.protein_title') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-copy class="btn btn-quiet btn-sm">{{ __('common.actions.copy') }}</button>
                <button type="button" data-close class="btn btn-quiet btn-sm">{{ __('common.actions.close') }}</button>
            </div>
        </div>
        <pre data-protein-sequence class="code-block max-h-[60vh] overflow-auto rounded-none whitespace-pre-wrap break-all"></pre>
    </dialog>
@endsection
