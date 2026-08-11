@extends('layouts.app')

@section('title', __('simulator.hero.title'))

@section('content')
    <x-tabs active="simulator" />

    <section class="mx-auto max-w-2xl text-center">
        <p class="eyebrow">{{ __('simulator.hero.eyebrow') }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
            {{ __('simulator.hero.title') }}
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-ink-500">
            {{ __('simulator.hero.subtitle') }}
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

    <form method="POST" action="{{ route('simulator.store') }}" data-simulate-form class="mx-auto mt-7 max-w-3xl">
        @csrf

        {{-- The network comes first because everything below it is a detail of
             the question this picks. Each option states what it answers, not
             just what it contains. --}}
        <fieldset>
            <legend class="mb-2 block text-sm font-semibold">{{ __('simulator.form.network') }}</legend>

            <div class="grid gap-2">
                @foreach (\App\Models\Simulation::PRESETS as $index => $preset)
                    @php $checked = old('preset', 'crosstalk_pair') === $preset; @endphp
                    <label class="panel flex cursor-pointer gap-3 p-4 transition hover:border-brand-500 hover:bg-brand-50
                                  has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                        <input type="radio" name="preset" value="{{ $preset }}" @checked($checked)
                               class="mt-1 h-4 w-4 shrink-0 accent-brand-600">
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-ink-900">
                                {{ __('simulator.presets.' . $preset . '.name') }}
                            </span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-ink-500">
                                {{ __('simulator.presets.' . $preset . '.description') }}
                            </span>
                            <span class="mt-1.5 block text-xs font-semibold text-brand-600">
                                {{ __('simulator.presets.' . $preset . '.question') }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="panel mt-4 p-5">
            <p class="eyebrow mb-4">{{ __('simulator.form.conditions') }}</p>

            <div class="grid gap-5 sm:grid-cols-2">
                @php
                    $sliders = [
                        ['name' => 'induction', 'min' => 0, 'max' => 1, 'step' => 0.05, 'default' => 1, 'suffix' => '%', 'scale' => 100],
                        ['name' => 'crosstalk', 'min' => 0, 'max' => 1, 'step' => 0.05, 'default' => 0.4, 'suffix' => '%', 'scale' => 100],
                        ['name' => 'variability', 'min' => 0, 'max' => 0.6, 'step' => 0.05, 'default' => 0.2, 'suffix' => '%', 'scale' => 100],
                    ];
                @endphp

                @foreach ($sliders as $slider)
                    @php $value = (float) old($slider['name'], $slider['default']); @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-2">
                            <label for="{{ $slider['name'] }}" class="text-sm font-semibold">
                                {{ __('simulator.form.' . $slider['name']) }}
                            </label>
                            <output for="{{ $slider['name'] }}" data-slider-output="{{ $slider['name'] }}"
                                    class="ltr-data text-xs font-bold text-brand-600">
                                {{ round($value * $slider['scale']) }}{{ $slider['suffix'] }}
                            </output>
                        </div>
                        <input type="range" id="{{ $slider['name'] }}" name="{{ $slider['name'] }}"
                               min="{{ $slider['min'] }}" max="{{ $slider['max'] }}" step="{{ $slider['step'] }}"
                               value="{{ $value }}"
                               data-slider data-scale="{{ $slider['scale'] }}" data-suffix="{{ $slider['suffix'] }}"
                               class="mt-2 w-full accent-brand-600">
                        <p class="mt-1 text-xs leading-relaxed text-ink-400">
                            {{ __('simulator.form.' . $slider['name'] . '_hint') }}
                        </p>
                    </div>
                @endforeach

                <div>
                    <span class="text-sm font-semibold">{{ __('simulator.form.resources') }}</span>
                    <label class="mt-2 flex cursor-pointer items-start gap-2.5">
                        <input type="hidden" name="resource_coupling" value="0">
                        <input type="checkbox" name="resource_coupling" value="1"
                               @checked(old('resource_coupling', '1') === '1')
                               class="mt-0.5 h-4 w-4 shrink-0 accent-brand-600">
                        <span class="text-xs leading-relaxed text-ink-500">
                            {{ __('simulator.form.resources_hint') }}
                        </span>
                    </label>
                </div>
            </div>

            <div class="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-3">
                <div>
                    <label for="cells" class="text-sm font-semibold">{{ __('simulator.form.cells') }}</label>
                    <input type="number" id="cells" name="cells" min="4" max="200" step="1"
                           value="{{ old('cells', 60) }}"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm
                                  focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400">{{ __('simulator.form.cells_hint') }}</p>
                </div>

                <div>
                    <label for="minutes" class="text-sm font-semibold">{{ __('simulator.form.duration') }}</label>
                    <input type="number" id="minutes" name="minutes" min="5" max="240" step="5"
                           value="{{ old('minutes', 60) }}"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm
                                  focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400">{{ __('simulator.form.duration_hint') }}</p>
                </div>

                <div>
                    <label for="seed" class="text-sm font-semibold">{{ __('simulator.form.seed') }}</label>
                    <input type="number" id="seed" name="seed" min="0" max="2147483646" step="1"
                           value="{{ old('seed') }}" placeholder="{{ __('simulator.form.seed_placeholder') }}"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm
                                  focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400">{{ __('simulator.form.seed_hint') }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-md text-xs text-ink-400">{{ __('simulator.form.wait_warning') }}</p>
            <button type="submit" data-submit class="btn btn-primary">
                {{ __('simulator.form.submit') }}
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
                            <a href="{{ route('simulator.show', ['simulation' => $item->id]) }}"
                               class="flex items-center justify-between gap-4 px-5 py-3 text-sm hover:bg-paper">
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-semibold text-ink-900">
                                        {{ __('simulator.presets.' . $item->preset . '.name') }}
                                    </span>
                                    <span class="ltr-data mt-0.5 block truncate text-xs text-ink-400">
                                        {{ $item->cells }} {{ __('simulator.units.cells') }}
                                        · {{ $item->minutes }} {{ __('simulator.units.min') }}
                                        · seed {{ $item->seed }}
                                    </span>
                                </span>
                                <span class="chip shrink-0 {{ $item->succeeded ? 'chip-good' : 'chip-alert' }}">
                                    {{ $item->succeeded ? __('common.recent.open') : __('simulator.severity.error') }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
