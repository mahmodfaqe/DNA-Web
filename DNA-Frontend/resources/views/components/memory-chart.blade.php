@props(['series', 'span', 'caption', 'unit'])

@php
    // Both architectures are plotted on one axis, and that is only legitimate
    // because both are shown as the same quantity: the share of the memory that
    // is set, from nothing to everything. A recombinase reports the fraction of
    // the population whose register has inverted; a toggle reports how far the
    // set repressor has won over the reset one. Plotting copies of integrase
    // against a fraction would need two y-scales, which is the one thing a
    // chart must never do — the alignment between the scales would be arbitrary
    // and the chart would invent a relationship that is not in the data.
    //
    // The write and hold phases are separate charts rather than one long axis:
    // an hour of signal followed by a day of holding puts the entire write
    // phase into three pixels, and the write phase is half the question.

    $width = 460;
    $height = 190;
    $left = 40;
    $right = 448;
    $top = 14;
    $bottom = 150;

    $x = fn ($minute) => $left + ($span > 0 ? $minute / $span : 0) * ($right - $left);
    $y = fn ($value) => $bottom - max(0, min(1, $value)) * ($bottom - $top);

    $path = function (array $points) use ($x, $y) {
        return implode(' ', array_map(
            fn ($point) => round($x($point[0]), 1) . ',' . round($y($point[1]), 1),
            $points
        ));
    };
@endphp

<figure class="track">
    <figcaption class="mb-1.5 text-xs font-semibold text-ink-700">{{ $caption }}</figcaption>

    <div class="overflow-x-auto">
        <svg viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}"
             role="img" class="min-w-full" aria-label="{{ $caption }}">

            @foreach ([0, 0.5, 1] as $tick)
                <line x1="{{ $left }}" y1="{{ round($y($tick), 1) }}"
                      x2="{{ $right }}" y2="{{ round($y($tick), 1) }}"
                      stroke="var(--color-line)" stroke-width="1"/>
                <text x="{{ $left - 6 }}" y="{{ round($y($tick) + 3, 1) }}"
                      font-size="9" text-anchor="end" fill="var(--color-ink-400)">
                    {{ round($tick * 100) }}%
                </text>
            @endforeach

            {{-- Half is where the memory stops being ambiguous, so it is drawn
                 as a reference rather than left for the reader to estimate. --}}
            <line x1="{{ $left }}" y1="{{ round($y(0.5), 1) }}" x2="{{ $right }}" y2="{{ round($y(0.5), 1) }}"
                  stroke="var(--color-line-strong)" stroke-width="1" stroke-dasharray="3 3"/>

            @foreach ($series as $line)
                @if (count($line['points']) > 1)
                    <polyline points="{{ $path($line['points']) }}" fill="none"
                              stroke="{{ $line['colour'] }}" stroke-width="2"
                              stroke-linejoin="round" stroke-linecap="round"/>
                    @php $last = end($line['points']); @endphp
                    <circle cx="{{ round($x($last[0]), 1) }}" cy="{{ round($y($last[1]), 1) }}" r="4"
                            fill="{{ $line['colour'] }}" stroke="#fff" stroke-width="2"/>
                @endif
            @endforeach

            <line x1="{{ $left }}" y1="{{ $bottom }}" x2="{{ $right }}" y2="{{ $bottom }}"
                  stroke="var(--color-line-strong)" stroke-width="1"/>

            @foreach ([0, 0.5, 1] as $fraction)
                <text x="{{ round($left + $fraction * ($right - $left), 1) }}" y="{{ $bottom + 15 }}"
                      font-size="9" fill="var(--color-ink-400)"
                      text-anchor="{{ $fraction === 0 ? 'start' : ($fraction === 1 ? 'end' : 'middle') }}">
                    {{ round($span * $fraction, $span < 10 ? 1 : 0) }}
                </text>
            @endforeach

            <text x="{{ $right }}" y="{{ $height - 3 }}" font-size="8.5"
                  text-anchor="end" fill="var(--color-ink-400)">{{ $unit }}</text>
        </svg>
    </div>
</figure>
