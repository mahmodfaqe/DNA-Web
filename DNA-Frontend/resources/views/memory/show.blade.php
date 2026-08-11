@extends('layouts.app')

@section('title', __('memory.result.title'))

@section('header-actions')
    @if ($design->succeeded)
        <div class="hidden items-center gap-2 sm:flex">
            <a href="{{ route('memory.fasta', ['design' => $design->id]) }}" class="btn btn-quiet btn-sm">FASTA</a>
            <a href="{{ route('memory.json', ['design' => $design->id]) }}" class="btn btn-quiet btn-sm">{{ __('common.actions.json') }}</a>
        </div>
    @endif
@endsection

@section('content')
    <x-tabs active="memory" />

    @php
        $request = $design->request();
        $recommendation = $design->recommendation();
        $winner = $recommendation['architecture'] ?? null;
        $comparison = collect($design->comparison());

        // Colour follows the architecture, not its rank, so the winner does not
        // change colour when the parameters do.
        $colours = [
            'recombinase' => 'var(--color-brand-500)',
            'recombinase_reversible' => 'var(--color-brand-500)',
            'toggle' => 'var(--color-signal-500)',
        ];

        $writeMinutes = (float) ($request['signal_minutes'] ?? 60);
        $holdMinutes = (float) ($request['hold_hours'] ?? 24) * 60;

        // Both architectures reduced to one comparable quantity: how much of the
        // memory is set. See the chart component for why this matters.
        $state = function (array $outcome, string $phase) {
            $found = collect($outcome['phases'] ?? [])->firstWhere('name', $phase);
            if (! $found) {
                return [];
            }

            $series = $found['series'];
            $points = [];
            foreach ($found['minutes'] as $index => $minute) {
                if (isset($series['flipped'])) {
                    $value = $series['flipped'][$index] ?? 0;
                } else {
                    $set = $series['set'][$index] ?? 0;
                    $reset = $series['reset'][$index] ?? 0;
                    $value = ($set + $reset) > 0 ? $set / ($set + $reset) : 0;
                }
                $points[] = [$minute, $value];
            }
            return $points;
        };

        $lines = fn (string $phase) => $design->comparison() ? array_values(array_map(
            fn ($entry) => [
                'label' => __('memory.architectures.' . $entry['architecture'] . '.name'),
                'colour' => $colours[$entry['architecture']] ?? 'var(--color-ink-500)',
                'points' => $state($design->outcomeFor($entry['architecture']), $phase),
            ],
            $design->comparison()
        )) : [];

        $hours = function (?float $value) {
            if ($value === null) {
                return null;
            }
            return $value;
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow">{{ __('memory.result.title') }}</p>
            <h1 class="mt-1 text-lg font-semibold leading-snug">
                {{ __('memory.signals.' . $design->signal) }} → {{ __('memory.chassis.' . $design->chassis) }}
            </h1>
            <p class="mt-1 text-xs text-ink-400">
                {{ $design->created_at->diffForHumans() }}
                · {{ __('memory.result.hold') }} <span class="ltr-data">{{ $design->hold_hours }}</span> {{ __('memory.units.hours') }}
                · {{ __('memory.result.exposure') }} <span class="ltr-data">{{ round($writeMinutes) }}</span> {{ __('memory.units.min') }}
            </p>
        </div>
        <a href="{{ route('memory.index') }}" class="btn btn-quiet no-print">{{ __('memory.result.design_another') }}</a>
    </div>

    @unless ($design->succeeded)
        <div class="alert mb-6" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
            </svg>
            <div>
                <p class="font-semibold">{{ __('memory.result.refused') }}</p>
                <p class="mt-1">{{ __('memory.result.refused_hint') }}</p>
            </div>
        </div>
    @endunless

    <div class="space-y-6">
        @if ($design->succeeded && $winner)
            {{-- The verdict, and immediately underneath it the reason. A
                 recommendation with nothing behind it is a guess in a suit. --}}
            <section class="panel overflow-hidden">
                <div class="p-5 sm:p-6">
                    <p class="eyebrow">{{ __('memory.result.recommended') }}</p>
                    <h2 class="mt-1.5 text-2xl font-bold leading-tight">
                        {{ __('memory.architectures.' . $winner . '.name') }}
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-ink-700">
                        {{ __('memory.architectures.' . $winner . '.why') }}
                    </p>

                    @if ($design->isCloseCall())
                        <p class="mt-3 inline-flex items-start gap-2 rounded-lg bg-signal-50 px-3 py-2 text-xs leading-relaxed text-signal-600">
                            {{ __('memory.result.close_call', [
                                'other' => __('memory.architectures.' . ($recommendation['runner_up'] ?? '') . '.name'),
                                'gap' => number_format(($recommendation['gap'] ?? 0) * 100, 1),
                            ]) }}
                        </p>
                    @endif
                </div>

                <div class="metric-strip border-t border-line">
                    @php $best = $comparison->firstWhere('architecture', $winner) ?? []; @endphp
                    <div class="metric">
                        <p class="metric-value">{{ number_format(($best['retention'] ?? 0) * 100, 0) }}%</p>
                        <p class="metric-label">{{ __('memory.metrics.retention') }}</p>
                    </div>
                    <div class="metric">
                        <p class="metric-value {{ ($best['false_write_share'] ?? 0) > 0.05 ? 'text-signal-600' : '' }}">
                            {{ number_format(($best['false_write_share'] ?? 0) * 100, 1) }}%
                        </p>
                        <p class="metric-label">{{ __('memory.metrics.false_writes') }}</p>
                    </div>
                    <div class="metric">
                        @php $out = $design->outcomeFor($winner); @endphp
                        <p class="metric-value ltr-data">
                            {{ $out['write_minutes_to_half'] !== null ? round($out['write_minutes_to_half']) : '—' }}
                        </p>
                        <p class="metric-label">{{ __('memory.metrics.write_time') }}</p>
                    </div>
                    <div class="metric">
                        <p class="metric-value">{{ $out['stores_in_dna'] ?? false ? __('memory.metrics.in_dna') : __('memory.metrics.in_protein') }}</p>
                        <p class="metric-label">{{ __('memory.metrics.stored_in') }}</p>
                    </div>
                    <div class="metric">
                        <p class="metric-value ltr-data">{{ number_format($design->totals()['length'] ?? 0) }}</p>
                        <p class="metric-label">{{ __('memory.metrics.length') }} ({{ __('analysis.units.bp') }})</p>
                    </div>
                </div>
            </section>

            {{-- The comparison ------------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('memory.compare.title') }}</h2>
                        <p class="panel-note">{{ __('memory.compare.subtitle') }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th scope="col">{{ __('memory.compare.criterion') }}</th>
                            @foreach ($design->comparison() as $entry)
                                <th scope="col">
                                    <span class="flex items-center gap-1.5">
                                        <span class="inline-block h-2.5 w-2.5 rounded-full"
                                              style="background: {{ $colours[$entry['architecture']] ?? 'var(--color-ink-500)' }};" aria-hidden="true"></span>
                                        {{ __('memory.architectures.' . $entry['architecture'] . '.name') }}
                                        @if ($entry['architecture'] === $winner)
                                            <span class="chip chip-good">{{ __('memory.compare.chosen') }}</span>
                                        @endif
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $rows = [
                                'retention' => fn ($e) => number_format($e['retention'] * 100, 0) . '%',
                                'fidelity' => fn ($e) => number_format($e['fidelity'] * 100, 0) . '%',
                                'speed' => fn ($e) => number_format($e['speed'] * 100, 0) . '%',
                                'cost' => fn ($e) => number_format($e['cost'] * 100, 0) . '%',
                            ];
                        @endphp

                        @foreach ($rows as $key => $format)
                            <tr>
                                <td>
                                    <span class="font-semibold">{{ __('memory.compare.' . $key) }}</span>
                                    <span class="block text-xs text-ink-400">{{ __('memory.compare.' . $key . '_note') }}</span>
                                </td>
                                @foreach ($design->comparison() as $entry)
                                    <td class="ltr-data {{ $entry['disqualified'] ? 'text-ink-300' : '' }}">
                                        {{ $format($entry) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        <tr>
                            <td>
                                <span class="font-semibold">{{ __('memory.compare.survives_division') }}</span>
                                <span class="block text-xs text-ink-400">{{ __('memory.compare.survives_division_note') }}</span>
                            </td>
                            @foreach ($design->comparison() as $entry)
                                @php $outcome = $design->outcomeFor($entry['architecture']); @endphp
                                <td>
                                    <span class="chip {{ ($outcome['stores_in_dna'] ?? false) ? 'chip-good' : 'chip-signal' }}">
                                        {{ ($outcome['stores_in_dna'] ?? false) ? __('memory.compare.yes_dna') : __('memory.compare.needs_expression') }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>

                        <tr>
                            <td>
                                <span class="font-semibold">{{ __('memory.compare.erasable') }}</span>
                                <span class="block text-xs text-ink-400">{{ __('memory.compare.erasable_note') }}</span>
                            </td>
                            @foreach ($design->comparison() as $entry)
                                @php $outcome = $design->outcomeFor($entry['architecture']); @endphp
                                <td>
                                    <span class="chip {{ ($outcome['reversible'] ?? false) ? 'chip-good' : 'chip-muted' }}">
                                        {{ ($outcome['reversible'] ?? false) ? __('memory.compare.yes') : __('memory.compare.no') }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>

                        <tr>
                            <td><span class="font-semibold">{{ __('memory.compare.total') }}</span></td>
                            @foreach ($design->comparison() as $entry)
                                <td class="ltr-data font-bold {{ $entry['architecture'] === $winner ? 'text-brand-600' : '' }}">
                                    @if ($entry['disqualified'])
                                        <span class="chip chip-alert">
                                            {{ __('memory.compare.excluded.' . ($entry['disqualified_reason'] ?? 'never_written')) }}
                                        </span>
                                    @else
                                        {{ number_format($entry['total'] * 100, 1) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        </tbody>
                    </table>
                </div>

                <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                    {{ __('memory.compare.weights') }}
                </p>
            </section>

            {{-- What the model did ---------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('memory.dynamics.title') }}</h2>
                        <p class="panel-note">{{ __('memory.dynamics.subtitle') }}</p>
                    </div>
                </div>

                <div class="grid gap-6 p-5 sm:grid-cols-2">
                    <x-memory-chart :series="$lines('write')"
                                    :span="$writeMinutes"
                                    :caption="__('memory.dynamics.write')"
                                    :unit="__('memory.units.min')" />

                    <x-memory-chart :series="$lines('hold')"
                                    :span="$holdMinutes"
                                    :caption="__('memory.dynamics.hold')"
                                    :unit="__('memory.units.min')" />
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line px-5 py-3 text-[0.6875rem] text-ink-500">
                    @foreach ($design->comparison() as $entry)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="inline-block h-2.5 w-4 rounded-sm"
                                  style="background: {{ $colours[$entry['architecture']] ?? 'var(--color-ink-500)' }};" aria-hidden="true"></span>
                            {{ __('memory.architectures.' . $entry['architecture'] . '.name') }}
                        </span>
                    @endforeach
                    <span class="text-ink-400">{{ __('memory.dynamics.legend') }}</span>
                </div>
            </section>

            {{-- Orientation ------------------------------------------------------}}
            @php $orientation = $design->orientation(); @endphp
            @if ($orientation)
                <section class="panel overflow-hidden">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title">{{ __('memory.orientation.title') }}</h2>
                            <p class="panel-note">{{ __('memory.orientation.subtitle') }}</p>
                        </div>
                        <span class="chip {{ $orientation['decided_by_sequence'] ? 'chip-good' : 'chip-muted' }}">
                            {{ __('memory.orientation.' . ($orientation['decided_by_sequence'] ? $orientation['preferred'] : 'either')) }}
                        </span>
                    </div>

                    <div class="grid gap-5 p-5 sm:grid-cols-2">
                        @foreach (['forward', 'reverse'] as $side)
                            @php
                                $panel = $orientation[$side];
                                $isChosen = $orientation['decided_by_sequence'] && $orientation['preferred'] === $side;
                            @endphp
                            <div class="rounded-xl border p-4 {{ $isChosen ? 'border-brand-600 bg-brand-50' : 'border-line' }}">
                                <div class="mb-2 flex items-baseline justify-between gap-2">
                                    <span class="text-sm font-bold">{{ __('memory.orientation.' . $side) }}</span>
                                    <span class="ltr-data text-xs text-ink-400">
                                        {{ __('memory.orientation.risk') }} {{ number_format($panel['risk'], 2) }}
                                    </span>
                                </div>

                                <dl class="space-y-1.5 text-xs">
                                    @foreach ([
                                        'promoters_outward' => $panel['counts']['promoters_outward'],
                                        'promoters_inward' => $panel['counts']['promoters_inward'],
                                        'terminators' => $panel['counts']['terminators'],
                                        'repeats' => $panel['counts']['repeats'],
                                    ] as $key => $value)
                                        <div class="flex items-baseline justify-between gap-3">
                                            <dt class="text-ink-500">{{ __('memory.orientation.' . $key) }}</dt>
                                            <dd class="ltr-data font-bold {{ $key === 'promoters_outward' && $value > 0 ? 'text-signal-600' : '' }}">
                                                {{ $value }}
                                            </dd>
                                        </div>
                                    @endforeach
                                    <div class="flex items-baseline justify-between gap-3 border-t border-line pt-1.5">
                                        <dt class="text-ink-500">GC</dt>
                                        <dd class="ltr-data font-bold">{{ number_format($panel['gc_percent'], 1) }}%</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                        {{ __('memory.orientation.explanation') }}
                        @if ($design->composition()['is_default_payload'] ?? false)
                            <span class="block mt-1 text-ink-400">{{ __('memory.orientation.default_payload') }}</span>
                        @endif
                    </p>
                </section>
            @endif

            {{-- The construct ----------------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('memory.construct.title') }}</h2>
                        <p class="panel-note">{{ __('memory.construct.subtitle') }}</p>
                    </div>
                    <a href="{{ route('memory.fasta', ['design' => $design->id]) }}" class="btn btn-quiet btn-sm no-print">
                        {{ __('memory.construct.download') }}
                    </a>
                </div>

                <div class="space-y-6 p-5">
                    @foreach ($design->constructs() as $unit)
                        <div>
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <span class="ltr-data text-sm font-bold">{{ $unit['name'] }}</span>
                                <span class="flex items-center gap-2 text-xs text-ink-400">
                                    <span class="chip chip-muted">{{ __('memory.purposes.' . $unit['purpose']) }}</span>
                                    <span class="ltr-data">{{ number_format($unit['length']) }} {{ __('analysis.units.bp') }}</span>
                                    <span class="ltr-data">{{ $unit['resolved_percent'] }}% {{ __('memory.construct.resolved') }}</span>
                                </span>
                            </div>
                            <x-part-map :unit="$unit" />
                        </div>
                    @endforeach

                    <div class="track flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line pt-4 text-[0.6875rem] text-ink-500">
                        @foreach ([
                            'promoter' => 'var(--color-brand-500)',
                            'rbs' => 'var(--color-signal-500)',
                            'cds' => 'var(--color-good-600)',
                            'att' => 'var(--color-brand-700)',
                            'payload' => 'var(--color-ink-700)',
                            'terminator' => 'var(--color-alert-600)',
                        ] as $role => $colour)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-4 rounded-sm" style="background: {{ $colour }};" aria-hidden="true"></span>
                                {{ __('memory.roles.' . $role) }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Parts -------------------------------------------------------------}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('memory.parts.title') }}</h2>
                        <p class="panel-note">{{ __('memory.parts.subtitle') }}</p>
                    </div>
                    @if ($design->synthesis()['difficult'] ?? false)
                        <span class="chip chip-signal">{{ __('memory.parts.difficult') }}</span>
                    @endif
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
                        @foreach ($design->parts() as $part)
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
                                <td>{{ __('memory.roles.' . $part['role']) }}</td>
                                <td>
                                    <span class="chip {{ $part['provenance'] === 'literal' ? 'chip-good' : 'chip-muted' }}">
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

        <x-diagnostics :items="$design->diagnostics()"
                       :counts="$design->diagnosticCounts()"
                       namespace="memory" />
    </div>
@endsection
