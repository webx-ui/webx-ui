{{--
    A recipe page: a fixed structure, one part per @include, so a site can publish one part —
    `recipe/facts`, say — and leave the rest to the package. The order of the parts is this file;
    a site that wants another order publishes this one too. What every part is handed is listed
    in Rendering\RecipePage.

    The parts first, the layout after: `@webxPartAssets` prints the bundle of what was rendered
    — the similar recipes are cards, and a customised card brings its own styles and script —
    and the head slot is worked out before the body.
--}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $recipe))

@php(ob_start())
    <article class="wx-recipe">
        @if (config('webx-recipes.breadcrumbs', true))
            <x-webx-seo::breadcrumbs :for="$recipe" />
        @endif

        @include('webx-recipes::recipe.gallery')
        @include('webx-recipes::recipe.heading')
        @include('webx-recipes::recipe.facts')
        @include('webx-recipes::recipe.ingredients')
        @include('webx-recipes::recipe.method')
        @include('webx-recipes::recipe.nutrition')
        @include('webx-recipes::recipe.services')
        @include('webx-recipes::recipe.similar')
    </article>
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-recipes.layout') ?: 'webx-recipes::standalone'">
    <x-slot:head>
        {{-- The SEO card, the site defaults, and the Recipe markup (HasStructuredData). --}}
        @webxSeo($recipe)
        @if ($meta->title === null)
            <title>{{ $title }}</title>
        @endif
        @webxPartAssets
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
