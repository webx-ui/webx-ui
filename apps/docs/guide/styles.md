# Where the styles live

What a site looks like is spread over three places, and only two of them are the site's to edit.
This page says which file holds what, what is safe to change and what is not, and how to see a
change — for a person changing the design with an agent, and for the agent itself.

## Three looks, three places

| Look                                    | Where it lives                                                              | Whose                                     |
| --------------------------------------- | --------------------------------------------------------------------------- | ----------------------------------------- |
| The public site — pages, header, footer | the site's theme: `theme/`, over the packaged theme it stands on; `public/` | **the site's**, edit `theme/` freely      |
| Blocks on the pages                     | block types, made in the panel («Blocks») and stored in the database        | **the site's**, in the panel              |
| The admin panel                         | the packages `@webx-ui/*` in `node_modules/` and `webx-ui/*` in `vendor/`   | the library's — rebrand it, never edit it |

## The public site

A new site stands on a theme: `webx:setup` creates `theme/` over `webx-ui/theme-default`, which
brings the layout, the header, the footer, the typography and a value for every site token — so
the site opens finished. `theme/` holds only what makes this site different, and the modules'
pages (a page, an article, a product) stand inside the theme's layout.

| You want to change                    | Edit                                                                                                 |
| ------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Colours, fonts, radii, spacing        | `theme/tokens.json` — `"defaults": { "color-accent": "#…" }`; every page, block and module follows   |
| Anything else about the look          | `theme/src/css/theme.css`, on the classes the shell and the modules print, with `var(--site-…)` only |
| The frame of every page, the `<head>` | copy `components/layout.blade.php` from `vendor/webx-ui/theme-default/views/` into `theme/views/`    |
| The header and the footer             | the same, `components/header.blade.php` and `footer.blade.php` — shown while the region is empty     |
| The menu items                        | the panel («Menus»), not the view — the header prints `menu('header')`                               |
| Images, the favicon, fonts            | `public/` — `public/favicon.ico`, your own folders such as `public/images/`                          |
| A module's page (article, page…)      | its classes in `theme/src/css` first; see [below](#a-module-s-page)                                  |
| The start page before real content    | `resources/views/demo.blade.php` — delete it with its route when the site has a home page            |

Never edit the packaged theme in `vendor/`: `composer update` brings its fixes, and
`php artisan webx:theme:sync` publishes its built files into `public/themes/`. A view in
`resources/views/components/` sits above every theme layer and wins over it — that is why the
skeleton has no `layout`, `header` or `footer` there.

### CSS knows only tokens

A colour or a font written in a stylesheet is out of reach of the presets and of the owner's
edits in the panel: the page stops repainting wherever it stands. So `theme/src/css` — like the
CSS of the modules and the widgets under it — uses `var(--site-…)` and nothing else:

| Not this                                       | But this                                                                    |
| ---------------------------------------------- | --------------------------------------------------------------------------- |
| `color: #0e6b5c`, `rgb()`, `oklch()`, `white`  | `var(--site-color-accent)` — a role; `transparent`, `currentColor` are fine |
| `font-family: Georgia, serif`                  | `var(--site-font-heading)`; a generic family (`serif`, `monospace`) is fine |
| `var(--site-color-olive)` that the theme lacks | a name of the vocabulary, or add it under `vocabulary` in `tokens.json`     |
| `var(--site-color-border, #ccc)`               | `var(--site-color-border)` — every token has a value                        |
| `@media (min-width: 40rem)`                    | `@container (min-width: 40rem)`                                             |

Three prefixes, each for its own thing — a fourth one is a mistake, and so is mixing them up,
because the check tells them apart by the prefix:

