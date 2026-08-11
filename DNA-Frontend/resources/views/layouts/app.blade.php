@php $meta = \App\Support\Locales::meta(); @endphp
<!DOCTYPE html>
<html lang="{{ $meta['tag'] }}" dir="{{ $meta['dir'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', __('common.app.name')) — {{ __('common.app.name') }}</title>
    <meta name="description" content="{{ __('common.app.description') }}">

    {{-- Tells search engines these are the same page in three languages rather
         than three pages of duplicate content. --}}
    @foreach (\App\Support\Locales::codes() as $code)
        <link rel="alternate" hreflang="{{ \App\Support\Locales::tag($code) }}" href="{{ \App\Support\Locales::urlFor($code) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Locales::urlFor('en') }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    data-label-submitting="{{ __('common.hero.submitting') }}"
    data-label-copied="{{ __('common.actions.copied') }}"
    data-label-no-protein="{{ __('analysis.orf.protein_empty') }}"
    data-label-compiling="{{ __('compiler.hero.submitting') }}"
    data-label-simulating="{{ __('simulator.form.submitting') }}"
    data-label-modelling="{{ __('memory.form.submitting') }}"
>
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">
    {{ __('common.nav.skip_to_content') }}
</a>

<header class="no-print sticky top-0 z-30 border-b border-line bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ route('analysis.index') }}" class="flex items-center gap-3">
            <span aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 text-white">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5">
                    <path d="M7 3c0 5 10 5 10 9s-10 4-10 9" stroke-linecap="round"/>
                    <path d="M17 3c0 5-10 5-10 9s10 4 10 9" stroke-linecap="round"/>
                    <path d="M9 7h6M8.5 12h7M9 17h6" stroke-linecap="round"/>
                </svg>
            </span>
            <span>
                <span class="block text-sm font-bold leading-tight">{{ __('common.app.name') }}</span>
                <span class="block text-xs text-ink-400">{{ __('common.app.tagline') }}</span>
            </span>
        </a>

        <div class="flex items-center gap-2">
            @yield('header-actions')
            <x-language-switcher />
        </div>
    </div>
</header>

<main id="main" class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    @yield('content')
</main>

<footer class="no-print mx-auto max-w-6xl px-4 pb-10 sm:px-6">
    <p class="border-t border-line pt-5 text-xs text-ink-400">
        {{ __('common.footer.retention', ['days' => 30]) }}
    </p>
</footer>
</body>
</html>
