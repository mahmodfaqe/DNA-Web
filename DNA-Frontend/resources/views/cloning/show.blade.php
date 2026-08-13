@extends('layouts.app')

@section('title', __('cloning.result.title'))

@section('header-actions')
    <a href="{{ route('cloning.index') }}" class="btn btn-quiet btn-sm">{{ __('cloning.actions.new') }}</a>
@endsection

@section('content')
    <x-tabs active="cloning" />

    @php
        $digest = $plan->digest();
        $primers = $plan->primers();
        $amplicon = $plan->amplicon();
        $cutters = collect($plan->enzymes())->where('cut_count', '>', 0);
    @endphp

    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow">{{ __('cloning.result.title') }}</p>
            <h1 class="mt-1 truncate text-2xl font-bold tracking-tight">
                {{ $plan->label ?: __('cloning.recent.untitled') }}
            </h1>
            <p class="mt-1 text-xs text-ink-400">
                <span class="ltr-data">{{ __('cloning.recent.length', ['count' => $plan->template_length]) }}</span>
                · {{ __('cloning.result.topology_' . ($plan->circular ? 'circular' : 'linear')) }}
                · <span class="ltr-data">{{ __('cloning.result.panel_searched', ['count' => $digest['searched'] ?? 0]) }}</span>
            </p>
        </div>

        <div class="no-print flex flex-wrap gap-2">
            @if ($plan->order() !== [])
                <a href="{{ route('cloning.csv', ['plan' => $plan->id]) }}" class="btn btn-quiet btn-sm">
                    {{ __('cloning.actions.order_csv') }}
                </a>
            @endif
            @if (($amplicon['sequence'] ?? '') !== '')
                <a href="{{ route('cloning.fasta', ['plan' => $plan->id]) }}" class="btn btn-quiet btn-sm">
                    {{ __('cloning.actions.amplicon_fasta') }}
                </a>
            @endif
            <a href="{{ route('cloning.json', ['plan' => $plan->id]) }}" class="btn btn-quiet btn-sm">
                {{ __('cloning.actions.json') }}
            </a>
        </div>
    </header>

    {{-- The two lists a cloning strategy is actually chosen from, before any
         table of every enzyme. A reader who reads nothing else should read
         these. --}}
    <div class="grid gap-4 md:grid-cols-2">
        <section class="panel p-5">
            <h2 class="text-sm font-bold">{{ __('cloning.digest.unique_title') }}</h2>
            <p class="mt-1 text-xs text-ink-400">{{ __('cloning.digest.unique_note') }}</p>
            @if ($plan->uniqueCutters() === [])
                <p class="mt-3 text-sm text-signal-700">{{ __('cloning.digest.unique_empty') }}</p>
            @else
                <ul class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($plan->uniqueCutters() as $enzyme)
                        <li><span class="chip chip-good ltr-data">{{ $enzyme }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel p-5">
            <h2 class="text-sm font-bold">{{ __('cloning.digest.absent_title') }}</h2>
            <p class="mt-1 text-xs text-ink-400">{{ __('cloning.digest.absent_note') }}</p>
            @if ($plan->nonCutters() === [])
                <p class="mt-3 text-sm text-ink-500">{{ __('cloning.digest.absent_empty') }}</p>
            @else
                <ul class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($plan->nonCutters() as $enzyme)
                        <li><span class="chip chip-muted ltr-data">{{ $enzyme }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Primers with tails come before the full digest table: this is what the
         reader will paste into an order form, and burying it under 24 rows of
         enzyme data would be the wrong order of importance. --}}
    @if ($plan->hasTails())
        <section class="panel mt-4 overflow-hidden">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">{{ __('cloning.tails.title') }}</h2>
                    <p class="panel-note">{{ __('cloning.tails.subtitle') }}</p>
                </div>
            </div>

            <div class="divide-y divide-line">
                @foreach (['forward', 'reverse'] as $direction)
                    @php $tail = $plan->tail($direction); @endphp
                    @continue(empty($tail))

                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="chip chip-muted">{{ __('cloning.primers.' . $direction) }}</span>
                            <span class="chip chip-muted ltr-data">{{ $tail['enzyme'] }}</span>
                            @if (($tail['cuts_inside_amplicon'] ?? 0) > 0)
                                <span class="chip chip-alert">
                                    {{ __('cloning.tails.cuts_inside', ['count' => $tail['cuts_inside_amplicon']]) }}
                                </span>
                            @else
                                <span class="chip chip-good">{{ __('cloning.tails.cuts_inside_safe') }}</span>
                            @endif
                        </div>

                        {{-- The three segments coloured separately, because the
                             difference between "what you order" and "what
                             anneals in cycle one" is the whole point of the
                             panel and is invisible in a single string. --}}
                        <p class="ltr-data mt-3 break-all font-mono text-sm">
                            <span class="rounded bg-ink-100 px-1 py-0.5 text-ink-500">{{ $plan->tails()['clamp'] ?? '' }}</span><span
                                  class="rounded bg-signal-100 px-1 py-0.5 text-signal-800">{{ $tail['site'] }}</span><span
                                  class="rounded bg-brand-50 px-1 py-0.5 text-brand-700">{{ $tail['binding_region'] }}</span>
                        </p>

                        <dl class="mt-3 grid gap-x-6 gap-y-1.5 text-xs sm:grid-cols-2">
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.tails.binding_tm') }}</dt>
                                <dd class="ltr-data font-semibold">{{ $tail['binding_tm'] }} °C</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.tails.full_tm') }}</dt>
                                <dd class="ltr-data">{{ $tail['full_length_tm'] }} °C</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.tails.overhang') }}</dt>
                                <dd class="ltr-data">
                                    {{ __('cloning.digest.overhang_' . $tail['overhang']['kind'], ['sequence' => $tail['overhang']['sequence']]) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.primers.length') }}</dt>
                                <dd class="ltr-data">{{ $tail['length'] }}</dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>

            <p class="border-t border-line bg-paper px-5 py-3 text-xs text-ink-500">
                {{ __('cloning.tails.why_two') }}
            </p>
        </section>
    @endif

    {{-- The primer pair itself. --}}
    <section class="panel mt-4 overflow-hidden">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">{{ __('cloning.primers.title') }}</h2>
                @if ($amplicon !== [])
                    <p class="panel-note ltr-data">
                        {{ __('cloning.primers.amplicon_span', [
                            'start' => $amplicon['start'],
                            'end' => $amplicon['end'],
                            'length' => $amplicon['length'],
                            'gc' => $amplicon['gc_percent'],
                        ]) }}
                    </p>
                @endif
            </div>
        </div>

        @if ($primers === [])
            <p class="px-5 py-6 text-center text-sm text-ink-500">{{ __('cloning.primers.none') }}</p>
        @else
            <div class="grid divide-y divide-line md:grid-cols-2 md:divide-x md:divide-y-0">
                @foreach (['forward', 'reverse'] as $direction)
                    @php $primer = $plan->primer($direction); @endphp
                    <div class="px-5 py-4">
                        <p class="text-sm font-bold">{{ __('cloning.primers.' . $direction) }}</p>
                        <p class="ltr-data mt-2 break-all font-mono text-sm text-brand-700">{{ $primer['sequence'] }}</p>

                        <dl class="mt-3 space-y-1.5 text-xs">
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.primers.tm') }}</dt>
                                <dd class="ltr-data font-semibold">{{ $primer['tm'] }} °C</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.primers.gc') }}</dt>
                                <dd class="ltr-data">{{ $primer['gc_percent'] }}%</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.primers.length') }}</dt>
                                <dd class="ltr-data">{{ $primer['length'] }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.primers.position') }}</dt>
                                <dd class="ltr-data">{{ $primer['start'] }}–{{ $primer['end'] }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.primers.gc_clamp') }}</dt>
                                <dd>{{ $primer['gc_clamp'] ? __('cloning.primers.yes') : __('cloning.primers.no') }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.primers.self_dimer') }}</dt>
                                <dd class="ltr-data">{{ __('cloning.primers.bases', ['count' => $primer['self_dimer_bp']]) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.primers.hairpin') }}</dt>
                                <dd class="ltr-data">{{ __('cloning.primers.bases', ['count' => $primer['hairpin_stem_bp']]) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">{{ __('cloning.primers.matches') }}</dt>
                                <dd class="ltr-data">{{ $primer['matches_in_template'] }}</dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-line bg-paper px-5 py-4">
                <p class="text-xs font-bold">{{ __('cloning.primers.pair_title') }}</p>
                <dl class="mt-2 grid gap-x-6 gap-y-1.5 text-xs sm:grid-cols-3">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-400">{{ __('cloning.primers.tm_delta') }}</dt>
                        <dd class="ltr-data font-semibold">{{ $primers['pair']['tm_delta'] }} °C</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-400">{{ __('cloning.primers.cross_dimer') }}</dt>
                        <dd class="ltr-data">{{ __('cloning.primers.bases', ['count' => $primers['pair']['cross_dimer_bp']]) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-400">{{ __('cloning.primers.annealing') }}</dt>
                        <dd class="ltr-data font-semibold">{{ $primers['pair']['annealing_suggestion'] }} °C</dd>
                    </div>
                </dl>
            </div>
        @endif
    </section>

    {{-- Every enzyme that cut, with the coordinates and the gel. --}}
    <section class="panel mt-4 overflow-hidden">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">{{ __('cloning.digest.title') }}</h2>
                <p class="panel-note">{{ __('cloning.digest.subtitle') }}</p>
            </div>
        </div>

        @if ($cutters->isEmpty())
            <p class="px-5 py-6 text-center text-sm text-ink-500">{{ __('cloning.digest.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('cloning.digest.table_enzyme') }}</th>
                            <th scope="col">{{ __('cloning.digest.table_site') }}</th>
                            <th scope="col">{{ __('cloning.digest.table_cuts') }}</th>
                            <th scope="col">{{ __('cloning.digest.table_positions') }}</th>
                            <th scope="col">{{ __('cloning.digest.table_overhang') }}</th>
                            <th scope="col">{{ __('cloning.digest.table_fragments') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cutters as $enzyme)
                            <tr>
                                <th scope="row" class="ltr-data font-semibold">
                                    {{ $enzyme['enzyme'] }}
                                    @if ($enzyme['cuts_outside_site'])
                                        <span class="block text-[0.625rem] font-normal text-ink-400">
                                            {{ __('cloning.digest.cuts_outside') }}
                                        </span>
                                    @endif
                                </th>
                                <td class="ltr-data font-mono">{{ $enzyme['site'] }}</td>
                                <td class="ltr-data">{{ $enzyme['cut_count'] }}</td>
                                <td class="ltr-data">
                                    {{ collect($enzyme['sites'])->pluck('site_start')->take(6)->implode(', ') }}@if (count($enzyme['sites']) > 6)…@endif
                                </td>
                                <td class="ltr-data">
                                    {{ __('cloning.digest.overhang_' . $enzyme['overhang']['kind'], ['sequence' => $enzyme['overhang']['sequence']]) }}
                                </td>
                                <td class="ltr-data">
                                    {{ collect($enzyme['fragments'])->take(5)->implode(', ') }}@if (count($enzyme['fragments']) > 5)…@endif
                                    @if ($enzyme['unresolvable_pairs'] !== [])
                                        <span class="mt-0.5 block text-[0.625rem] text-signal-700">
                                            {{ __('cloning.digest.gel_title') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- How the numbers were produced, so they can be argued with. --}}
    @if ($plan->conditions() !== [])
        @php $conditions = $plan->conditions(); $criteria = $plan->criteria(); @endphp
        <section class="panel mt-4 p-5">
            <h2 class="text-sm font-bold">{{ __('cloning.conditions.title') }}</h2>
            <dl class="mt-3 grid gap-x-6 gap-y-1.5 text-xs sm:grid-cols-2">
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-400">{{ __('cloning.conditions.model') }}</dt>
                    <dd class="text-end">{{ __('cloning.conditions.model_value') }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-400">{{ __('cloning.conditions.primer') }}</dt>
                    <dd class="ltr-data">{{ $conditions['primer_nM'] }} nM</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-400">{{ __('cloning.conditions.na') }}</dt>
                    <dd class="ltr-data">{{ $conditions['na_mM'] }} mM</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-400">{{ __('cloning.conditions.mg') }}</dt>
                    <dd class="ltr-data">{{ $conditions['mg_mM'] }} mM</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-400">{{ __('cloning.conditions.dntp') }}</dt>
                    <dd class="ltr-data">{{ $conditions['dntp_mM'] }} mM</dd>
                </div>
                @if ($criteria !== [])
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-400">{{ __('cloning.conditions.target_tm') }}</dt>
                        <dd class="ltr-data">{{ $criteria['target_tm'] }} °C</dd>
                    </div>
                @endif
            </dl>
            <p class="mt-3 border-t border-line pt-3 text-xs text-ink-500">
                {{ __('cloning.conditions.note') }}
            </p>
        </section>
    @endif

    <x-diagnostics :items="$plan->diagnostics()" :counts="$plan->diagnosticCounts()" namespace="cloning" class="mt-4" />

    {{-- Said on the page, not only in the documentation. --}}
    <section class="panel mt-4 p-5">
        <h2 class="text-sm font-bold">{{ __('cloning.limits.title') }}</h2>
        <ul class="mt-3 space-y-2 text-xs text-ink-500">
            @foreach (['specificity', 'structure', 'methylation', 'star', 'teaching'] as $limit)
                <li class="flex gap-2">
                    <span aria-hidden="true" class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-ink-300"></span>
                    <span>{{ __('cloning.limits.' . $limit) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