| Prefix     | What it is                                                               | Rule                                                                                            |
| ---------- | ------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------- |
| `--site-*` | a token of the site's vocabulary: its value comes from the theme         | only names the chain knows, never a fallback; a new name goes under `vocabulary` in tokens.json |
| `--webx-*` | a package's own variable, a widget's or a module's (`--webx-slider-gap`) | declared by the package on `--site-*`; a theme overrides it to tune that one widget             |
| `--wx-*`   | the panel and the core components                                        | never on the public site: it is not loaded there, and `var(--wx-…)` is silently empty           |

A block's own variable (`--team-columns`, set inline from a field) is none of these: it lives under
`.b-<slug>` and may have a default.

`tokens.json` is the one place a literal value belongs. `php artisan webx:theme:check` reads the
whole chain — every layer's `tokens.json` and `src/css`, the CSS of the modules and the widgets —
and prints a line per finding (file, line, what, why), exiting non-zero; run it before saying the
theme is done.

### A site without a theme

`php artisan webx:setup --no-theme` writes the layout, the header and the footer into
`resources/views/components/`, styled by about eighty lines of `<style>` in the layout so that the
first pages are readable without a build. The day the real design starts, delete that block,
uncomment the `@vite([...])` line under it and write the design in `resources/css/app.css`. Its
variables are your own; the `--site-*` tokens and the `--wx-*` variables of the panel are not
loaded on such a site.

### A module's page

A module prints its public pages with views of its own, with semantic markup and classes that are
its public contract — listed under «Classes» in the module's `AGENTS.md`. On a site with a theme
the module also brings its **base look**: `resources/theme/*.css` on the site tokens, so a form of
the Inbox or the trail of SEO looks decent under any theme from the first page.
`php artisan webx:theme:sync` writes the base look of every installed module into one file,
`public/themes/modules/<hash>/modules.css`, and `@webxTheme` links it **under** the theme: the
theme restyles a module by writing the same class, later in the cascade.

Four doors, the cheapest first:

1. **Tokens** — a module's base look follows `theme/tokens.json` like everything else.
2. **Style it from `theme/src/css`** by its classes — nothing to publish, nothing to keep up to
   date. Not by tags and not by nesting: the markup inside may change, the classes will not.
3. **Add a piece at an extension point** — where a module's view calls `@webxPart('recipe-card', …)`,
   the theme puts `theme/views/parts/recipe-card.blade.php` and it is printed instead of the
   module's partial (a card customised in the panel still wins).
4. **Change its markup**: copy the views into the site and edit the copy.

```bash
php artisan vendor:publish --tag=webx-blog-views
```

The copies land in `resources/views/vendor/` and win over the package's. **Keep only the file
you changed and delete the rest**: the ones you did not touch then keep arriving fresh with each
release, and the copies you kept get no fixes. The tag is `webx-<module>-views` —
`webx-pages-views`, `webx-blog-views`, `webx-catalog-views` and so on; a module without public
pages has none.

Which modules have their base look today:

| Module             | Its pages and parts                   | Classes                                         | Extension points |
| ------------------ | ------------------------------------- | ----------------------------------------------- | ---------------- |
| `module-inbox`     | the form                              | `wx-form*`                                      | —                |
| `module-seo`       | the trail, the page FAQ, interlinking | `webx-breadcrumbs*`, `webx-faq*`, `webx-links*` | —                |
| `module-blog`      | the feed, a rubric, a tag, an article | `webx-blog-*`                                   | `blog.card`      |
| `module-services`  | the index, a category, a service      | `webx-services-*`                               | `services.card`  |
| `module-menu`      | `<x-webx-menu::menu>`                 | `wx-menu*`                                      | —                |
| `module-events`    | the index, a category, an event       | `wx-events*`, `wx-event*`                       | `events.card`    |
| `module-vacancies` | the index, a vacancy                  | `wx-vacancies*`, `wx-vacancy*`                  | `vacancies.card` |
| `module-press`     | an outlet's page                      | `wx-press-outlet*`, `wx-press-article*`         | —                |

