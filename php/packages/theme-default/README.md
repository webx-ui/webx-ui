# webx-ui/theme-default

The theme every new WebX UI site stands on.

It is not meant to be beautiful; it is meant to be a good place to start: a value for every site
token, a neutral page shell and the typography of prose, in code that is easy to override. A new
site gets its own `theme/` on top of it and changes only what makes it different.

```
resources/views          ← the site itself
theme/                   ← the site's local theme: its differences
  → webx-ui/theme-default ← this package
    → modules            ← the look of their own pages
```

Part of [WebX UI](https://github.com/webx-ui/webx-ui). The mechanism — the chain, the token
vocabulary, `@webxTheme` — is [`webx-ui/themes`](https://github.com/webx-ui/themes).

> Early days: this release holds the tokens, the layout with its header and footer, and the shell
> and prose stylesheet. Block types, icons, fonts, layout words, mail and error pages arrive in the
> next releases.

## What is in it

| Path                      | What                                                                                   |
| ------------------------- | -------------------------------------------------------------------------------------- |
| `tokens.json`             | a value for every token of the engine's vocabulary, and the presets `warm` and `night` |
| `views/components/layout` | the document: `@webxTheme`, the head, the `header` and `footer` regions, `<main>`      |
| `views/components/header` | the header region's fallback: the site name and `menu('header')`                       |
| `views/components/footer` | the footer region's fallback: `menu('footer')` and the copyright line                  |
| `src/css/`                | `base.css`, `shell.css` (`site-*` classes), `prose.css` (headings, lists, tables…)     |
| `dist/theme.css`          | the build of `src/`, committed: a site installs this theme with no Node                |

Every rule is written on `var(--site-…)` tokens only and inside `:where()`, so it carries no
specificity: a local theme, a module or a block overrides it by writing the rule at all.

## Install

`php artisan webx:setup` is about to install it with every new site. Until then, by hand:

```bash
composer require webx-ui/theme-default
php artisan webx:theme:sync
```

and `WEBX_THEME=webx-ui/theme-default` (or a local `theme/` that `uses` it) in `.env`.

## Working on it

`src/` is built into `dist/` by Vite, and `dist/` is committed. After an edit:

```bash
npx vite build
```

in this directory. `dist/sources.json` records what the build was made from, and the package's test
fails when `src/` and `dist/` disagree.

## License

MIT
