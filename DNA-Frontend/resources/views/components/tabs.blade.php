@props(['active'])

{{-- Two tabs, both always visible: the two tools share a subject and a user, and
     hiding one behind a menu would hide that they belong together. --}}
<nav aria-label="{{ __('common.app.name') }}" class="no-print mb-7 border-b border-line">
    <ul class="-mb-px flex gap-1">
        @php
            $tabs = [
                ['route' => 'analysis.index', 'key' => 'analysis', 'label' => __('compiler.nav.analysis')],
                ['route' => 'compiler.index', 'key' => 'compiler', 'label' => __('compiler.nav.compiler')],
            ];
        @endphp

        @foreach ($tabs as $tab)
            <li>
                <a href="{{ route($tab['route']) }}"
                   @if ($tab['key'] === $active) aria-current="page" @endif
                   class="inline-block border-b-2 px-4 py-2.5 text-sm font-semibold transition
                          {{ $tab['key'] === $active
                                ? 'border-brand-600 text-brand-600'
                                : 'border-transparent text-ink-400 hover:border-line-strong hover:text-ink-700' }}">
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
