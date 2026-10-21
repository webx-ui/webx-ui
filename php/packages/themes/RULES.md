# The rules of a theme

The rules every theme keeps — the local `theme/` of a site and a packaged theme alike. They are
numbered: `webx:theme:check` and the theme's test (`Testing\ThemeContract`) name a rule by its
number when they find it broken. A rule a machine checks says which part of the check holds it;
the rest is for the eye and the review.

Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_THEMES.md
(§16.1 is this list).

## Where to work

1. **Do** change the look of the site only in `theme/` (or in the theme's package). **Do not**
   change it in `vendor/`, in the site's `resources/views` or in `public/`: an override in
   `resources/views` is the developer's escape hatch, not the theme's way.
2. **Do** read the chain first: `php artisan webx:theme:show` — what is already there below, which
   blocks, views and tokens. **Do not** write what `theme-default` or a module already gives.
3. **Do** make a change through the cheapest door that solves it: a token → CSS on the classes →
   an extension point (`@webxPart`) → an override of a view. **Do not** override a view when CSS
   was enough: that is a mistake, not a style.

## Tokens

4. **Do** write every colour, font, radius, space, shadow and duration as `var(--site-…)`.
   **Do not** write a literal value anywhere but `tokens.json`. (check §8)
5. **Do** reach for a role of the vocabulary first (`accent-2`, `highlight`, `surface-muted`) when
   a colour is missing; only when none fits, add a token of your own under `vocabulary` with a
   **role** name (`color-badge`). **Do not** name it after a shade (`color-pink`).
6. **Do not** put a fallback into `var()`: `var(--site-x, #fff)` does not repaint. There are three
   prefixes — `--site-*` a token of the site, `--webx-*` a package's own variable, `--wx-*` the
   panel, never on the public site. **Do not** invent a fourth. (check §8)
7. **Do** put the brand's values into the `defaults` of the local theme, variants into presets,
   and the dark scheme into `schemes.dark` (§7.6). **Do not** copy a theme to make a variant of
   it. **Do not** write `light-dark()` into a token: its contrast is not checked, and the engine
   does not set `color-scheme` and `data-theme` for it.
8. **Do** name the site's preset with `"preset"` in the manifest. **Do not** choose it with an
   environment variable or a setting in the database: the look of the site is all in git.
   (check: a preset the chain does not have)

## Styles — each in one place

9. **Do** style one block in the block itself, under `.b-<slug>`. **Do not** write a `.b-<slug>`
   selector in `src/css`: it is in the wrong place.
10. **Do** keep in `src/css` only the shell, the grid, the typography and the look of module
    pages and widgets on their classes.
11. **Do** style a module's pages by the `webx-<module>-*` classes listed in the module's
    `AGENTS.md`. **Do not** style them by tags or nesting (`.webx-catalog-listing > div > a`): the
    markup inside may change, the classes are the contract.
12. **Do** let the container decide the width: `@container`. **Do not** use `@media` by width in
    blocks and `src/css`. **Do not** write `@media (prefers-color-scheme)` either: the engine
    prints the dark scheme from `tokens.json` and honours `data-theme` on `<html>`. (check §8)
13. **Do not** write bare element selectors (`h2`, `a`, `img`) outside the typography of prose,
    under its class.
14. **Do not** use `!important`, except for the utility ones (`[hidden]`): a cascade problem is
    solved by the order of the layers, not by force.

## Blocks

15. **Do** make a new block only when no block of `theme-default` or of a module solves the task
    with its fields. **Do** give an existing block another look by overriding **the same slug**.
    **Do not** make a new block with a similar name.
16. **Do** keep an overridden schema **compatible**: the same fields with the same types; new
    fields may be added. **Do not** remove a field or change its type. (check §9.2)
17. **Do** give a block's root `data-wx-block="<slug>"` and put every selector under
    `.b-<slug>`. (lint of the blocks)
18. **Do** make every text of a block a visitor reads a field of the schema with `localized`;
    fixed labels are `__('theme::…')`. **Do not** write a string into the template.
19. **Do** make a slider, a lightbox, a video, a map, tabs, an accordion or a dialog with a
    **widget**. **Do not** write your own script or bring your own library for them. Anything of a
    third party (YouTube, maps, counters) goes only through a widget or with
    `data-webx-consent`: before consent, a page makes no request to a third party. A script of
    your own only for what the widgets lack, and by the contract of the entry.
20. **Do** export a block made in the panel into `theme/blocks/`
    (`php artisan webx:blocks:export <slug> --path=theme/blocks`) and commit it. **Do not** leave a
    block only in the database: the next deployment from scratch loses it.

## Views

21. **Do** keep `@webxTheme`, `@webxSeo`, `@webxBlocks` and the `header`/`footer` regions in
    `components/layout`. (contract: the layout of the chain)
22. **Do** copy the **current** version from `vendor/` when you override a view of a module, and
    change the least. **Do not** remove its classes or `@webxPart`: the module's companions stand
    on them.
23. **Do** link through `route()` and the addresses of entities, menus through `menu('…')`,
    contacts and the name through `settings()`. **Do not** hard-code a path or a text of the site
    into a template.
24. **Do** draw an icon with `<x-theme::icon>` from the sprite and a picture of the theme with
    `theme_asset()`. **Do not** paste an inline `<svg>` into a template. (check: an unknown icon,
    a missing asset)
25. **Do** take the values of a letter from `theme_token()` in `style="…"`. **Do not** write
    `var()` into a letter. (check §7.5)

## Languages and fonts

26. **Do** have every word of the layout in every language of the theme's `locales`: a key added
    to `en` is added to all. (check: the keys of each language)
27. **Do** ship a font as the theme's own files in `assets/fonts/`, with the scripts of every
    language of the site. **Do not** load it from Google Fonts or another CDN. (check §15.3)
28. **Do not** put text into the pictures of the demo.

## Accessibility and layout

29. **Do** keep the contrast of text and background in every preset and in the dark scheme at
    WCAG AA or above (4.5:1 for text, 3:1 for large text and controls). (contract: `text`/`bg`,
    `text`/`surface`, `text-muted`/`bg` and `surface`, `accent-contrast`/`accent`, `accent`/`bg`,
    by the merged values of each preset and of its dark scheme; a note in `webx:theme:check`)
30. **Do** give everything interactive a visible focus (`:focus-visible`) and a target of at least
    44×44 on a phone.
31. **Do not** let the page scroll sideways at a width of 360px.

## Before you say it is done

32. **Do** run `php artisan webx:theme:check` — no findings — and the theme's test
    (`ThemeContract`) — green.
33. **Do** take screenshots in a browser: the home page, one inner page and the page of each
    installed module — on a desktop and at 375px, in every preset. jsdom does not check layout.
34. **Do** measure rather than look, where you can: `scrollWidth` against `clientWidth` for a
    sideways scroll, `getComputedStyle` for whether a token really arrived.
35. **Do** report to the person what changed, through which door and why, and which views are
    overridden. Whether the theme is good is the person's decision.

## Speed

36. **Do** serve a photo as WebP or AVIF, at the width the layout draws it: a picture of the media
    library with its preview and its file in `srcset`/`sizes`, the way the gallery of
    `webx-ui/widgets` does; an old library converted with `php artisan webx:media:webp`. **Do not** put a 3000px JPEG into a card 400px wide.
37. **Do** give every `<img>` its `width` and `height` — of the file, or the size the layout draws
    it at — so the page keeps its place before the file arrives. **Do not** leave the browser to
    find the size out: the text below jumps when the picture loads. (check: `<img>` without both)
38. **Do** write `loading="lazy"` on pictures below the first screen, and `fetchpriority="high"` on
    the one picture that is the largest thing of the first screen (the hero). **Do not** make the
    first screen lazy: its picture then arrives last.
39. **Do** ship fonts as `.woff2`, one file per script (rule 27 gives each its `unicode-range`), as
    few faces as the design needs — a variable font is one file for every weight; `@webxTheme`
    prints `font-display: swap`. **Do not** add a `<link rel="preload">` for a font: a preload
    downloads it on every page, whatever its language. (check: a font that is not `.woff2` —
    a note)
40. **Do** let a page load only what it shows: a widget's script and style come with its component
    (`Widgets::need()`), the map, the video and anything heavy start when they come near the
    screen. **Do not** put a library into the theme's own bundle for one page.
41. **Do** measure the home page and the heaviest page of the site after a change of the look — in
    the browser's performance panel or Lighthouse, at a phone's width: the largest picture
    (LCP), the jumps (CLS), the bytes. **Do not** judge speed on a fast machine by eye.
