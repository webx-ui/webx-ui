{{--
    The gate of a closed site: what a visitor sees instead of the site. In the frame of the
    panel's own pages (`webx-admin::page`) — the panel's tokens and its card.
--}}
@extends('webx-admin::page')

@section('title', __('webx-admin::gate.title'))

@section('content')
    <section class="wx-card wx-card--shadow-always wx-card--padding-lg">
        <div class="wx-card__body wx-page__stack">
            <h1 class="wx-page__title">{{ __('webx-admin::gate.title') }}</h1>
            <p class="wx-page__muted">{{ __('webx-admin::gate.text') }}</p>
        </div>
    </section>
@endsection
