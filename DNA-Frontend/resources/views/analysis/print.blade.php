@php $meta = \App\Support\Locales::meta(); @endphp
<!DOCTYPE html>
<html lang="{{ $meta['tag'] }}" dir="{{ $meta['dir'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('analysis.result.title') }} — {{ $analysis->filename }}</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white">
<div class="mx-auto max-w-4xl px-6 py-8">

    <div class="no-print mb-6 flex items-center justify-between gap-3 rounded-lg border border-line bg-paper px-4 py-3">
        <p class="text-xs text-ink-500">{{ __('analysis.print.note') }}</p>
        <div class="flex gap-2">
            <a href="{{ route('analysis.show', ['analysis' => $analysis->id]) }}" class="btn btn-quiet btn-sm">
                {{ __('common.actions.back') }}
            </a>
            {{-- The browser's own print dialog produces vector PDF with real,
                 selectable Kurdish and Arabic text. A rasterising JS library
                 cannot do that, which is why the old html2pdf path was dropped. --}}
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                {{ __('common.actions.print') }}
            </button>
        </div>
    </div>

    <header class="mb-6 border-b border-line pb-4">
        <p class="eyebrow">{{ __('common.app.name') }}</p>
        <h1 class="mt-1 text-xl font-bold">{{ __('analysis.result.title') }}</h1>
        <p class="mt-2 text-xs text-ink-500">
            {{ __('analysis.print.source') }}:
            <span class="ltr-data font-semibold">{{ $analysis->filename }}</span>
            · {{ __('analysis.print.generated', ['date' => $analysis->created_at->toDayDateTimeString()]) }}
        </p>
        <p class="mt-1 text-xs text-ink-400">
            {{ __('analysis.result.checksum') }}: <span class="ltr-data">{{ $analysis->checksum }}</span>
        </p>
    </header>

    <div class="space-y-5">
        @include('partials.metrics')
        @include('partials.tracks')
        @include('partials.records-table', ['interactive' => false])
        @include('partials.comparison')
    </div>
</div>
</body>
</html>
