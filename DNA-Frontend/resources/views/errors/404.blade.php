@extends('layouts.app')

@section('title', __('errors.not_found.title'))

@section('content')
    <div class="mx-auto max-w-md py-16 text-center">
        <p class="eyebrow">404</p>
        <h1 class="mt-2 text-2xl font-bold">{{ __('errors.not_found.title') }}</h1>
        <p class="mt-2 text-sm text-ink-500">{{ __('errors.not_found.body') }}</p>
        <a href="{{ url('/' . app()->getLocale()) }}" class="btn btn-primary mt-6">
            {{ __('errors.not_found.action') }}
        </a>
    </div>
@endsection
