@props(['unit'])

@php
    $length = max(1, (int) $unit['length']);

    $roleColours = [
        'promoter' => 'var(--color-brand-500)',
        'rbs' => 'var(--color-signal-500)',
        'cds' => 'var(--color-good-600)',
        'terminator' => 'var(--color-alert-600)',
        'tag' => 'var(--color-ink-500)',
        'scar' => 'var(--color-line-strong)',
        'spacer' => 'var(--color-line-strong)',
        // Added for the memory designer. The att sites take the darkest brand
        // step because they are the boundary of the register — the two points
        // the whole construct is built around — and the payload between them
        // is neutral ink, since what it contains is the user's business.
        'att' => 'var(--color-brand-700)',
        'payload' => 'var(--color-ink-700)',
    ];
@endphp

{{-- Drawn to scale so the eye sees what actually dominates the construct: in a
     typical design the coding sequence is most of it, and the regulatory parts
     the compiler chose are the thin slivers at either end. --}}
<div class="track">
    <div class="flex h-7 w-full overflow-hidden rounded-md border border-line">
        @foreach ($unit['annotations'] as $annotation)
            @php
                $span = $annotation['end'] - $annotation['start'] + 1;
                $percent = $span / $length * 100;
                $colour = $roleColours[$annotation['role']] ?? 'var(--color-ink-300)';
                $isPlaceholder = $annotation['provenance'] === 'placeholder';
            @endphp
            <div class="relative flex items-center justify-center overflow-hidden text-[9px] font-semibold text-white"
                 style="width: {{ round($percent, 3) }}%;
                        background: {{ $colour }};
                        {{ $isPlaceholder ? 'background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(255,255,255,.35) 4px, rgba(255,255,255,.35) 8px);' : '' }}"
                 title="{{ $annotation['part_id'] }} — {{ $annotation['name'] }} ({{ $span }} bp)">
                @if ($percent > 12)
                    <span class="truncate px-1">{{ $annotation['part_id'] }}</span>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-1 flex justify-between text-[0.625rem] text-ink-400">
        <span class="ltr-data">5′ 1</span>
        <span class="ltr-data">{{ number_format($length) }} 3′</span>
    </div>
</div>
