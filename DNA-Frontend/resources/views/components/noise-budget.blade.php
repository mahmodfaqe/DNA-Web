@props(['budget', 'label'])

@php
    // Noise expressed as CV squared is additive over independent sources, which
    // is the only reason this bar can be drawn at all: each segment is a real
    // share of the total, not a proportion invented for the picture.
    //
    // The four internal sources are an ordered sequence — the irreducible floor
    // first, then what the gene's own machinery adds on top — so they get one
    // hue getting darker rather than four unrelated colours. Coupling from other
    // genes is not part of that sequence and is the thing being measured, so it
    // takes the accent colour this design reserves for "look here".
    $sources = [
        ['key' => 'floor', 'colour' => 'var(--color-brand-100)', 'ink' => 'var(--color-ink-700)'],
        ['key' => 'bursting', 'colour' => 'var(--color-brand-200)', 'ink' => 'var(--color-ink-700)'],
        ['key' => 'extrinsic', 'colour' => 'var(--color-brand-400)', 'ink' => '#fff'],
        ['key' => 'promoter', 'colour' => 'var(--color-brand-600)', 'ink' => '#fff'],
    ];

    $coupling = (float) ($budget['coupling'] ?? 0);
    $positive = [];
    foreach ($sources as $source) {
        $positive[$source['key']] = max(0.0, (float) ($budget[$source['key']] ?? 0));
    }

    // A negative coupling term means the couplings made this gene *quieter*.
    // It cannot be drawn as a slice of the bar, so the bar shows what the gene
    // would have been without it and the reduction is stated in words below.
    $drawnTotal = array_sum($positive) + max(0.0, $coupling);
    $scale = $drawnTotal > 0 ? $drawnTotal : 1;
@endphp

<div>
    <div class="mb-1.5 flex items-baseline justify-between gap-3">
        <span class="text-xs font-bold text-ink-900">{{ $label }}</span>
        <span class="ltr-data text-[0.6875rem] text-ink-400">
            CV² {{ number_format((float) ($budget['total'] ?? 0), 4) }}
        </span>
    </div>

    <div class="track flex h-6 w-full gap-0.5 overflow-hidden rounded-md">
        @foreach ($sources as $source)
            @php $value = $positive[$source['key']]; @endphp
            @if ($value > 0)
                @php $percent = $value / $scale * 100; @endphp
                <div class="flex items-center justify-center overflow-hidden text-[9px] font-semibold"
                     style="width: {{ round($percent, 3) }}%; background: {{ $source['colour'] }}; color: {{ $source['ink'] }};"
                     title="{{ __('simulator.budget.' . $source['key']) }} — {{ round($percent) }}%">
                    @if ($percent > 14)
                        <span class="truncate px-1">{{ round($percent) }}%</span>
                    @endif
                </div>
            @endif
        @endforeach

        @if ($coupling > 0)
            @php $percent = $coupling / $scale * 100; @endphp
            <div class="flex items-center justify-center overflow-hidden text-[9px] font-semibold text-white"
                 style="width: {{ round($percent, 3) }}%; background: var(--color-signal-500);"
                 title="{{ __('simulator.budget.coupling') }} — {{ round($percent) }}%">
                @if ($percent > 14)
                    <span class="truncate px-1">{{ round($percent) }}%</span>
                @endif
            </div>
        @endif
    </div>

    @if ($coupling < -0.0001)
        <p class="mt-1.5 text-[0.6875rem] text-good-600">
            {{ __('simulator.budget.coupling_reduces', [
                'percent' => number_format(abs($coupling) / max($scale, 0.0001) * 100, 1),
            ]) }}
        </p>
    @endif
</div>
