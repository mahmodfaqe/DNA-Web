@php $current = app()->getLocale(); @endphp

{{-- A three-way segmented control rather than a dropdown: with only three
     languages every option is worth showing, and one tap is better than two.
     Each option is rendered in its own script so a reader recognises their
     language without having to know the others. --}}
<nav aria-label="{{ __('common.nav.language') }}"
     class="flex items-center gap-0.5 rounded-lg border border-line bg-paper p-0.5">
    @foreach (\App\Support\Locales::SUPPORTED as $code => $meta)
        <a href="{{ \App\Support\Locales::urlFor($code) }}"
           hreflang="{{ $meta['tag'] }}"
           lang="{{ $meta['tag'] }}"
           @if ($code === $current) aria-current="true" @endif
           class="rounded-md px-2.5 py-1 text-xs font-semibold transition
                  {{ $code === $current
                        ? 'bg-white text-brand-600 shadow-sm'
                        : 'text-ink-500 hover:text-ink-900' }}">
            {{ $meta['native'] }}
        </a>
    @endforeach
</nav>
