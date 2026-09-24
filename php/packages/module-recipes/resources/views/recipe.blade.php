{{--
    A recipe page: a fixed structure, one part per @include, so a site can publish one part —
    `recipe/facts`, say — and leave the rest to the package. The order of the parts is this file;
    a site that wants another order publishes this one too. What every part is handed is listed
    in Rendering\RecipePage.
--}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $recipe))

<x-dynamic-component :component="config('webx-recipes.layout') ?: 'webx-recipes::standalone'">
    <x-slot:head>
        {{-- The SEO card, the site defaults, and the Recipe markup (HasStructuredData). --}}
        @webxSeo($recipe)
        @if ($meta->title === null)
            <title>{{ $title }}</title>
        @endif
    </x-slot:head>

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
</x-dynamic-component>
