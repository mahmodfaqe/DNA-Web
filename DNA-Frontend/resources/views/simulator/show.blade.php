@extends('layouts.app')

@section('title', __('simulator.result.title'))

@section('header-actions')
    @if ($simulation->succeeded)
        <div class="hidden items-center gap-2 sm:flex">
            <a href="{{ route('simulator.csv', ['simulation' => $simulation->id]) }}" class="btn btn-quiet btn-sm">{{ __('common.actions.csv') }}</a>
            <a href="{{ route('simulator.json', ['simulation' => $simulation->id]) }}" class="btn btn-quiet btn-sm">{{ __('common.actions.json') }}</a>
        </div>
    @endif
@endsection

@section('content')
    <x-tabs active="simulator" />

    @php
        $request = $simulation->request();
        $genes = $simulation->genes();
        $statistics = $simulation->statistics();
        $crosstalk = $simulation->crosstalk();
        $performance = $simulation->performance();
        $time = $simulation->time();

        // Gene identity is carried by position and label first; colour only
        // reinforces it. Assigned by the gene's place in the network and never
        // by its rank in any measurement, so a gene keeps its colour across
        // every chart on the page.
        $palette = ['var(--color-brand-500)', 'var(--color-signal-500)', 'var(--color-good-600)'];
        $colours = [];
        foreach ($genes as $index => $gene) {
            $colours[$gene['id']] = $palette[$index % count($palette)];
        }

        $name = fn (array $gene) => __('simulator.genes.' . $gene['label']);
        $precision = $simulation->precision();
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow">{{ __('simulator.result.title') }}</p>
            <h1 class="mt-1 text-lg font-semibold leading-snug">
                {{ __('simulator.presets.' . $simulation->preset . '.name') }}
            </h1>
            <p class="mt-1 text-xs text-ink-400">
                {{ $simulation->created_at->diffForHumans() }}
                · <span class="ltr-data">{{ $simulation->cells }}</span> {{ __('simulator.units.cells') }}
                · <span class="ltr-data">{{ $simulation->minutes }}</span> {{ __('simulator.units.min') }}
                · {{ __('simulator.result.seed') }} <span class="ltr-data">{{ $simulation->seed }}</span>
            </p>
        </div>
        <a href="{{ route('simulator.index') }}" class="btn btn-quiet no-print">{{ __('simulator.result.run_another') }}</a>
    </div>

    @unless ($simulation->succeeded)
        <div class="alert mb-6" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
            </svg>
            <div>
                <p class="font-semibold">{{ __('simulator.result.failed') }}</p>
                <p class="mt-1">{{ __('simulator.result.failed_hint') }}</p>
            </div>
        </div>
    @endunless

    <div class="space-y-6">
        @if ($simulation->succeeded)
            @php
                $loudest = collect($statistics)->sortByDesc('cv')->first();
                $worstCrosstalk = $simulation->worstCrosstalk();
            @endphp

            <div class="panel metric-strip overflow-hidden">
                <div class="metric">
                    <p class="metric-value">{{ number_format(($loudest['cv'] ?? 0) * 100, 0) }}%</p>
                    <p class="metric-label">{{ __('simulator.metrics.noisiest') }}</p>
                    <p class="metric-sub ltr-data">{{ $loudest['id'] ?? '' }} · ±{{ round($precision * 100) }}%</p>
                </div>
                <div class="metric">
                    <p class="metric-value">{{ number_format($loudest['fano'] ?? 0, 1) }}</p>
                    <p class="metric-label">{{ __('simulator.metrics.fano') }}</p>
                    <p class="metric-sub">{{ __('simulator.metrics.fano_sub') }}</p>
                </div>
                <div class="metric">
                    <p class="metric-value {{ $worstCrosstalk > 0.15 ? 'text-signal-600' : '' }}">
                        {{ number_format($worstCrosstalk * 100, 0) }}%
                    </p>
                    <p class="metric-label">{{ __('simulator.metrics.crosstalk') }}</p>
                    <p class="metric-sub">{{ __('simulator.metrics.crosstalk_sub') }}</p>
                </div>
                <div class="metric">
                    <p class="metric-value {{ ($performance['availability'] ?? 1) < 0.85 ? 'text-signal-600' : '' }}">
                        {{ number_format(($performance['availability'] ?? 1) * 100, 0) }}%
                    </p>
                    <p class="metric-label">{{ __('simulator.metrics.availability') }}</p>
                    <p class="metric-sub">{{ __('simulator.metrics.availability_sub') }}</p>
                </div>
                <div class="metric">
                    <p class="metric-value ltr-data">{{ number_format($performance['events'] ?? 0) }}</p>
                    <p class="metric-label">{{ __('simulator.metrics.events') }}</p>
                    <p class="metric-sub ltr-data">{{ number_format(($performance['wall_ms'] ?? 0) / 1000, 1) }} s</p>
                </div>
            </div>

            {{-- Trajectories -------------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('simulator.trajectories.title') }}</h2>
                        <p class="panel-note">{{ __('simulator.trajectories.subtitle') }}</p>
                    </div>
                </div>

                <div class="divide-y divide-line">
                    @foreach ($genes as $gene)
                        @php $entry = $statistics[$gene['id']] ?? []; @endphp
                        <div class="p-5">
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <span class="flex items-center gap-2 text-sm font-bold">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full"
                                          style="background: {{ $colours[$gene['id']] }};" aria-hidden="true"></span>
                                    <span class="ltr-data">{{ $gene['id'] }}</span>
                                    <span class="font-semibold text-ink-700">{{ $name($gene) }}</span>
                                </span>
                                <span class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-400">
                                    <span class="ltr-data">
                                        {{ __('simulator.trajectories.mean') }}
                                        {{ number_format($entry['mean_protein'] ?? 0, 0) }}
                                    </span>
                                    <span class="ltr-data">CV {{ number_format(($entry['cv'] ?? 0) * 100, 0) }}%</span>
                                    <span class="ltr-data">
                                        {{ __('simulator.trajectories.burst') }}
                                        {{ number_format($entry['burst_size'] ?? 0, 1) }}
                                    </span>
                                </span>
                            </div>

                            <x-trajectory-chart :series="$simulation->trajectories()[$gene['id']] ?? []"
                                                :time="$time"
                                                :colour="$colours[$gene['id']]"
                                                :label="$name($gene)" />
                        </div>
                    @endforeach
                </div>

                <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                    {{ __('simulator.trajectories.legend') }}
                </p>
            </section>

            {{-- Distributions -------------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('simulator.distributions.title') }}</h2>
                        <p class="panel-note">{{ __('simulator.distributions.subtitle') }}</p>
                    </div>
                </div>
                <div class="grid gap-6 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($genes as $gene)
                        <div>
                            <p class="mb-1 flex items-center gap-2 text-xs font-bold">
                                <span class="inline-block h-2.5 w-2.5 rounded-full"
                                      style="background: {{ $colours[$gene['id']] }};" aria-hidden="true"></span>
                                <span class="ltr-data">{{ $gene['id'] }}</span>
                                <span class="font-semibold text-ink-700">{{ $name($gene) }}</span>
                            </p>
                            <x-distribution-chart :shape="$simulation->distributions()[$gene['id']] ?? []"
                                                  :statistics="$statistics[$gene['id']] ?? []"
                                                  :colour="$colours[$gene['id']]"
                                                  :label="$name($gene)" />
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Crosstalk ------------------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('simulator.crosstalk.title') }}</h2>
                        <p class="panel-note">{{ __('simulator.crosstalk.subtitle') }}</p>
                    </div>
                </div>

                <div class="space-y-6 p-5">
                    {{-- Where each gene's transcripts came from. This is the
                         direct measurement; the matrices below are the indirect
                         one, and they do not always agree. --}}
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-ink-500">
                            {{ __('simulator.crosstalk.attribution_title') }}
                        </h3>

                        <div class="space-y-3">
                            @foreach ($genes as $gene)
                                @php $share = $simulation->attribution()[$gene['id']] ?? []; @endphp
                                <div>
                                    <div class="mb-1 flex items-baseline justify-between gap-3">
                                        <span class="text-xs font-bold">
                                            <span class="ltr-data">{{ $gene['id'] }}</span>
                                            <span class="ms-1 font-semibold text-ink-500">{{ $name($gene) }}</span>
                                        </span>
                                        <span class="ltr-data text-[0.6875rem] text-ink-400">
                                            {{ number_format($share['transcripts'] ?? 0) }}
                                            {{ __('simulator.crosstalk.transcripts') }}
                                        </span>
                                    </div>
                                    <div class="track flex h-5 w-full gap-0.5 overflow-hidden rounded-md">
                                        @foreach ([
                                            'cognate' => 'var(--color-good-600)',
                                            'crosstalk' => 'var(--color-signal-500)',
                                            'leak' => 'var(--color-ink-300)',
                                        ] as $source => $colour)
                                            @php $fraction = (float) ($share[$source] ?? 0); @endphp
                                            @if ($fraction > 0.0005)
                                                <div class="flex items-center justify-center overflow-hidden text-[9px] font-semibold text-white"
                                                     style="width: {{ round($fraction * 100, 3) }}%; background: {{ $colour }};"
                                                     title="{{ __('simulator.crosstalk.' . $source) }} — {{ round($fraction * 100, 1) }}%">
                                                    @if ($fraction > 0.12)
                                                        <span class="truncate px-1">{{ round($fraction * 100) }}%</span>
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.6875rem] text-ink-500">
                            @foreach ([
                                'cognate' => 'var(--color-good-600)',
                                'crosstalk' => 'var(--color-signal-500)',
                                'leak' => 'var(--color-ink-300)',
                            ] as $source => $colour)
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="inline-block h-2.5 w-4 rounded-sm" style="background: {{ $colour }};" aria-hidden="true"></span>
                                    {{ __('simulator.crosstalk.' . $source) }}
                                </span>
                            @endforeach
                        </p>
                    </div>

                    {{-- The two matrices side by side. The gap between them is
                         the lesson: most of what a microscope measures as
                         "these genes are related" is cells differing from one
                         another, not the genes talking. --}}
                    <div class="border-t border-line pt-5">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <x-crosstalk-matrix :genes="$crosstalk['genes'] ?? []"
                                                :matrix="$crosstalk['correlation'] ?? []"
                                                :caption="__('simulator.crosstalk.measured')"
                                                :note="__('simulator.crosstalk.measured_note')" />

                            <x-crosstalk-matrix :genes="$crosstalk['genes'] ?? []"
                                                :matrix="$crosstalk['partial'] ?? []"
                                                :caption="__('simulator.crosstalk.partial')"
                                                :note="__('simulator.crosstalk.partial_note', [
                                                    'measured' => __('simulator.crosstalk.measured'),
                                                ])" />
                        </div>

                        {{-- One legend for both grids. It carries the reading and
                             not just the colours: "+1" and "−1" mean nothing to
                             someone who has not been told what moving together
                             implies. --}}
                        <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.6875rem] text-ink-500">
                            @foreach ([
                                'opposed' => 'rgba(179, 53, 42, .82)',
                                'independent' => 'var(--color-paper)',
                                'together' => 'rgba(43, 80, 143, .82)',
                            ] as $reading => $colour)
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="inline-block h-2.5 w-4 rounded-sm border border-line"
                                          style="background: {{ $colour }};" aria-hidden="true"></span>
                                    {{ __('simulator.crosstalk.' . $reading) }}
                                </span>
                            @endforeach
                        </p>
                    </div>
                </div>
            </section>

            {{-- Noise budget ---------------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('simulator.budget.title') }}</h2>
                        <p class="panel-note">{{ __('simulator.budget.subtitle') }}</p>
                    </div>
                </div>

                <div class="space-y-5 p-5">
                    @foreach ($genes as $gene)
                        <x-noise-budget :budget="($statistics[$gene['id']]['noise_budget'] ?? [])"
                                        :label="$gene['id'] . ' — ' . $name($gene)" />
                    @endforeach

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line pt-4 text-[0.6875rem] text-ink-500">
                        @foreach ([
                            'floor' => 'var(--color-brand-100)',
                            'bursting' => 'var(--color-brand-200)',
                            'extrinsic' => 'var(--color-brand-400)',
                            'promoter' => 'var(--color-brand-600)',
                            'coupling' => 'var(--color-signal-500)',
                        ] as $source => $colour)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-4 rounded-sm border border-line" style="background: {{ $colour }};" aria-hidden="true"></span>
                                {{ __('simulator.budget.' . $source) }}
                            </span>
                        @endforeach
                    </div>

                    @if (! ($performance['control_ensemble'] ?? false))
                        <p class="text-xs text-ink-400">{{ __('simulator.budget.no_control') }}</p>
                    @endif
                </div>
            </section>

            {{-- Intrinsic and extrinsic --------------------------------------}}
            @if ($split = $simulation->decomposition())
                <section class="panel overflow-hidden">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title">{{ __('simulator.decomposition.title') }}</h2>
                            <p class="panel-note">{{ __('simulator.decomposition.subtitle') }}</p>
                        </div>
                    </div>

                    <div class="p-5">
                        @php
                            $total = max(0.000001, (float) $split['total']);
                            $intrinsicShare = (float) $split['intrinsic'] / $total * 100;
                        @endphp

                        <div class="track flex h-8 w-full gap-0.5 overflow-hidden rounded-md">
                            <div class="flex items-center justify-center text-[10px] font-bold text-white"
                                 style="width: {{ round($intrinsicShare, 2) }}%; background: var(--color-brand-600);">
                                {{ round($intrinsicShare) }}%
                            </div>
                            <div class="flex items-center justify-center text-[10px] font-bold text-white"
                                 style="width: {{ round(100 - $intrinsicShare, 2) }}%; background: var(--color-signal-500);">
                                {{ round(100 - $intrinsicShare) }}%
                            </div>
                        </div>

                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="flex items-center gap-1.5 text-xs font-bold">
                                    <span class="inline-block h-2.5 w-4 rounded-sm" style="background: var(--color-brand-600);" aria-hidden="true"></span>
                                    {{ __('simulator.decomposition.intrinsic') }}
                                    <span class="ltr-data font-normal text-ink-400">η² {{ number_format($split['intrinsic'], 4) }}</span>
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-ink-500">
                                    {{ __('simulator.decomposition.intrinsic_note') }}
                                </p>
                            </div>
                            <div>
                                <p class="flex items-center gap-1.5 text-xs font-bold">
                                    <span class="inline-block h-2.5 w-4 rounded-sm" style="background: var(--color-signal-500);" aria-hidden="true"></span>
                                    {{ __('simulator.decomposition.extrinsic') }}
                                    <span class="ltr-data font-normal text-ink-400">η² {{ number_format($split['extrinsic'], 4) }}</span>
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-ink-500">
                                    {{ __('simulator.decomposition.extrinsic_note') }}
                                </p>
                            </div>
                        </div>

                        <p class="mt-3 border-t border-line pt-3 text-xs text-ink-400">
                            {{ __('simulator.decomposition.method', [
                                'first' => $split['pair'][0],
                                'second' => $split['pair'][1],
                            ]) }}
                        </p>
                    </div>
                </section>
            @endif

            {{-- Bistability ---------------------------------------------------}}
            @if ($flips = $simulation->switching())
                <section class="panel overflow-hidden">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title">{{ __('simulator.switching.title') }}</h2>
                            <p class="panel-note">{{ __('simulator.switching.subtitle') }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-3">
                        <div>
                            <p class="metric-value">{{ $flips['switches'] }}</p>
                            <p class="metric-label">{{ __('simulator.switching.flips') }}</p>
                        </div>
                        <div>
                            <p class="metric-value">
                                {{ $flips['cells_that_switched'] }}<span class="text-ink-300">/{{ $flips['cells'] }}</span>
                            </p>
                            <p class="metric-label">{{ __('simulator.switching.cells') }}</p>
                        </div>
                        <div>
                            <p class="metric-value">
                                {{ $flips['mean_dwell_minutes'] !== null ? number_format($flips['mean_dwell_minutes'], 0) : '—' }}
                            </p>
                            <p class="metric-label">{{ __('simulator.switching.dwell') }}</p>
                        </div>
                    </div>
                    <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                        {{ __('simulator.switching.note') }}
                    </p>
                </section>
            @endif

            {{-- The numbers ---------------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('simulator.table.title') }}</h2>
                        <p class="panel-note">{{ __('simulator.table.subtitle', ['percent' => round($precision * 100)]) }}</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th scope="col">{{ __('simulator.table.gene') }}</th>
                            <th scope="col">{{ __('simulator.table.protein') }}</th>
                            <th scope="col">{{ __('simulator.table.mrna') }}</th>
                            <th scope="col">CV</th>
                            <th scope="col">{{ __('simulator.table.fano') }}</th>
                            <th scope="col">{{ __('simulator.table.predicted') }}</th>
                            <th scope="col">{{ __('simulator.table.burst') }}</th>
                            <th scope="col">{{ __('simulator.table.independent') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($genes as $gene)
                            @php $entry = $statistics[$gene['id']] ?? []; @endphp
                            <tr>
                                <td>
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                              style="background: {{ $colours[$gene['id']] }};" aria-hidden="true"></span>
                                        <span>
                                            <span class="ltr-data font-bold">{{ $gene['id'] }}</span>
                                            <span class="block text-xs text-ink-400">{{ $name($gene) }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td class="ltr-data">
                                    {{ number_format($entry['mean_protein'] ?? 0, 0) }}
                                    <span class="text-ink-400">± {{ number_format($entry['sd_protein'] ?? 0, 0) }}</span>
                                </td>
                                <td class="ltr-data">{{ number_format($entry['mean_mrna'] ?? 0, 2) }}</td>
                                <td class="ltr-data">{{ number_format(($entry['cv'] ?? 0) * 100, 1) }}%</td>
                                <td class="ltr-data font-bold">{{ number_format($entry['fano'] ?? 0, 1) }}</td>
                                <td class="ltr-data text-ink-400">{{ number_format($entry['analytic_fano'] ?? 0, 1) }}</td>
                                <td class="ltr-data">{{ number_format($entry['burst_size'] ?? 0, 1) }}</td>
                                <td class="ltr-data text-ink-400">{{ number_format($entry['effective_samples'] ?? 0, 0) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                    {{ __('simulator.table.note') }}
                </p>
            </section>

            {{-- What was actually run ------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('simulator.conditions.title') }}</h2>
                        <p class="panel-note">{{ __('simulator.conditions.subtitle') }}</p>
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-3 p-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        'induction' => round(($request['induction'] ?? 0) * 100) . '%',
                        'crosstalk' => round(($request['crosstalk'] ?? 0) * 100) . '%',
                        'variability' => round(($request['variability'] ?? 0) * 100) . '%',
                        'cells' => $simulation->cells,
                        'duration' => $simulation->minutes . ' ' . __('simulator.units.min'),
                        'seed' => $simulation->seed,
                    ] as $field => $value)
                        <div class="flex items-baseline justify-between gap-3 border-b border-line pb-2">
                            <dt class="text-xs text-ink-500">{{ __('simulator.form.' . $field) }}</dt>
                            <dd class="ltr-data text-xs font-bold">{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div class="flex items-baseline justify-between gap-3 border-b border-line pb-2">
                        <dt class="text-xs text-ink-500">{{ __('simulator.form.resources') }}</dt>
                        <dd class="text-xs font-bold">
                            {{ ($request['resource_coupling'] ?? false) ? __('simulator.conditions.on') : __('simulator.conditions.off') }}
                        </dd>
                    </div>
                </dl>
            </section>
        @endif

        <x-diagnostics :items="$simulation->diagnostics()"
                       :counts="$simulation->diagnosticCounts()"
                       namespace="simulator" />
    </div>
@endsection
