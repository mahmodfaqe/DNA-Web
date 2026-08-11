@props(['active'])

{{-- Four tabs, all always visible: the tools share a subject and a user, and
     hiding one behind a menu would hide that they belong together. They are
     ordered as the work is: read a sequence, design a circuit, find out what
     that circuit does in a cell that is not a test tube, and then commit the
     result to something the cell will still be carrying tomorrow.

     The row scrolls rather than wraps on a narrow screen, so the tabs stay one
     line and never reflow into something that reads like two navigations. --}}
<nav aria-label="{{ __('common.app.name') }}" class="no-print mb-7 border-b border-line">
    <ul class="-mb-px flex gap-1 overflow-x-auto">
        @php
            $tabs = [
                ['route' => 'analysis.index', 'key' => 'analysis', 'label' => __('compiler.nav.analysis')],
                ['route' => 'compiler.index', 'key' => 'compiler', 'label' => __('compiler.nav.compiler')],
                ['route' => 'simulator.index', 'key' => 'simulator', 'label' => __('compiler.nav.simulator')],
                ['route' => 'memory.index', 'key' => 'memory', 'label' => __('compiler.nav.memory')],
            ];
        @endphp

        @foreach ($tabs as $tab)
            <li>
                <a href="{{ route($tab['route']) }}"
                   @if ($tab['key'] === $active) aria-current="page" @endif
                   class="inline-block whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition
                          {{ $tab['key'] === $active
                                ? 'border-brand-600 text-brand-600'
                                : 'border-transparent text-ink-400 hover:border-line-strong hover:text-ink-700' }}">
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
