@extends('layouts.app')

@section('title', __('common.hero.title'))

@section('content')
    <x-tabs active="analysis" />

    <section class="mx-auto max-w-2xl text-center">
        <p class="eyebrow">{{ __('common.hero.eyebrow') }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
            {{ __('common.hero.title') }}
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-ink-500">
            {{ __('common.hero.subtitle') }}
        </p>
    </section>

    @if ($errors->any())
        <div class="mx-auto mt-6 max-w-2xl">
            <div class="alert" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
                </svg>
                <div>
                    <p class="font-semibold">{{ __('errors.heading') }}</p>
                    @foreach ($errors->all() as $message)
                        <p class="mt-1">{{ $message }}</p>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <form method="POST"
          action="{{ route('analysis.store') }}"
          enctype="multipart/form-data"
          data-upload-form
          class="mx-auto mt-7 max-w-2xl space-y-4">
        @csrf

        <label class="dropzone" data-dropzone for="fasta_file">
            <input type="file"
                   name="fasta_file"
                   id="fasta_file"
                   accept=".fasta,.fa,.fna,.txt"
                   required
                   data-file-input
                   class="sr-only">

            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                 class="mx-auto h-8 w-8 text-ink-300" aria-hidden="true">
                <path d="M12 16V4m0 0L8 8m4-4 4 4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke-linecap="round"/>
            </svg>

            <span data-file-label class="mt-2 block text-sm font-semibold">
                {{ __('common.hero.dropzone') }}
            </span>
            <span class="mt-1 block text-xs text-ink-400">
                {{ __('common.hero.dropzone_hint', ['megabytes' => round(config('services.backend.max_upload_kb', 10240) / 1024)]) }}
            </span>
        </label>

        <div class="text-center">
            <button type="submit" data-submit class="btn btn-primary w-full sm:w-auto">
                {{ __('common.hero.submit') }}
            </button>
        </div>
    </form>

    <section class="mx-auto mt-10 max-w-2xl">
        <div class="panel overflow-hidden">
            <div class="panel-head">
                <h2 class="panel-title">{{ __('common.hero.example_title') }}</h2>
            </div>
            <div class="p-4">
                <pre class="code-block"><span style="color:#7dd3a0">&gt;Human_HBA1 Human haemoglobin subunit alpha</span>
ACTCTTCTGGTCCCCACAGACTCAGAGAGAACCCACCATG
GTGCTGTCTCCTGCCGACAAGACCAACGTC
<span style="color:#7dd3a0">&gt;Bat_HBA1 Bat haemoglobin subunit alpha</span>
ACTCTTCTGGTCCCCACAGACTCAGAGAGAACCCACCATG
GTGCTGTCTCCTGCAGATAAGACCAACGTC</pre>
                <p class="mt-3 text-xs text-ink-500">{{ __('common.hero.example_note') }}</p>
            </div>
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
                            <a href="{{ route('analysis.show', ['analysis' => $item->id]) }}"
                               class="flex items-center justify-between gap-4 px-5 py-3 text-sm hover:bg-paper">
                                <span class="min-w-0">
                                    <span class="ltr-data block truncate text-xs font-semibold text-ink-900">{{ $item->filename }}</span>
                                    <span class="mt-0.5 block text-xs text-ink-400">
                                        {{ __('common.recent.records', ['count' => $item->gene_count]) }}
                                        · {{ $item->created_at->diffForHumans() }}
                                    </span>
                                </span>
                                <span class="chip chip-muted shrink-0">{{ __('common.recent.open') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
