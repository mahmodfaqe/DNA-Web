@props(['genes', 'matrix', 'caption', 'note'])

@php
    // A diverging scale, because the quantity has a meaningful zero and two
    // opposite directions: genes rising together, genes rising against each
    // other, and a neutral middle that has to read as "nothing here". Warm
    // against cool, with grey at the midpoint — a rainbow or two cool hues
    // would destroy exactly that reading.
    $colourFor = function (float $value) {
        $strength = min(1.0, abs($value));
        if ($strength < 0.04) {
            return ['fill' => 'var(--color-paper)', 'ink' => 'var(--color-ink-400)'];
        }

        $token = $value > 0 ? '43, 80, 143' : '179, 53, 42';   // brand-500 / alert-600
        $opacity = 0.12 + 0.78 * $strength;

        return [
            'fill' => "rgba({$token}, {$opacity})",
            'ink' => $opacity > 0.55 ? '#fff' : 'var(--color-ink-900)',
        ];
    };
@endphp

<figure class="track">
    <figcaption class="mb-2 text-xs font-semibold text-ink-700">{{ $caption }}</figcaption>

    <div class="overflow-x-auto">
        <table class="w-full border-separate" style="border-spacing: 2px;">
            <caption class="sr-only">{{ $caption }}. {{ $note }}</caption>
            <thead>
            <tr>
                <th scope="col" class="w-10"><span class="sr-only">{{ __('simulator.crosstalk.gene') }}</span></th>
                @foreach ($genes as $gene)
                    <th scope="col" class="px-1 pb-1 text-[0.6875rem] font-bold text-ink-500">{{ $gene }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach ($genes as $row => $gene)
                <tr>
                    <th scope="row" class="pe-1 text-end text-[0.6875rem] font-bold text-ink-500">{{ $gene }}</th>
                    @foreach ($genes as $column => $other)
                        @php
                            // The diagonal is a gene against itself, which is 1.00
                            // by definition and tells nobody anything. Left blank
                            // rather than filled solid, so the strongest colour on
                            // the grid is always a real reading.
                            $self = $row === $column;
                            $value = (float) ($matrix[$row][$column] ?? 0);
                            $style = $self
                                ? ['fill' => 'transparent', 'ink' => 'var(--color-ink-300)']
                                : $colourFor($value);
                        @endphp
                        <td class="rounded-[3px] px-1 py-2 text-center text-[0.6875rem] font-semibold tabular-nums"
                            style="background: {{ $style['fill'] }}; color: {{ $style['ink'] }};">
                            {{ $self ? '·' : number_format($value, 2) }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-2 text-[0.6875rem] leading-relaxed text-ink-400">{{ $note }}</p>
</figure>
