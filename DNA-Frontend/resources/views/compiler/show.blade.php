@extends('layouts.app')

@section('title', __('compiler.result.title'))

@section('header-actions')
    @if ($circuit->succeeded)
        <div class="hidden items-center gap-2 sm:flex">
            <a href="{{ route('compiler.fasta', ['circuit' => $circuit->id]) }}" class="btn btn-quiet btn-sm">FASTA</a>
            <a href="{{ route('compiler.json', ['circuit' => $circuit->id]) }}" class="btn btn-quiet btn-sm">{{ __('common.actions.json') }}</a>
        </div>
    @endif
@endsection

@section('content')
    <x-tabs active="compiler" />

    @php
        $totals = $circuit->totals();
        $counts = $circuit->diagnosticCounts();
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow">{{ __('compiler.result.title') }}</p>
            <p class="mt-1 max-w-2xl text-lg font-semibold leading-snug">{{ $circuit->source_text }}</p>
            <p class="mt-1 text-xs text-ink-400">
                {{ __('compiler.result.created') }} {{ $circuit->created_at->diffForHumans() }}
                · {{ __('compiler.result.language') }}:
                {{ __('compiler.languages.' . ($circuit->language ?: 'unknown')) }}
            </p>
        </div>
        <a href="{{ route('compiler.index') }}" class="btn btn-quiet no-print">{{ __('common.actions.new_analysis') }}</a>
    </div>

    @if ($circuit->expression)
        <div class="panel mb-6 p-4">
            <p class="eyebrow">{{ __('compiler.result.expression') }}</p>
            <p class="ltr-data mt-1.5 text-sm font-bold text-brand-600">{{ $circuit->expression }}</p>
        </div>
    @endif

    @unless ($circuit->succeeded)
        <div class="alert mb-6" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
            </svg>
            <div>
                <p class="font-semibold">{{ __('compiler.result.failed') }}</p>
                <p class="mt-1">{{ __('compiler.result.failed_hint') }}</p>
            </div>
        </div>
    @endunless

    <div class="space-y-6">
        @if ($circuit->succeeded)
            <div class="panel metric-strip overflow-hidden">
                <div class="metric">
                    <p class="metric-value">{{ $totals['units'] ?? 0 }}</p>
                    <p class="metric-label">{{ __('compiler.metrics.units') }}</p>
                </div>
                <div class="metric">
                    <p class="metric-value">{{ number_format($totals['length'] ?? 0) }}</p>
                    <p class="metric-label">{{ __('compiler.metrics.length') }} ({{ __('analysis.units.bp') }})</p>
                </div>
                <div class="metric">
                    <p class="metric-value">{{ count($circuit->parts()) }}</p>
                    <p class="metric-label">{{ __('compiler.metrics.parts') }}</p>
                </div>
                <div class="metric">
                    <p class="metric-value">{{ $totals['resolved_percent'] ?? 0 }}%</p>
                    <p class="metric-label">{{ __('compiler.metrics.resolved') }}</p>
                </div>
                <div class="metric">
                    <p class="metric-value {{ ($counts['warning'] ?? 0) > 0 ? 'text-signal-600' : '' }}">
                        {{ $counts['warning'] ?? 0 }}
                    </p>
                    <p class="metric-label">{{ __('compiler.metrics.warnings') }}</p>
                </div>
            </div>

            @if ($circuit->gates())
                <section class="panel overflow-hidden">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title">{{ __('compiler.logic.title') }}</h2>
                            <p class="panel-note">{{ __('compiler.logic.subtitle') }}</p>
                        </div>
                    </div>
                    <div class="p-5">
                        <x-circuit-diagram :gates="$circuit->gates()" />
                    </div>
                </section>
            @endif

            {{-- FASTA is one link in the header; these are a panel of their own,
                 because they are the difference between a sequence and a design.
                 A reader who has never met SBOL needs to be told which tool each
                 file opens in, so the format name alone is not the label. --}}
            <section class="panel overflow-hidden no-print">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('compiler.export.title') }}</h2>
                        <p class="panel-note">{{ __('compiler.export.note') }}</p>
                    </div>
                </div>

                <ul class="divide-y divide-line">
                    @foreach (['sbol', 'genbank'] as $format)
                        <li>
                            <a href="{{ route('compiler.export', ['circuit' => $circuit->id, 'format' => $format]) }}"
                               class="flex items-center justify-between gap-4 px-5 py-3.5 transition hover:bg-paper">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold">{{ __('compiler.export.' . $format) }}</span>
                                    <span class="ltr-data block text-xs text-ink-400">{{ __('compiler.export.' . $format . '_note') }}</span>
                                </span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                     class="h-4 w-4 shrink-0 text-ink-400" aria-hidden="true">
                                    <path d="M12 4v12M7 12l5 5 5-5M5 20h14" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <p class="border-t border-line bg-paper px-5 py-3 text-xs text-ink-500">
                    {{ __('compiler.export.placeholder_warning') }}
                </p>
            </section>

            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('compiler.construct.title') }}</h2>
                        <p class="panel-note">{{ __('compiler.construct.subtitle') }}</p>
                    </div>
                    <a href="{{ route('compiler.fasta', ['circuit' => $circuit->id]) }}" class="btn btn-quiet btn-sm no-print">
                        {{ __('compiler.construct.download') }}
                    </a>
                </div>

                <div class="space-y-6 p-5">
                    @foreach ($circuit->units() as $unit)
                        <div>
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <span class="ltr-data text-sm font-bold">{{ $unit['name'] }}</span>
                                <span class="flex items-center gap-2 text-xs text-ink-400">
                                    <span class="chip chip-muted">{{ __('compiler.gates.' . $unit['purpose']) }}</span>
                                    <span class="ltr-data">{{ number_format($unit['length']) }} {{ __('analysis.units.bp') }}</span>
                                    <span class="ltr-data">{{ $unit['known_fraction'] }}% {{ __('compiler.construct.resolved') }}</span>
                                </span>
                            </div>
                            <x-part-map :unit="$unit" />
                        </div>
                    @endforeach

                    <div class="track flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line pt-4 text-[0.6875rem] text-ink-500">
                        {{-- Full class names, never "bg-{$token}": Tailwind scans the
                             source as text and cannot see a class assembled at runtime,
                             so an interpolated colour silently renders as no colour. --}}
                        @foreach ([
                            'promoter' => 'var(--color-brand-500)',
                            'rbs' => 'var(--color-signal-500)',
                            'cds' => 'var(--color-good-600)',
                            'terminator' => 'var(--color-alert-600)',
                            'tag' => 'var(--color-ink-500)',
                        ] as $role => $colour)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-4 rounded-sm" style="background: {{ $colour }};" aria-hidden="true"></span>
                                {{ __('compiler.roles.' . $role) }}
                            </span>
                        @endforeach
                        <span class="text-ink-400">{{ __('compiler.provenance.placeholder_note') }}</span>
                    </div>
                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('compiler.parts.title') }}</h2>
                        <p class="panel-note">{{ __('compiler.parts.subtitle') }}</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th scope="col">{{ __('compiler.parts.id') }}</th>
                            <th scope="col">{{ __('compiler.parts.name') }}</th>
                            <th scope="col">{{ __('compiler.parts.role') }}</th>
                            <th scope="col">{{ __('compiler.parts.provenance') }}</th>
                            <th scope="col">{{ __('compiler.parts.length') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($circuit->parts() as $part)
                            <tr>
                                <td class="ltr-data font-bold">
                                    @if ($part['registry_url'])
                                        <a href="{{ $part['registry_url'] }}" target="_blank" rel="noopener noreferrer"
                                           class="text-brand-600 underline underline-offset-2">{{ $part['id'] }}</a>
                                    @else
                                        {{ $part['id'] }}
                                    @endif
                                </td>
                                <td class="text-ink-700">{{ $part['name'] }}</td>
                                <td>{{ __('compiler.roles.' . $part['role']) }}</td>
                                <td>
                                    <span class="chip {{ match ($part['provenance']) {
                                        'literal' => 'chip-good',
                                        'designed' => 'chip-signal',
                                        default => 'chip-muted',
                                    } }}">
                                        {{ __('compiler.provenance.' . $part['provenance']) }}
                                    </span>
                                </td>
                                <td class="ltr-data">{{ number_format($part['length']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <x-diagnostics :items="$circuit->diagnostics()"
                       :counts="$counts"
                       namespace="compiler" />
    </div>
@endsection