A module whose classes shipped as `wx-*` keeps them: classes are a contract, and renaming them
would break the themes already written. A module that prints a style of its own inline — the
lists of events and vacancies do, for a site without a theme — prints none under a theme, so the
theme's stylesheet has the last word. Recipes and the catalogue come next (the themes spec, §19).

## Blocks

A block type — its fields, its template, its CSS and its script — is made in the panel under
«Blocks» (or by an agent over MCP), and stored in the database, not in the site's files. Its
styles travel with it and reach only the pages that show it.

- Every selector starts with `.b-<slug>` (the type's slug), and sizes respond to `@container`
  rather than `@media`: a bare `h2 {}` or a media query in a block reaches the whole site.
- On a site with a theme, a block's CSS knows only tokens too ([above](#css-knows-only-tokens)):
  saving warns of a literal colour or font, a `--site-*` the theme lacks and a fallback inside one.
- Keep the site's types in git: `php artisan webx:blocks:export` writes `resources/blocks/<slug>.json`,
  `php artisan webx:blocks:import --publish` reads them back on another machine.

### Blocks of the theme

A theme carries block types too, as `blocks/<slug>.json` — the files `webx:blocks:export` writes. A
type of an upper layer replaces the one of a lower layer with the same slug, whole; its schema may
add fields but keeps every field below with the same type, or the content filled in for the lower
type is lost. `php artisan webx:theme:sync` puts the resulting set on the site, and is run by
`webx:setup` and by `composer update` (the site's `post-update-cmd`):

| What the site has                               | What sync does                                                 |
| ----------------------------------------------- | -------------------------------------------------------------- |
| no type by that slug                            | creates it from the layer and publishes it                     |
| the theme's type, not edited here since         | publishes a new version from the file; the same file — nothing |
| the theme's type, edited in the panel since     | keeps the edit and says so; `--force` overwrites it            |
| a type of its own (or a module's) by that slug  | refuses: taking it over would overwrite somebody's work        |
| the blocks demo's type, exactly as it seeded it | refuses and says so; `--adopt=<slug>` hands it to the theme    |
| a type a layer stopped carrying                 | keeps it — pages may stand on it                               |

The blocks demo does not seed a slug the theme carries, so on a new site the theme's `hero` and
`text` are the ones that stand. A site that got the demo before its theme carried them hears it
from sync — «It is the blocks demo's hero, unchanged» — and `php artisan webx:theme:sync
--adopt=hero,text` gives them to the theme: a new version from the theme's file, the demo's in the
history, the pages on them keeping their content. Only a copy nobody changed is adopted; anything
else under the slug stays the site's.

### The six blocks of the default theme

`webx-ui/theme-default` brings the primitives a company page is built of — the same on every new
site, restyled by the tokens, overridden by slug:

| Block        | What                                                                           | Variants                                                                   |
| ------------ | ------------------------------------------------------------------------------ | -------------------------------------------------------------------------- |
| `hero`       | a headline, a line under it, one or two buttons                                | page, tint, accent, over a picture; left or centre; column or edge to edge |
| `text`       | prose in the theme's typography — headings, lists, quotes, tables              | a reading column or the whole column                                       |
| `media-text` | a picture beside a heading, a text and a button                                | picture left or right; page or tint                                        |
| `cards`      | a grid of cards — a photo or an icon, a title, a text, the card as a link      | two, three or four across at most                                          |
| `cta`        | a band asking for one thing, with one or two buttons                           | accent, tint, page with a border                                           |
| `contact`    | the contacts of Settings › Contacts and a form of the Inbox, named by its slug | contacts, a form, or both                                                  |

Every one lays itself out by its container: in a narrow column the cards go one a row and the
picture of `media-text` goes above its words, whatever the window. Their pages, every variant and
a narrow column each, are on the starter site under Kitchen sink › Blocks. Another look of one of
them is the same slug in `theme/blocks/` with every field kept — not a `hero-2`.

A type whose CSS breaks [the tokens rule](#css-knows-only-tokens) is refused by sync, not just
warned of — `webx:theme:check` lists the same findings first. The usual path of a site's own block:
make it in the panel, then `php artisan webx:blocks:export hero --path=theme/blocks` and commit —
the site's look is then all in git, not only in its database.

The rules a block type has to follow are in [Blocks](./blocks.md).

## The admin panel

The panel is the library's own look, the same on every site. A site rebrands it — colours,
density — by overriding the panel's `--wx-*` variables in a stylesheet of its own, and never by
editing the packages.

1. Create `resources/css/admin.css`:

   ```css
   :root {
     --wx-color-primary: #7c3aed;
     --wx-color-primary-hover: #6d28d9;
     --wx-color-primary-active: #5b21b6;
   }
   ```

2. Import it in `resources/js/admin.ts`, **below** the `// /webx:styles` line and outside every
   `// webx:` marker pair: `import '../css/admin.css'`.
3. Build the front end (below).

Only variables that exist do anything: a `var(--wx-…)` with a name that is not in
`node_modules/@webx-ui/tokens/dist/tokens.css` is silently empty. The list, and what each one
means, is in [Theming](./theming.md). Check the result in the dark theme too.

## Safe and not safe

| Safe to edit                                                                        | Never edit                                                                                                         |
| ----------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| `theme/` — tokens, CSS, copies of views; `resources/css/` on a site without a theme | `vendor/` — `composer update` overwrites it; extend by configuration and published views instead                   |
| `resources/views/` — your own views, published module copies in `vendor/` under it  | `node_modules/` — `npm install` overwrites it; rebrand the panel through `--wx-*` instead                          |
| `public/` — images, icons, fonts                                                    | `public/build/` and `public/hot` — written by the build                                                            |
| `resources/js/admin.ts` **outside** the `// webx:` markers                          | the `// webx:imports`, `// webx:styles`, `// webx:modules` regions — `php artisan webx:panel --sync` rewrites them |
| block types, in the panel or over MCP                                               | a copy of a module in the site (a fork) — it stops getting fixes; patch and configure instead                      |
| `AGENTS.md` under `## This project`                                                 | `AGENTS.md` between `<!-- webx:agents -->` markers — rewritten on every `webx:panel --sync`                        |

## Seeing the change

| What changed                                | How to see it                                                                                                                                              |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A view (`.blade.php`), the inline `<style>` | reload the page                                                                                                                                            |
| `theme/src`, `admin.css`, `admin.ts`        | the development server rebuilds as you save: `npm run dev` on a local site, the `vite` container under [Docker](./docker.md). Without it — `npm run build` |
| A block type                                | its preview in the panel, then publish it                                                                                                                  |
| Anything, on the server                     | `npm run build` (in Docker: `docker compose up -d --build`)                                                                                                |

Not changing? `php artisan view:clear` clears cached views, and `php artisan webx:doctor` says
when the built front end is older than its sources ("has changed since the bundle was built").

## Checklist for an agent

1. Decide which look the request is about: the public site, a block, or the panel.
2. Edit only what the "safe" column lists. Never `vendor/`, `node_modules/` or a `// webx:`
   region; never copy a module into the site.
3. To change a module's page, try `theme/src/css` first; publish its views only when the markup must
   change, and delete every published file you did not edit.
4. Use only variables that exist: `--site-*` on the public site, `--wx-*` from `tokens.css` in the
   panel. In a block, prefix every selector with `.b-<slug>` and use `@container`.
5. Show the change: reload, or `npm run build`; run `php artisan webx:doctor` and, on a site with
   a theme, `php artisan webx:theme:check`.
6. Look at a phone width as well as a wide one; for the panel, the dark theme too.
7. Commit what the site owns: `theme/`, `resources/`, `public/` (not `public/build`, `public/themes`), `config/`, and the
   block types after `php artisan webx:blocks:export` (on a site with a theme,
   `--path=theme/blocks`).
