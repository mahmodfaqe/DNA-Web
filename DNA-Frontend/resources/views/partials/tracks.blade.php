@php
    $genes = $analysis->genes();
    $maxLength = max(array_column($genes, 'length') ?: [1]);

    // Variants are indexed by the record they were called against, so each track
    // can draw its own marks without re-scanning the whole comparison set.
    $variantsById = [];
    foreach ($analysis->comparisons() as $comparison) {
        $variantsById[$comparison['alternative_id']] = $comparison['variants'] ?? [];
    }
    $referenceId = $genes[0]['id'] ?? null;
@endphp

<section class="panel overflow-hidden">
    <div class="panel-head">
        <div>
            <h2 class="panel-title">{{ __('analysis.track.title') }}</h2>
            <p class="panel-note">{{ __('analysis.track.subtitle') }}</p>
        </div>

        <div class="track flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.6875rem] text-ink-500">
            @foreach (['A' => 'a', 'T' => 't', 'C' => 'c', 'G' => 'g', 'N' => 'n'] as $base => $token)
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-[2px]"
                          style="background: var(--color-base-{{ $token }});" aria-hidden="true"></span>
                    <span class="ltr-data">{{ $base }}</span>
                </span>
            @endforeach
        </div>
    </div>

    <div class="space-y-4 p-5">
        @foreach ($genes as $gene)
            <x-composition-track
                :gene="$gene"
                :variants="$variantsById[$gene['id']] ?? []"
                :max-length="$maxLength"
                :is-reference="$gene['id'] === $referenceId" />
        @endforeach
    </div>

    <p class="border-t border-line px-5 py-3 text-[0.6875rem] text-ink-400">
        {{ __('analysis.track.orientation_note') }}
    </p>
</section>
