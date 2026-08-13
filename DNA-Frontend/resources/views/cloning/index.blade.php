@extends('layouts.app')

@section('title', __('cloning.hero.title'))

@section('content')
    <x-tabs active="cloning" />

    <section class="mx-auto max-w-2xl text-center">
        <p class="eyebrow">{{ __('cloning.hero.eyebrow') }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
            {{ __('cloning.hero.title') }}
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-ink-500">
            {{ __('cloning.hero.subtitle') }}
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

    <form method="POST" action="{{ route('cloning.store') }}" data-cloning-form class="mx-auto mt-7 max-w-3xl">
        @csrf

        {{-- The template. Pasted rather than uploaded: the region being cloned
             is usually copied out of a map, not saved to a file first. --}}
        <div class="panel p-5">
            <label for="sequence" class="mb-1.5 block text-sm font-semibold">
                {{ __('cloning.form.sequence') }}
            </label>
            <textarea id="sequence" name="sequence" rows="7" required
                      @class(['ltr-data w-full rounded-lg border bg-white px-3 py-2 font-mono text-xs leading-relaxed focus:outline-none',
                              'border-alert-400' => $errors->has('sequence'),
                              'border-line-strong focus:border-brand-500' => ! $errors->has('sequence')])
                      aria-describedby="sequence-hint"
                      @if ($errors->has('sequence')) aria-invalid="true" aria-errormessage="sequence-error" @endif
            >{{ old('sequence') }}</textarea>
            <p id="sequence-hint" class="mt-1 text-xs text-ink-400">{{ __('cloning.form.sequence_hint') }}</p>
            @error('sequence')
                <p id="sequence-error" class="mt-1 text-xs text-alert-600">{{ $message }}</p>
            @enderror

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="label" class="mb-1.5 block text-sm font-semibold">{{ __('cloning.form.label') }}</label>
                    <input id="label" name="label" type="text" maxlength="120" value="{{ old('label') }}"
                           class="w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400">{{ __('cloning.form.label_hint') }}</p>
                </div>

                <div>
                    <label for="panel" class="mb-1.5 block text-sm font-semibold">{{ __('cloning.form.panel') }}</label>
                    <select id="panel" name="panel"
                            class="w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        @foreach (\App\Models\CloningPlan::PANELS as $panel)
                            <option value="{{ $panel }}" @selected(old('panel', 'teaching') === $panel)>
                                {{ __('cloning.form.panel_' . $panel) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" name="circular" value="1" @checked(old('circular'))
                           class="mt-0.5 h-4 w-4 rounded border-line-strong text-brand-600 focus:ring-brand-500">
                    <span>
                        <span class="font-semibold">{{ __('cloning.form.circular') }}</span>
                        <span class="block text-xs text-ink-400">{{ __('cloning.form.circular_hint') }}</span>
                    </span>
                </label>
            </div>
        </div>

        {{-- Primer design is opt-in but on by default: a reader who only wants a
             site map can turn it off, and everyone else gets the answer they
             actually came for without hunting for a second button. --}}
        <div class="panel mt-4 p-5">
            <label class="flex items-center gap-2.5 text-sm">
                <input type="checkbox" name="design_primers" value="1" @checked(old('design_primers', true))
                       class="h-4 w-4 rounded border-line-strong text-brand-600 focus:ring-brand-500">
                <span class="font-semibold">{{ __('cloning.form.design_primers') }}</span>
            </label>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <span class="mb-1.5 block text-sm font-semibold">{{ __('cloning.form.target_start') }}</span>
                    <div class="flex items-center gap-2">
                        <input id="target_start" name="target_start" type="number" min="1" value="{{ old('target_start') }}"
                               aria-label="{{ __('cloning.form.target_start') }}"
                               class="ltr-data w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <span class="shrink-0 text-xs text-ink-400">{{ __('cloning.form.target_end') }}</span>
                        <input id="target_end" name="target_end" type="number" min="1" value="{{ old('target_end') }}"
                               aria-label="{{ __('cloning.form.target_end') }}"
                               class="ltr-data w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    </div>
                    <p class="mt-1 text-xs text-ink-400">{{ __('cloning.form.target_hint') }}</p>
                </div>

                <div>
                    <label for="target_tm" class="mb-1.5 block text-sm font-semibold">{{ __('cloning.form.target_tm') }}</label>
                    <input id="target_tm" name="target_tm" type="number" step="0.5" min="45" max="75"
                           value="{{ old('target_tm', 60) }}"
                           class="ltr-data w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
            </div>

            <details class="mt-5 border-t border-line pt-4">
                <summary class="cursor-pointer text-sm font-semibold">{{ __('cloning.form.advanced') }}</summary>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="min_length" class="mb-1.5 block text-sm font-semibold">{{ __('cloning.form.min_length') }}</label>
                        <input id="min_length" name="min_length" type="number" min="15" max="40" value="{{ old('min_length', 18) }}"
                               class="ltr-data w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="max_length" class="mb-1.5 block text-sm font-semibold">{{ __('cloning.form.max_length') }}</label>
                        <input id="max_length" name="max_length" type="number" min="15" max="45" value="{{ old('max_length', 30) }}"
                               class="ltr-data w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    </div>
                </div>
            </details>
        </div>

        {{-- The tails, and the check that makes this tab worth having: an
             enzyme added here is verified against the fragment it will have to
             not cut. --}}
        <div class="panel mt-4 p-5">
            <p class="eyebrow mb-1">{{ __('cloning.form.tails') }}</p>
            <p class="mb-4 text-xs text-ink-400">{{ __('cloning.form.tails_hint') }}</p>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="forward_enzyme" class="mb-1.5 block text-sm font-semibold">
                        {{ __('cloning.form.forward_enzyme') }}
                    </label>
                    <input id="forward_enzyme" name="forward_enzyme" type="text" maxlength="20"
                           placeholder="{{ __('cloning.form.enzyme_placeholder') }}"
                           value="{{ old('forward_enzyme') }}"
                           class="ltr-data w-full rounded-lg border border-line-strong bg-white px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label for="reverse_enzyme" class="mb-1.5 block text-sm font-semibold">
                        {{ __('cloning.form.reverse_enzyme') }}
                    </label>
                    <input id="reverse_enzyme" name="reverse_enzyme" type="text" maxlength="20"
                           placeholder="{{ __('cloning.form.enzyme_placeholder') }}"
                           value="{{ old('reverse_enzyme') }}"
                           class="ltr-data w-full rounded-lg border border-line-strong bg-white px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none">
                </div>
            </div>
        </div>

        <div class="mt-5 flex justify-center">
            <button type="submit" class="btn btn-primary" data-submit>
                {{ __('cloning.hero.submit') }}
            </button>
        </div>
    </form>

    <x-samples group="cloning" class="mt-10" />

    @if ($recent->isNotEmpty())
        <section class="mx-auto mt-10 max-w-3xl">
            <h2 class="mb-3 text-sm font-bold">{{ __('cloning.recent.title') }}</h2>
            <ul class="panel divide-y divide-line">
                @foreach ($recent as $plan)
                    <li class="flex items-center justify-between gap-4 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">
                                {{ $plan->label ?: __('cloning.recent.untitled') }}
                            </p>
                            <p class="text-xs text-ink-400">
                                <span class="ltr-data">{{ __('cloning.recent.length', ['count' => $plan->template_length]) }}</span>
                                · {{ $plan->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <a href="{{ route('cloning.show', ['plan' => $plan->id]) }}" class="btn btn-quiet btn-sm shrink-0">
                            {{ __('cloning.recent.open') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
