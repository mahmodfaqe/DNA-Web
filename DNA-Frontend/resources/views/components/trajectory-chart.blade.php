@props(['series', 'time', 'colour', 'label'])

@php
    // One panel per gene rather than every gene overlaid on one plot.
    //
    // The point of this chart is that a single cell does not look like the
    // average of many cells: the average is a smooth line, and the cell under it
    // is a staircase of bursts. Stacking three genes' bands on one axis turns
    // that into overlapping washes nobody can read, and forces the reader to
    // tell three colours apart to follow it. Separate panels put each gene on
    // its own baseline, and whether the genes move together is answered
    // properly by the correlation matrix further down the page.

    $minutes = $time['grid_minutes'] ?? [];
    $mean = $series['mean'] ?? [];
    $spread = $series['sd'] ?? [];
    $examples = $series['examples'] ?? [];

    $width = 720;
    $height = 176;
    $left = 46;
    $right = 704;
    $top = 12;
    $bottom = 150;

    $span = max(0.001, (float) end($minutes));
    reset($minutes);

    // The ceiling covers the band and every example trace, so no line is ever
    // clipped by a scale chosen from the mean alone.
    $ceiling = 1.0;
    foreach ($mean as $index => $value) {
        $ceiling = max($ceiling, $value + ($spread[$index] ?? 0));
    }
    foreach ($examples as $trace) {
        $ceiling = max($ceiling, max($trace ?: [0]));
    }
    $ceiling *= 1.06;

    $x = fn ($minute) => $left + ($minute / $span) * ($right - $left);
    $y = fn ($value) => $bottom - (min($value, $ceiling) / $ceiling) * ($bottom - $top);

    $path = function (array $values) use ($minutes, $x, $y) {
        $points = [];
        foreach ($values as $index => $value) {
            if (! isset($minutes[$index])) {
                continue;
            }
            $points[] = round($x($minutes[$index]), 1) . ',' . round($y($value), 1);
        }
        return implode(' ', $points);
    };

    // The band is drawn as one closed shape: up the top edge, back along the
    // bottom. Clamped at zero because a count cannot be negative, and a band
    // dipping below the axis would suggest it can.
    $bandTop = [];
    $bandBottom = [];
    foreach ($mean as $index => $value) {
        if (! isset($minutes[$index])) {
            continue;
        }
        $deviation = $spread[$index] ?? 0;
        $bandTop[] = round($x($minutes[$index]), 1) . ',' . round($y($value + $deviation), 1);
        $bandBottom[] = round($x($minutes[$index]), 1) . ',' . round($y(max(0, $value - $deviation)), 1);
    }
    $band = implode(' ', $bandTop) . ' ' . implode(' ', array_reverse($bandBottom));

    $burnIn = (float) ($time['burn_in_minutes'] ?? 0);
    $final = (float) (end($mean) ?: 0);
    reset($mean);

    $ticks = [0, $ceiling / 2, $ceiling];
@endphp

<div class="track overflow-x-auto">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}"
         role="img" class="min-w-full"
         aria-label="{{ __('simulator.charts.trajectory_alt', ['gene' => $label]) }}">

        {{-- Burn-in. Shaded rather than cropped: the reader should see the
             window the statistics ignored, and why it was ignored. --}}
        @if ($burnIn > 0)
            <rect x="{{ $left }}" y="{{ $top }}"
                  width="{{ round($x($burnIn) - $left, 1) }}" height="{{ $bottom - $top }}"
                  fill="var(--color-paper)"/>
            <text x="{{ round($x($burnIn) - 5, 1) }}" y="{{ $top + 11 }}"
                  font-size="8.5" text-anchor="end" fill="var(--color-ink-400)">
                {{ __('simulator.charts.burn_in') }}
            </text>
        @endif

        @foreach ($ticks as $tick)
            <line x1="{{ $left }}" y1="{{ round($y($tick), 1) }}"
                  x2="{{ $right }}" y2="{{ round($y($tick), 1) }}"
                  stroke="var(--color-line)" stroke-width="1"/>
            <text x="{{ $left - 7 }}" y="{{ round($y($tick) + 3, 1) }}"
                  font-size="9" text-anchor="end" fill="var(--color-ink-400)">
                {{ number_format($tick, $ceiling < 20 ? 1 : 0) }}
            </text>
        @endforeach

        {{-- Spread first, then individual cells, then the mean on top. --}}
        <polygon points="{{ $band }}" fill="{{ $colour }}" fill-opacity="0.12"/>

        @foreach ($examples as $trace)
            <polyline points="{{ $path($trace) }}" fill="none"
                      stroke="{{ $colour }}" stroke-opacity="0.32" stroke-width="1"
                      stroke-linejoin="round"/>
        @endforeach

        <polyline points="{{ $path($mean) }}" fill="none"
                  stroke="{{ $colour }}" stroke-width="2"
                  stroke-linejoin="round" stroke-linecap="round"/>

        {{-- One direct label, on the endpoint. A number on every point would be
             chaos and would go unread. --}}
        <circle cx="{{ round($x($span), 1) }}" cy="{{ round($y($final), 1) }}" r="4"
                fill="{{ $colour }}" stroke="#fff" stroke-width="2"/>

        <line x1="{{ $left }}" y1="{{ $bottom }}" x2="{{ $right }}" y2="{{ $bottom }}"
              stroke="var(--color-line-strong)" stroke-width="1"/>

        @foreach ([0, 0.25, 0.5, 0.75, 1] as $fraction)
            <text x="{{ round($left + $fraction * ($right - $left), 1) }}" y="{{ $bottom + 15 }}"
                  font-size="9" text-anchor="{{ $fraction === 0 ? 'start' : ($fraction === 1 ? 'end' : 'middle') }}"
                  fill="var(--color-ink-400)">
                {{ round($span * $fraction) }}
            </text>
        @endforeach

        <text x="{{ $right }}" y="{{ $height - 2 }}" font-size="8.5"
              text-anchor="end" fill="var(--color-ink-400)">
            {{ __('simulator.charts.minutes') }}
        </text>
    </svg>
</div>
