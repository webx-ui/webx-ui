{{--
    An event page: a fixed structure, one part per @include, so a site can publish one part —
    `event/facts`, say — and leave the rest to the package. The order of the parts is this file;
    a site that wants another order publishes this one too. What every part is handed is listed
    in Rendering\EventPage.

    The parts first, the layout after: the head slot is worked out before the body.
--}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $event))

@php(ob_start())
    <article class="wx-event">
        @if (config('webx-events.breadcrumbs', true))
            <x-webx-seo::breadcrumbs :for="$event" />
        @endif

        @include('webx-events::event.gallery')
        @include('webx-events::event.heading')
        @include('webx-events::event.facts')
        @include('webx-events::event.booking')
        @include('webx-events::event.description')
        @include('webx-events::event.highlights')
        @include('webx-events::event.services')
    </article>
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-events.layout') ?: 'webx-events::standalone'">
    <x-slot:head>
        {{-- The SEO card, the site defaults, and the Event markup (HasStructuredData). --}}
        @webxSeo($event)
        @if ($meta->title === null)
            <title>{{ $title }}</title>
        @endif
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
