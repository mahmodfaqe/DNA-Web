@php
    $summary = $analysis->summary();
    $variantCount = $analysis->variantCount();
@endphp

<div class="panel metric-strip overflow-hidden">
    <div class="metric">
        <p class="metric-value">{{ number_format($summary['total_genes'] ?? 0) }}</p>
        <p class="metric-label">{{ __('analysis.metrics.records') }}</p>
    </div>

    <div class="metric">
        <p class="metric-value">{{ number_format($summary['total_bases'] ?? 0) }}</p>
        <p class="metric-label">{{ __('analysis.metrics.total_bases') }}</p>
    </div>

    <div class="metric">
        <p class="metric-value">{{ number_format($summary['average_gc'] ?? 0, 1) }}%</p>
        <p class="metric-label">{{ __('analysis.metrics.avg_gc') }}</p>
        <p class="metric-sub ltr-data">
            {{ number_format($summary['min_gc'] ?? 0, 1) }}–{{ number_format($summary['max_gc'] ?? 0, 1) }}%
        </p>
    </div>

    <div class="metric">
        <p class="metric-value">{{ number_format($summary['average_length'] ?? 0) }}</p>
        <p class="metric-label">{{ __('analysis.metrics.avg_length') }} ({{ __('analysis.units.bp') }})</p>
        <p class="metric-sub ltr-data">
            {{ number_format($summary['min_length'] ?? 0) }}–{{ number_format($summary['max_length'] ?? 0) }}
        </p>
    </div>

    <div class="metric">
        <p class="metric-value {{ $variantCount > 0 ? 'text-signal-600' : '' }}">
            {{ number_format($variantCount) }}
        </p>
        <p class="metric-label">{{ __('analysis.metrics.variants') }}</p>
        @if (($summary['unknown_bases'] ?? 0) > 0)
            <p class="metric-sub">
                {{ __('analysis.metrics.unknown') }}: {{ number_format($summary['unknown_bases']) }}
            </p>
        @endif
    </div>
</div>
