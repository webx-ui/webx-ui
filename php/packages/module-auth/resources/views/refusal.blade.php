{{--
    The consent screen that could not be shown: an application the panel does not know, an
    address it was not registered with. In the frame of `webx-admin::page`, like the screen
    itself; the OAuth code is there for whoever the person sends a screenshot to.
--}}
@php
    /** @var \WebxUi\Admin\Manifest\Branding $brand */
    /** @var string $code */
@endphp
@extends('webx-admin::page')

@section('title', __('webx-auth::consent.refused-title'))

@push('webx-page-styles')
    <style>
        .code {
            display: inline-block; padding: var(--wx-space-2) var(--wx-space-8);
            border-radius: var(--wx-radius-xs); background: var(--wx-bg-muted); color: var(--wx-text-default);
            font-family: var(--wx-font-family-mono); font-size: var(--wx-font-size-xs); overflow-wrap: anywhere;
        }
    </style>
@endpush

@section('content')
    <div class="wx-page__brand">
        @if ($brand->logo !== null)
            <img src="{{ $brand->logo->url }}" alt="{{ $brand->title }}">
        @else
            <span>{{ $brand->title }}</span>
        @endif
    </div>

    <section class="wx-card wx-card--shadow-always wx-card--padding-lg">
        <div class="wx-card__body wx-page__stack">
            <h1 class="wx-page__title">{{ __('webx-auth::consent.refused-title') }}</h1>
            <p>{{ __('webx-auth::consent.refused-text') }}</p>
            <div><span class="code">{{ $code }}</span></div>
        </div>
    </section>
@endsection
