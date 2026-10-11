---
'@webx-ui/php': minor
---

`webx-ui/themes`: the error pages of the chain. An HTML request that ends in an HTTP error gets `errors.<status>` looked up the way every view is — the site's `resources/views/errors`, the local theme, the layers below — instead of Laravel's stock page, which never saw the theme's views; no such view, a JSON request, or a view that fails to render go on to Laravel as before. The words of the layout, `theme::` (spec THEMES §15.2), are looked up through the chain too: each layer's `lang/<locale>/theme.php` bottom up, the site's `lang/vendor/theme/` on top. `webx-ui/theme-default`: `views/errors/404` — a missing address in the site's header and footer, in the language of its prefix, `noindex`, with the language switcher leading to each language's home page (`is-fallback`) and a button home; its words in ten languages.
