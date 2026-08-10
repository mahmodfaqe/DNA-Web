@php
    $comparisons = $analysis->comparisons();
    $limits = $analysis->payload['limits'] ?? [];
@endphp

@if (empty($comparisons))
    <section class="panel p-5">
        <h2 class="panel-title">{{ __('analysis.compare.title') }}</h2>
        <p class="mt-1 text-sm text-ink-500">{{ __('analysis.compare.none') }}</p>
    </section>
@else
    @foreach ($comparisons as $comparison)
        @php
            $identity = (float) ($comparison['identity_percent'] ?? 0);
            $counts = array_filter($comparison['counts'] ?? []);
            $effects = array_filter($comparison['effects'] ?? []);
            $variants = $comparison['variants'] ?? [];
        @endphp

        <section class="panel overflow-hidden">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">
                        <span class="ltr-data">{{ $comparison['reference_id'] }}</span>
                        <span class="mx-1 font-normal text-ink-400">{{ __('analysis.compare.against') }}</span>
                        <span class="ltr-data">{{ $comparison['alternative_id'] }}</span>
                    </h2>
                    <p class="panel-note">
                        {{ __('analysis.compare.method') }}:
                        {{ __('analysis.methods.' . ($comparison['method'] ?? 'global_alignment')) }}
                        · {{ __('analysis.compare.aligned_length') }}
                        <span class="ltr-data">{{ number_format($comparison['aligned_length'] ?? 0) }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="chip {{ $identity >= 99 ? 'chip-good' : ($identity >= 90 ? 'chip' : 'chip-signal') }}">
                        {{ __('analysis.compare.identity') }}
                        <span class="ltr-data">{{ number_format($identity, 2) }}%</span>
                    </span>
                    <span class="chip {{ ($comparison['total_variants'] ?? 0) > 0 ? 'chip-signal' : 'chip-muted' }}">
                        {{ __('analysis.compare.total') }}
                        <span class="ltr-data">{{ number_format($comparison['total_variants'] ?? 0) }}</span>
                    </span>
                </div>
            </div>

            @if (($comparison['method'] ?? '') === 'positional_diff')
                <p class="border-b border-line bg-signal-50 px-5 py-2.5 text-xs text-signal-600">
                    {{ __('analysis.methods.positional_note', ['length' => $limits['align_max_bp'] ?? 3000]) }}
                </p>
            @endif

            @if (($comparison['total_variants'] ?? 0) === 0)
                <p class="px-5 py-6 text-center text-sm text-good-600">{{ __('analysis.compare.identical') }}</p>
            @else
                <div class="grid gap-5 border-b border-line p-5 sm:grid-cols-2">
                    <div>
                        <p class="eyebrow">{{ __('analysis.compare.counts_title') }}</p>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($counts as $type => $count)
                                <li class="flex items-baseline justify-between gap-3">
                                    <span class="text-ink-700">{{ __('analysis.variant_types.' . $type) }}</span>
                                    <span class="ltr-data font-semibold">{{ number_format($count) }}</span>
                                </li>
                            @endforeach
                            @if (($comparison['frameshift_events'] ?? 0) > 0)
                                <li class="flex items-baseline justify-between gap-3 border-t border-line pt-1">
                                    <span class="font-semibold text-alert-600">{{ __('analysis.compare.frameshift') }}</span>
                                    <span class="ltr-data font-semibold text-alert-600">{{ $comparison['frameshift_events'] }}</span>
                                </li>
                            @endif
                        </ul>
                    </div>

                    <div>
                        <p class="eyebrow">{{ __('analysis.compare.effects_title') }}</p>
                        @if (empty($effects))
                            <p class="mt-2 text-sm text-ink-400">—</p>
                        @else
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($effects as $effect => $count)
                                    <li class="flex items-baseline justify-between gap-3">
                                        <span class="text-ink-700">{{ __('analysis.effects.' . $effect) }}</span>
                                        <span class="ltr-data font-semibold">{{ number_format($count) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="max-h-[26rem] overflow-auto">
                    <table class="data-table">
                        <thead class="sticky top-0">
                        <tr>
                            <th scope="col">{{ __('analysis.variant.type') }}</th>
                            <th scope="col">{{ __('analysis.variant.position') }}</th>
                            <th scope="col">{{ __('analysis.variant.codon') }}</th>
                            <th scope="col">{{ __('analysis.variant.change') }}</th>
                            <th scope="col">{{ __('analysis.variant.effect') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($variants as $variant)
                            @php $type = $variant['type'] ?? 'substitution'; @endphp
                            <tr>
                                <td>
                                    <span class="chip {{ in_array($type, ['insertion', 'deletion'], true) ? 'chip-alert' : 'chip-muted' }}">
                                        {{ __('analysis.variant_types.' . $type) }}
                                    </span>
                                    @if ($variant['frameshift'] ?? false)
                                        <span class="ltr-data mt-1 block text-[0.625rem] font-semibold text-alert-600">
                                            frameshift
                                        </span>
                                    @endif
                                </td>

                                <td class="ltr-data whitespace-nowrap">{{ number_format($variant['position'] ?? 0) }}</td>
                                <td class="ltr-data whitespace-nowrap text-ink-500">#{{ $variant['codon'] ?? '—' }}</td>

                                <td class="ltr-data whitespace-nowrap">
                                    @if ($type === 'substitution')
                                        <span style="color: var(--color-base-{{ strtolower($variant['reference_base'] ?? 'n') }})">{{ $variant['reference_base'] ?? '' }}</span>
                                        <span class="text-ink-300">→</span>
                                        <span style="color: var(--color-base-{{ strtolower($variant['alternative_base'] ?? 'n') }})">{{ $variant['alternative_base'] ?? '' }}</span>
                                        @if (!empty($variant['ref_codon']))
                                            <span class="mt-0.5 block text-[0.625rem] text-ink-400">
                                                {{ $variant['ref_codon'] }} → {{ $variant['alt_codon'] }}
                                            </span>
                                        @endif
                                    @elseif ($type === 'insertion')
                                        +{{ $variant['length'] ?? 0 }} {{ __('analysis.units.bp') }}
                                        <span class="mt-0.5 block max-w-[18ch] truncate text-[0.625rem] text-ink-400">{{ $variant['inserted'] ?? '' }}</span>
                                    @elseif ($type === 'deletion')
                                        −{{ $variant['length'] ?? 0 }} {{ __('analysis.units.bp') }}
                                        <span class="mt-0.5 block max-w-[18ch] truncate text-[0.625rem] text-ink-400">{{ $variant['deleted'] ?? '' }}</span>
                                    @else
                                        {{ $variant['length'] ?? '' }} {{ __('analysis.units.bp') }}
                                    @endif
                                </td>

                                <td>
                                    @if ($type === 'substitution')
                                        @php $effect = $variant['effect'] ?? 'unknown'; @endphp
                                        <span class="chip {{ match ($effect) {
                                            'synonymous' => 'chip-good',
                                            'nonsense', 'stop_lost' => 'chip-alert',
                                            'missense' => 'chip-signal',
                                            default => 'chip-muted',
                                        } }}">
                                            {{ __('analysis.effects.' . $effect) }}
                                        </span>
                                        @if (!empty($variant['ref_aa']))
                                            <span class="ltr-data mt-1 block text-[0.625rem] text-ink-400">
                                                {{ $variant['ref_aa'] }} → {{ $variant['alt_aa'] }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-ink-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($comparison['variants_truncated'] ?? false)
                    <p class="border-t border-line px-5 py-3 text-[0.6875rem] text-ink-400">
                        {{ __('analysis.compare.truncated', ['total' => number_format($comparison['total_variants'])]) }}
                    </p>
                @endif
            @endif
        </section>
    @endforeach
@endif
