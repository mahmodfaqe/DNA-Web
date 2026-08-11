@props(['shape', 'statistics', 'colour', 'label'])

@php
    // The histogram is the point of a stochastic simulation, not a nicety.
    //
    // A deterministic model would report one number for this gene. The width of
    // this shape is everything that number leaves out: cells at twice the mean
    // and cells at half of it, in the same culture, with the same DNA.

    $counts = $shape['counts'] ?? [];
    $edges = $shape['edges'] ?? [];
    $total = array_sum($counts) ?: 1;
    $tallest = max($counts ?: [1]);

    $width = 340;
    $height = 150;
    $left = 6;
    $right = 334;
    $top = 10;
    $bottom = 122;

    $slots = max(1, count($counts));
    $slot = ($right - $left) / $slots;
    $barWidth = max(1.5, $slot - 2);  // the 2px surface gap between neighbours

    $mean = (float) ($statistics['mean_protein'] ?? 0);
    $low = (float) ($edges[0] ?? 0);
    $high = (float) (end($edges) ?: 1);
    $range = max(0.001, $high - $low);
    $meanX = $left + (($mean - $low) / $range) * ($right - $left);
@endphp

<div class="track overflow-x-auto">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}"
         role="img" class="min-w-full"
         aria-label="{{ __('simulator.charts.distribution_alt', ['gene' => $label]) }}">

        @foreach ($counts as $index => $count)
            @php
                $barHeight = $tallest > 0 ? ($count / $tallest) * ($bottom - $top) : 0;
                $barX = $left + $index * $slot;
                $share = round($count / $total * 100, 1);
                $from = round((float) ($edges[$index] ?? 0));
                $to = round((float) ($edges[$index + 1] ?? 0));
            @endphp
            @if ($count > 0)
                {{-- Rounded at the data end, square on the baseline. --}}
                <rect x="{{ round($barX, 1) }}" y="{{ round($bottom - $barHeight, 1) }}"
                      width="{{ round($barWidth, 1) }}" height="{{ round($barHeight, 1) }}"
                      rx="{{ min(2, $barWidth / 2) }}" fill="{{ $colour }}" fill-opacity="0.85">
                    <title>{{ $from }}–{{ $to }}: {{ $share }}%</title>
                </rect>
            @endif
        @endforeach

        <line x1="{{ $left }}" y1="{{ $bottom }}" x2="{{ $right }}" y2="{{ $bottom }}"
              stroke="var(--color-line-strong)" stroke-width="1"/>

        {{-- The mean, marked so the asymmetry is visible. Expression
             distributions lean right: the mean sits above the commonest value,
             and a reader who assumes a bell curve will read this wrong. --}}
        <line x1="{{ round($meanX, 1) }}" y1="{{ $top - 4 }}" x2="{{ round($meanX, 1) }}" y2="{{ $bottom }}"
              stroke="var(--color-ink-700)" stroke-width="1" stroke-dasharray="3 2"/>
        <text x="{{ round($meanX, 1) }}" y="{{ $top - 6 }}" font-size="8.5"
              text-anchor="middle" fill="var(--color-ink-700)">
            {{ __('simulator.charts.mean') }} {{ number_format($mean, $mean < 20 ? 1 : 0) }}
        </text>

        <text x="{{ $left }}" y="{{ $bottom + 13 }}" font-size="9" fill="var(--color-ink-400)">
            {{ number_format($low) }}
        </text>
        <text x="{{ $right }}" y="{{ $bottom + 13 }}" font-size="9"
              text-anchor="end" fill="var(--color-ink-400)">
            {{ number_format($high) }}
        </text>
        <text x="{{ ($left + $right) / 2 }}" y="{{ $height - 3 }}" font-size="8.5"
              text-anchor="middle" fill="var(--color-ink-400)">
            {{ __('simulator.charts.copies_per_cell') }}
        </text>
    </svg>
</div>
