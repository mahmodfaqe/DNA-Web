@props(['group'])

@php
    // The samples for one tab, with their translated exercise text. The
    // question travels with the file on purpose: a sequence handed over without
    // one is a sequence nobody knows what to do with, and this dataset exists
    // to be a lesson rather than a folder.
    $samples = config('samples.' . $group, []);
@endphp

@if ($samples !== [])
    <section {{ $attributes->merge(['class' => 'mx-auto max-w-3xl']) }}>
        <div class="mb-3">
            <h2 class="text-sm font-bold">{{ __('samples.heading') }}</h2>
            <p class="mt-0.5 text-xs text-ink-400">{{ __('samples.subtitle') }}</p>
        </div>

        <ul class="panel divide-y divide-line">
            @foreach ($samples as $sample)
                @php $key = 'samples.' . $sample['key']; @endphp
                <li class="px-5 py-4">
                    <details class="group">
                        <summary class="flex cursor-pointer items-center justify-between gap-4">
                            <span class="text-sm font-semibold">{{ __($key . '.title') }}</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                 class="h-4 w-4 shrink-0 text-ink-400 transition group-open:rotate-180"
                                 aria-hidden="true">
                                <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </summary>

                        <div class="mt-3 space-y-3">
                            <div>
                                <p class="eyebrow">{{ __('samples.question') }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-ink-700">{{ __($key . '.question') }}</p>
                            </div>

                            <div>
                                <p class="eyebrow">{{ __('samples.looking_for') }}</p>
                                <p class="mt-1 text-xs text-ink-500">{{ __($key . '.looking_for') }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2 pt-1">
                                @if ($group === 'cloning')
                                    <a href="{{ route('samples.load', ['file' => $sample['file']]) }}"
                                       class="btn btn-primary btn-sm">
                                        {{ __('samples.load') }}
                                    </a>
                                @endif
                                <a href="{{ route('samples.download', ['file' => $sample['file']]) }}"
                                   class="btn btn-quiet btn-sm">
                                    {{ __('samples.download') }}
                                </a>
                            </div>
                        </div>
                    </details>
                </li>
            @endforeach
        </ul>
    </section>
@endif
