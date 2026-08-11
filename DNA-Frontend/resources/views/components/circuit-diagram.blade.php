@props(['gates'])

@php
    // Lay the netlist out in columns by gate type: inputs on the left, logic in
    // the middle, outputs on the right. Signal flows left to right in every
    // language, for the same reason a sequence does - a circuit diagram is not
    // prose, and mirroring it would mean inputs on the right feeding backwards.
    $columns = [
        'inputs' => array_values(array_filter($gates, fn ($g) => in_array($g['type'], ['SENSOR', 'NOT'], true))),
        'logic' => array_values(array_filter($gates, fn ($g) => in_array($g['type'], ['AND', 'OR'], true))),
        'outputs' => array_values(array_filter($gates, fn ($g) => in_array($g['type'], ['OUTPUT', 'TERMINAL'], true))),
    ];

    $rows = max(1, max(count($columns['inputs']), count($columns['outputs'])));
    $rowHeight = 58;
    $boxWidth = 168;
    $boxHeight = 40;
    $gap = 86;

    $height = $rows * $rowHeight + 24;
    $width = $boxWidth * 3 + $gap * 2;

    $x = ['inputs' => 0, 'logic' => $boxWidth + $gap, 'outputs' => ($boxWidth + $gap) * 2];

    $centre = function (int $index, int $count) use ($rows, $rowHeight) {
        $offset = ($rows - $count) * $rowHeight / 2;
        return (int) ($offset + $index * $rowHeight + 12);
    };

    $colours = [
        'SENSOR' => 'var(--color-brand-600)',
        'NOT' => 'var(--color-alert-600)',
        'AND' => 'var(--color-ink-700)',
        'OR' => 'var(--color-ink-700)',
        'OUTPUT' => 'var(--color-good-600)',
        'TERMINAL' => 'var(--color-signal-600)',
    ];

    $labelFor = function (array $gate) {
        $key = match ($gate['type']) {
            'SENSOR', 'NOT' => 'compiler.sensors.' . $gate['label'],
            'OUTPUT', 'TERMINAL' => 'compiler.actuators.' . $gate['label'],
            default => null,
        };

        return $key && Lang::has($key) ? __($key) : $gate['label'];
    };

    // Position lookup so wires can be drawn from real box coordinates.
    $positions = [];
    foreach ($columns as $column => $items) {
        foreach ($items as $index => $gate) {
            $positions[$gate['id']] = [
                'x' => $x[$column],
                'y' => $centre($index, count($items)),
            ];
        }
    }
@endphp

<div class="track overflow-x-auto">
    <svg viewBox="0 0 {{ $width }} {{ $height }}"
         width="{{ $width }}" height="{{ $height }}"
         role="img" aria-label="{{ __('compiler.logic.title') }}"
         class="min-w-full">

        {{-- Wires first, so boxes sit on top of them. --}}
        @foreach ($gates as $gate)
            @foreach ($gate['inputs'] as $inputId)
                @if (isset($positions[$inputId], $positions[$gate['id']]))
                    @php
                        $from = $positions[$inputId];
                        $to = $positions[$gate['id']];
                        $x1 = $from['x'] + $boxWidth;
                        $y1 = $from['y'] + $boxHeight / 2;
                        $x2 = $to['x'];
                        $y2 = $to['y'] + $boxHeight / 2;
                        $mid = ($x1 + $x2) / 2;
                    @endphp
                    <path d="M {{ $x1 }} {{ $y1 }} C {{ $mid }} {{ $y1 }}, {{ $mid }} {{ $y2 }}, {{ $x2 }} {{ $y2 }}"
                          fill="none" stroke="var(--color-line-strong)" stroke-width="1.5"/>
                    <circle cx="{{ $x2 }}" cy="{{ $y2 }}" r="2.5" fill="var(--color-line-strong)"/>
                @endif
            @endforeach
        @endforeach

        @foreach ($columns as $column => $items)
            @foreach ($items as $index => $gate)
                @php
                    $gy = $centre($index, count($items));
                    $colour = $colours[$gate['type']] ?? 'var(--color-ink-500)';
                @endphp
                <g>
                    <rect x="{{ $x[$column] }}" y="{{ $gy }}"
                          width="{{ $boxWidth }}" height="{{ $boxHeight }}"
                          rx="8" fill="#fff" stroke="{{ $colour }}" stroke-width="1.5"/>
                    <rect x="{{ $x[$column] }}" y="{{ $gy }}"
                          width="4" height="{{ $boxHeight }}" rx="2" fill="{{ $colour }}"/>

                    <text x="{{ $x[$column] + 14 }}" y="{{ $gy + 16 }}"
                          font-size="9" font-weight="700" fill="{{ $colour }}"
                          style="letter-spacing:.08em; text-transform:uppercase;">
                        {{ __('compiler.gates.' . $gate['type']) }}
                    </text>
                    <text x="{{ $x[$column] + 14 }}" y="{{ $gy + 30 }}"
                          font-size="11" fill="var(--color-ink-900)">
                        {{ Str::limit($labelFor($gate), 22) }}
                    </text>
                </g>
            @endforeach
        @endforeach
    </svg>
</div>
