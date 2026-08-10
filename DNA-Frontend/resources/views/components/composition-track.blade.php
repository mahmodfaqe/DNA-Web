@props(['gene', 'variants' => [], 'maxLength' => null, 'isReference' => false])

@php
    $composition = $gene['base_composition'] ?? [];
    $length = max(1, (int) ($gene['length'] ?? 1));
    $scale = max(1, (int) ($maxLength ?: $length));

    // Bar width is proportional to the longest record in the file, so two
    // sequences of different length are visibly different lengths rather than
    // both being stretched to the full width.
    $relative = round($length / $scale * 100, 3);

    $order = [
        'A' => 'var(--color-base-a)',
        'T' => 'var(--color-base-t)',
        'C' => 'var(--color-base-c)',
        'G' => 'var(--color-base-g)',
    ];

    $unknown = (int) ($composition['N'] ?? 0) + (int) ($composition['ambiguous'] ?? 0);
    $segments = [];
    foreach ($order as $base => $colour) {
        $count = (int) ($composition[$base] ?? 0);
        if ($count > 0) {
            $segments[] = ['label' => $base, 'colour' => $colour, 'percent' => $count / $length * 100];
        }
    }
    if ($unknown > 0) {
        $segments[] = ['label' => 'N', 'colour' => 'var(--color-base-n)', 'percent' => $unknown / $length * 100];
    }
@endphp

<div class="track">
    <div class="flex items-baseline justify-between gap-3 pb-1.5">
        <span class="ltr-data text-xs font-bold text-ink-900">{{ $gene['id'] ?? '' }}</span>
        <span class="ltr-data text-[0.6875rem] text-ink-400">
            {{ number_format($length) }} {{ __('analysis.units.bp') }} · GC {{ $gene['gc_content'] ?? 0 }}%
        </span>
    </div>

    {{-- Proportional composition bar. Each segment is a share of the record's
         own bases; the whole bar is a share of the longest record. --}}
    <div class="h-3.5 overflow-hidden rounded-[3px] bg-paper" style="width: {{ $relative }}%; min-width: 12px;">
        <div class="flex h-full w-full">
            @foreach ($segments as $segment)
                <div style="width: {{ round($segment['percent'], 3) }}%; background: {{ $segment['colour'] }};"
                     title="{{ $segment['label'] }} — {{ round($segment['percent'], 1) }}%"></div>
            @endforeach
        </div>
    </div>

    {{-- Variant ruler. A bar chart of GC percentages says nothing about where a
         difference is; in genetics position is the finding, so variants are drawn
         at their real coordinate along the sequence. --}}
    @if ($isReference)
        <div class="mt-1 h-4 text-[0.625rem] leading-4 text-ink-300">
            {{ __('analysis.compare.reference') }}
        </div>
    @elseif (count($variants) > 0)
        <div class="relative mt-1 h-4" style="width: {{ $relative }}%; min-width: 12px;"
             role="img"
             aria-label="{{ __('analysis.track.variants_marked', ['reference' => $variants[0]['reference_id'] ?? '']) }}">
            @foreach ($variants as $variant)
                @php
                    $position = min(100, max(0, ($variant['position'] ?? 1) / $length * 100));
                    $isIndel = in_array($variant['type'] ?? '', ['insertion', 'deletion'], true);
                @endphp
                <span class="absolute top-0 block h-2.5 w-px"
                      style="inset-inline-start: {{ round($position, 3) }}%;
                             background: {{ $isIndel ? 'var(--color-alert-600)' : 'var(--color-signal-500)' }};"
                      title="{{ __('analysis.variant_types.' . ($variant['type'] ?? 'substitution')) }} @ {{ $variant['position'] ?? '' }}"></span>
            @endforeach
        </div>
    @else
        <div class="mt-1 h-4 text-[0.625rem] leading-4 text-ink-300">
            {{ __('analysis.track.no_variants') }}
        </div>
    @endif
</div>
