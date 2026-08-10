@php
    $genes = $analysis->genes();
    $limits = $analysis->payload['limits'] ?? [];
    $interactive = $interactive ?? true;
@endphp

<section class="panel overflow-hidden">
    <div class="panel-head">
        <div>
            <h2 class="panel-title">{{ __('analysis.table.title') }}</h2>
            <p class="panel-note">{{ __('analysis.table.subtitle') }}</p>
        </div>
        <span class="chip">{{ count($genes) }} {{ __('analysis.result.records') }}</span>
    </div>

    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr>
                <th scope="col">{{ __('analysis.table.id') }}</th>
                <th scope="col">{{ __('analysis.table.length') }}</th>
                <th scope="col">{{ __('analysis.table.gc') }}</th>
                <th scope="col">{{ __('analysis.table.tm') }}</th>
                <th scope="col">{{ __('analysis.table.protein') }}</th>
                <th scope="col" class="ltr-data">{{ __('analysis.table.composition') }}</th>
                <th scope="col">{{ __('analysis.table.quality') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($genes as $gene)
                @php
                    $composition = $gene['base_composition'] ?? [];
                    $tm = $gene['melting_temp'] ?? [];
                    $orf = $gene['orfs']['longest'] ?? null;
                    $gc = (float) ($gene['gc_content'] ?? 0);
                @endphp
                <tr>
                    <td>
                        <span class="ltr-data block font-bold text-ink-900">{{ $gene['id'] }}</span>
                        @if (!empty($gene['description']) && $gene['description'] !== $gene['id'])
                            <span class="ltr-data mt-0.5 block max-w-[22ch] truncate text-[0.6875rem] text-ink-400"
                                  title="{{ $gene['description'] }}">{{ $gene['description'] }}</span>
                        @endif
                    </td>

                    <td class="whitespace-nowrap font-semibold">
                        <span class="ltr-data">{{ number_format($gene['length'] ?? 0) }}</span>
                        <span class="text-ink-400">{{ __('analysis.units.bp') }}</span>
                    </td>

                    <td>
                        <div class="flex items-center gap-2">
                            <span class="track h-1.5 w-14 shrink-0 overflow-hidden rounded-full bg-line">
                                <span class="block h-full rounded-full bg-brand-500"
                                      style="width: {{ min(100, max(0, $gc)) }}%"></span>
                            </span>
                            <span class="ltr-data font-semibold">{{ number_format($gc, 1) }}%</span>
                        </div>
                    </td>

                    <td class="whitespace-nowrap">
                        @if (($tm['value'] ?? null) !== null)
                            <span class="ltr-data font-semibold">{{ $tm['value'] }} {{ __('analysis.units.celsius') }}</span>
                            {{-- The method is shown next to the number rather than buried in a
                                 footnote: a Tm computed for a whole gene is an estimate, and the
                                 reader has to be able to see that at a glance. --}}
                            <span class="mt-0.5 block text-[0.625rem] {{ ($tm['reliable'] ?? false) ? 'text-ink-400' : 'text-signal-600' }}">
                                {{ __('analysis.tm_methods.' . ($tm['method'] ?? 'none')) }}
                                @unless ($tm['reliable'] ?? false)
                                    · {{ __('analysis.tm.estimate') }}
                                @endunless
                            </span>
                        @else
                            <span class="text-ink-300">—</span>
                        @endif
                    </td>

                    <td class="whitespace-nowrap">
                        @if ($orf)
                            @if ($interactive)
                                <button type="button"
                                        class="btn btn-quiet btn-sm"
                                        data-protein-trigger
                                        data-record-id="{{ $gene['id'] }}"
                                        data-protein="{{ $gene['protein_sequence'] ?? '' }}">
                                    <span class="ltr-data">{{ $orf['length_aa'] }} {{ __('analysis.units.aa') }}</span>
                                </button>
                            @else
                                <span class="ltr-data font-semibold">{{ $orf['length_aa'] }} {{ __('analysis.units.aa') }}</span>
                            @endif
                            <span class="ltr-data mt-0.5 block text-[0.625rem] text-ink-400">
                                {{ $orf['strand'] }}{{ $orf['frame'] }} · {{ number_format($orf['start']) }}–{{ number_format($orf['end']) }}
                            </span>
                        @else
                            <span class="text-ink-300">—</span>
                        @endif
                    </td>

                    <td class="ltr-data whitespace-nowrap text-ink-700">
                        {{ $composition['A'] ?? 0 }}/{{ $composition['T'] ?? 0 }}/{{ $composition['C'] ?? 0 }}/{{ $composition['G'] ?? 0 }}/{{ $composition['N'] ?? 0 }}
                    </td>

                    <td>
                        @if ($gene['quality']['has_ambiguity'] ?? false)
                            <span class="chip chip-signal">
                                {{ __('analysis.quality.unknown_fraction', ['percent' => $gene['quality']['unknown_fraction'] ?? 0]) }}
                            </span>
                            @if (!empty($gene['ambiguity_codes']))
                                <span class="ltr-data mt-1 block text-[0.625rem] text-ink-400">
                                    {{ implode(' ', $gene['ambiguity_codes']) }}
                                </span>
                            @endif
                        @else
                            <span class="chip chip-good">{{ __('analysis.quality.clean') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if (!empty($limits['tm_nn_max_bp']))
        <p class="border-t border-line px-5 py-3 text-[0.6875rem] text-ink-400">
            {{ __('analysis.tm.estimate_note', ['length' => $limits['tm_nn_max_bp']]) }}
        </p>
    @endif
</section>
