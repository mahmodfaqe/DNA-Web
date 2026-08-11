@props(['items', 'counts', 'namespace'])

@php
    // Shared by the compiler and the simulator. Both tools emit the same shape —
    // a code, a severity, parameters, and optionally the thing that triggered it
    // — and both need it rendered in the reader's language, so the rendering
    // lives in one place and the namespace says which translation file to look
    // the codes up in.
    $style = [
        'error' => ['chip' => 'chip-alert', 'bg' => 'bg-alert-50'],
        'warning' => ['chip' => 'chip-signal', 'bg' => 'bg-signal-50'],
        'info' => ['chip' => 'chip-muted', 'bg' => ''],
    ];
@endphp

<section class="panel overflow-hidden">
    <div class="panel-head">
        <div>
            <h2 class="panel-title">{{ __($namespace . '.diagnostics.title') }}</h2>
            <p class="panel-note">{{ __($namespace . '.diagnostics.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-1.5">
            @foreach (['error', 'warning', 'info'] as $severity)
                @if (($counts[$severity] ?? 0) > 0)
                    <span class="chip {{ $style[$severity]['chip'] }}">
                        {{ __($namespace . '.diagnostics.' . $severity) }}
                        <span class="ltr-data">{{ $counts[$severity] }}</span>
                    </span>
                @endif
            @endforeach
        </div>
    </div>

    @if (empty($items))
        <p class="px-5 py-6 text-center text-sm text-good-600">{{ __($namespace . '.diagnostics.none') }}</p>
    @else
        <ul class="divide-y divide-line">
            @foreach ($items as $diagnostic)
                @php
                    $key = $namespace . '.messages.' . $diagnostic['code'];
                    $params = collect($diagnostic['params'] ?? [])
                        ->map(fn ($value) => is_array($value) ? implode(', ', $value) : $value)
                        ->all();

                    // A language name is itself translatable, so the parameter is
                    // resolved through the same files rather than shown as a code.
                    if (isset($params['language'])) {
                        $params['language'] = __('compiler.languages.' . $params['language']);
                    }

                    $severity = $diagnostic['severity'];
                @endphp
                <li class="flex gap-3 px-5 py-3.5 {{ $style[$severity]['bg'] }}">
                    <span class="chip {{ $style[$severity]['chip'] }} mt-0.5 h-fit shrink-0">
                        {{ __($namespace . '.severity.' . $severity) }}
                    </span>
                    <div class="min-w-0 text-sm">
                        <p class="text-ink-900">
                            {{ Lang::has($key) ? __($key, $params) : $diagnostic['code'] }}
                        </p>
                        @if (!empty($diagnostic['span']))
                            <p class="ltr-data mt-1 text-[0.6875rem] text-ink-400">
                                {{ __($namespace . '.diagnostics.span') }}: “{{ $diagnostic['span'] }}”
                            </p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
