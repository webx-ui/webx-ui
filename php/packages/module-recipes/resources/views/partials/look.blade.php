{{--
    The look of the catalogue, the cards and the gallery's row on a site without a theme — the
    catalogue, the similar recipes and the gallery each include this, and it prints once.

    Under a theme the base look is resources/theme/recipes.css in the modules' stylesheet, which
    the theme restyles by coming later; this inline copy would come later still and win. The
    columns a block asks for are a class (`wx-recipes__grid--<n>`), not a `style` attribute, for
    the same reason.
--}}
@unless (function_exists('theme_active') && theme_active())
    @once
        @push('head')
            <style>
                .wx-recipes { container-type: inline-size; }
                .wx-recipes__filter, .wx-recipes__pages { display: flex; flex-wrap: wrap; gap: 0.5em; margin: 0 0 1.25em; padding: 0; list-style: none; }
                .wx-recipes__chip { display: inline-block; padding: 0.3em 0.9em; border: 1px solid currentColor; border-radius: 999px; color: inherit; text-decoration: none; opacity: 0.65; }
                .wx-recipes__chip[aria-current] { opacity: 1; font-weight: 600; }
                .wx-recipes__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.5em; margin: 0 0 1.5em; padding: 0; list-style: none; }
                .wx-recipes__grid--1 { grid-template-columns: minmax(0, 1fr); }
                .wx-recipes__grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .wx-recipes__grid--4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
                .wx-recipes__grid--5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
                .wx-recipes__grid--6 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
                @container (max-width: 40rem) { .wx-recipes__grid:not(.wx-recipes__grid--1) { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
                @container (max-width: 24rem) { .wx-recipes__grid:not(.wx-recipes__grid--1) { grid-template-columns: minmax(0, 1fr); } }
                .wx-recipes__link { display: flex; flex-direction: column; gap: 0.6em; color: inherit; text-decoration: none; }
                .wx-recipes__cover { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 0.5em; }
                .wx-recipes__name { font-weight: 600; }
                .wx-recipes__card { display: flex; flex-direction: column; gap: 0.4em; }
                .wx-recipes__services { font-size: 0.9em; }
                .wx-recipes__services a, .wx-recipes__category { color: inherit; }
                .wx-recipes__meta { display: flex; flex-wrap: wrap; gap: 0.5em; opacity: 0.7; font-size: 0.9em; }
                .wx-recipes__meta > * + *::before { content: "·"; margin-inline-end: 0.5em; }
                .wx-recipes__pages a[aria-current] { font-weight: 600; text-decoration: none; }
                .wx-recipe__strip { display: flex; gap: 0.5em; list-style: none; padding: 0; overflow-x: auto; }
            </style>
        @endpush
    @endonce
@endunless
