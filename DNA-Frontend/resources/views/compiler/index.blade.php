@extends('layouts.app')

@section('title', __('compiler.hero.title'))

@section('content')
    <x-tabs active="compiler" />

    <section class="mx-auto max-w-2xl text-center">
        <p class="eyebrow">{{ __('compiler.hero.eyebrow') }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
            {{ __('compiler.hero.title') }}
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-ink-500">
            {{ __('compiler.hero.subtitle') }}
        </p>
    </section>

    @if ($errors->any())
        <div class="mx-auto mt-6 max-w-2xl">
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

    <form method="POST" action="{{ route('compiler.store') }}" data-compile-form class="mx-auto mt-7 max-w-2xl">
        @csrf

        <label for="description" class="mb-2 block text-sm font-semibold">
            {{ __('compiler.hero.label') }}
        </label>

        <textarea id="description"
                  name="description"
                  rows="4"
                  required
                  maxlength="2000"
                  data-compile-input
                  placeholder="{{ __('compiler.hero.placeholder') }}"
                  class="w-full rounded-xl border border-line-strong bg-white p-4 text-sm leading-relaxed
                         focus:border-brand-500 focus:outline-none">{{ old('description') }}</textarea>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <span class="ltr-data text-xs text-ink-400" data-char-count>0 / 2000</span>
            <button type="submit" data-submit class="btn btn-primary">
                {{ __('compiler.hero.submit') }}
            </button>
        </div>
    </form>

    <section class="mx-auto mt-8 max-w-2xl">
        <p class="eyebrow mb-2">{{ __('compiler.hero.examples') }}</p>
        <div class="grid gap-2">
            @foreach (['basic', 'and_gate', 'or_gate', 'inverter'] as $example)
                {{-- Filling the textarea rather than submitting: the point is to
                     show the user the shape of a sentence the parser understands,
                     so they can edit it before compiling. --}}
                <button type="button"
                        data-example
                        data-text="{{ __('compiler.examples.' . $example) }}"
                        class="panel px-4 py-3 text-start text-sm text-ink-700 transition hover:border-brand-500 hover:bg-brand-50">
                    {{ __('compiler.examples.' . $example) }}
                </button>
            @endforeach
        </div>
    </section>

    @if ($recent->isNotEmpty())
        <section class="mx-auto mt-6 max-w-2xl">
            <div class="panel overflow-hidden">
                <div class="panel-head">
                    <h2 class="panel-title">{{ __('common.recent.title') }}</h2>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($recent as $item)
                        <li>
                            <a href="{{ route('compiler.show', ['circuit' => $item->id]) }}"
                               class="flex items-center justify-between gap-4 px-5 py-3 text-sm hover:bg-paper">
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-semibold text-ink-900">{{ Str::limit($item->source_text, 70) }}</span>
                                    <span class="ltr-data mt-0.5 block truncate text-xs text-ink-400">{{ $item->expression ?: '—' }}</span>
                                </span>
                                <span class="chip shrink-0 {{ $item->succeeded ? 'chip-good' : 'chip-alert' }}">
                                    {{ $item->succeeded ? __('common.recent.open') : __('compiler.severity.error') }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
