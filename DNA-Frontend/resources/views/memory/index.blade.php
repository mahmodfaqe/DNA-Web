@extends('layouts.app')

@section('title', __('memory.hero.title'))

@section('content')
    <x-tabs active="memory" />

    <section class="mx-auto max-w-2xl text-center">
        <p class="eyebrow">{{ __('memory.hero.eyebrow') }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
            {{ __('memory.hero.title') }}
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-ink-500">
            {{ __('memory.hero.subtitle') }}
        </p>
    </section>

    @if ($errors->any())
        <div class="mx-auto mt-6 max-w-3xl">
            <div class="alert" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
                </svg>
                <div>
                    @foreach ($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('memory.store') }}" data-memory-form class="mx-auto mt-7 max-w-3xl">
        @csrf

        {{-- What to record, and where. These two choices decide most of the
             answer: the signal brings its promoter's leak with it, and the host
             brings its division time. --}}
        <div class="panel p-5">
            <p class="eyebrow mb-4">{{ __('memory.form.recording') }}</p>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="signal" class="mb-1.5 block text-sm font-semibold">{{ __('memory.form.signal') }}</label>
                    <select id="signal" name="signal"
                            class="w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        @foreach (\App\Models\MemoryDesign::SIGNALS as $signal)
                            <option value="{{ $signal }}" @selected(old('signal', 'lactose') === $signal)>
                                {{ __('memory.signals.' . $signal) }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ink-400">{{ __('memory.form.signal_hint') }}</p>
                </div>

                <div>
                    <label for="chassis" class="mb-1.5 block text-sm font-semibold">{{ __('memory.form.chassis') }}</label>
                    <select id="chassis" name="chassis"
                            class="w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        @foreach (\App\Models\MemoryDesign::CHASSIS as $host)
                            <option value="{{ $host }}" @selected(old('chassis', 'ecoli') === $host)>
                                {{ __('memory.chassis.' . $host) }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ink-400">{{ __('memory.form.chassis_hint') }}</p>
                </div>
            </div>
        </div>

        <div class="panel mt-4 p-5">
            <p class="eyebrow mb-4">{{ __('memory.form.demands') }}</p>

            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="hold_hours" class="text-sm font-semibold">{{ __('memory.form.hold') }}</label>
                    <input type="number" id="hold_hours" name="hold_hours" min="0.5" max="168" step="0.5"
                           value="{{ old('hold_hours', 24) }}"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400">{{ __('memory.form.hold_hint') }}</p>
                </div>

                <div>
                    <label for="signal_minutes" class="text-sm font-semibold">{{ __('memory.form.exposure') }}</label>
                    <input type="number" id="signal_minutes" name="signal_minutes" min="1" max="720" step="1"
                           value="{{ old('signal_minutes', 60) }}"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400">{{ __('memory.form.exposure_hint') }}</p>
                </div>

                <div>
                    @php $strength = (float) old('strength', 0.7); @endphp
                    <div class="flex items-baseline justify-between gap-2">
                        <label for="strength" class="text-sm font-semibold">{{ __('memory.form.strength') }}</label>
                        <output for="strength" data-slider-output="strength" class="ltr-data text-xs font-bold text-brand-600">
                            {{ round($strength * 100) }}%
                        </output>
                    </div>
                    <input type="range" id="strength" name="strength" min="0.1" max="1" step="0.05"
                           value="{{ $strength }}" data-slider data-scale="100" data-suffix="%"
                           class="mt-2 w-full accent-brand-600">
                    <p class="mt-1 text-xs text-ink-400">{{ __('memory.form.strength_hint') }}</p>
                </div>
            </div>

            <div class="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-2.5">
                    <input type="hidden" name="must_be_reversible" value="0">
                    <input type="checkbox" name="must_be_reversible" value="1"
                           @checked(old('must_be_reversible') === '1')
                           class="mt-0.5 h-4 w-4 shrink-0 accent-brand-600">
                    <span>
                        <span class="block text-sm font-semibold">{{ __('memory.form.reversible') }}</span>
                        <span class="mt-0.5 block text-xs leading-relaxed text-ink-500">{{ __('memory.form.reversible_hint') }}</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-2.5">
                    <input type="hidden" name="on_plasmid" value="0">
                    <input type="checkbox" name="on_plasmid" value="1"
                           @checked(old('on_plasmid', '1') === '1')
                           class="mt-0.5 h-4 w-4 shrink-0 accent-brand-600">
                    <span>
                        <span class="block text-sm font-semibold">{{ __('memory.form.plasmid') }}</span>
                        <span class="mt-0.5 block text-xs leading-relaxed text-ink-500">{{ __('memory.form.plasmid_hint') }}</span>
                    </span>
                </label>
            </div>
        </div>

        {{-- The cargo. Optional, and the default is deliberately a promoter:
             flipping a promoter is what makes the stored bit readable. --}}
        <details class="panel mt-4 p-5" @if (old('payload')) open @endif>
            <summary class="cursor-pointer text-sm font-semibold">{{ __('memory.form.payload') }}</summary>
            <p class="mt-2 text-xs leading-relaxed text-ink-500">{{ __('memory.form.payload_hint') }}</p>

            <textarea name="payload" rows="4" maxlength="60000"
                      placeholder="{{ __('memory.form.payload_placeholder') }}"
                      class="ltr-data mt-3 w-full rounded-xl border border-line-strong bg-white p-3 text-xs leading-relaxed focus:border-brand-500 focus:outline-none">{{ old('payload') }}</textarea>

            <div class="mt-3">
                <label for="recombinase" class="text-sm font-semibold">{{ __('memory.form.recombinase') }}</label>
                <select id="recombinase" name="recombinase"
                        class="mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none sm:w-64">
                    @foreach (\App\Models\MemoryDesign::RECOMBINASES as $enzyme)
                        <option value="{{ $enzyme }}" @selected(old('recombinase', 'bxb1') === $enzyme)>
                            {{ __('memory.recombinases.' . $enzyme) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </details>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-md text-xs text-ink-400">{{ __('memory.form.note') }}</p>
            <button type="submit" data-submit class="btn btn-primary">
                {{ __('memory.form.submit') }}
            </button>
        </div>
    </form>

    @if ($recent->isNotEmpty())
        <section class="mx-auto mt-8 max-w-3xl">
            <div class="panel overflow-hidden">
                <div class="panel-head">
                    <h2 class="panel-title">{{ __('common.recent.title') }}</h2>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($recent as $item)
                        <li>
                            <a href="{{ route('memory.show', ['design' => $item->id]) }}"
                               class="flex items-center justify-between gap-4 px-5 py-3 text-sm hover:bg-paper">
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-semibold text-ink-900">
                                        {{ __('memory.signals.' . $item->signal) }} · {{ __('memory.chassis.' . $item->chassis) }}
                                    </span>
                                    <span class="mt-0.5 block truncate text-xs text-ink-400">
                                        {{ $item->architecture ? __('memory.architectures.' . $item->architecture . '.name') : '—' }}
                                        · <span class="ltr-data">{{ $item->hold_hours }}</span> {{ __('memory.units.hours') }}
                                    </span>
                                </span>
                                <span class="chip shrink-0 {{ $item->succeeded ? 'chip-good' : 'chip-alert' }}">
                                    {{ $item->succeeded ? __('common.recent.open') : __('memory.severity.error') }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
