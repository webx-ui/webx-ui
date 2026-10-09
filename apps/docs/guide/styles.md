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

### A site without a theme

`php artisan webx:setup --no-theme` writes the layout, the header and the footer into
`resources/views/components/`, styled by about eighty lines of `<style>` in the layout so that the
first pages are readable without a build. The day the real design starts, delete that block,
uncomment the `@vite([...])` line under it and write the design in `resources/css/app.css`. Its
variables are your own; the `--site-*` tokens and the `--wx-*` variables of the panel are not
loaded on such a site.

### A module's page

A module prints its public pages with views of its own, and they carry semantic markup and a few
`wx-<module>` classes. Two ways to change one, the first one first:

1. **Style it from `theme/src/css`** by its class — nothing to publish, nothing to keep
   up to date.
2. **Change its markup**: copy the views into the site and edit the copy.

```bash
php artisan vendor:publish --tag=webx-blog-views
```

The copies land in `resources/views/vendor/` and win over the package's. **Keep only the file
you changed and delete the rest**: the ones you did not touch then keep arriving fresh with each
release. The tag is `webx-<module>-views` — `webx-pages-views`, `webx-blog-views`,
`webx-catalog-views` and so on; a module without public pages has none.

## Blocks

A block type — its fields, its template, its CSS and its script — is made in the panel under
«Blocks» (or by an agent over MCP), and stored in the database, not in the site's files. Its
styles travel with it and reach only the pages that show it.

- Every selector starts with `.b-<slug>` (the type's slug), and sizes respond to `@container`
  rather than `@media`: a bare `h2 {}` or a media query in a block reaches the whole site.
- Keep the site's types in git: `php artisan webx:blocks:export` writes `resources/blocks/<slug>.json`,
  `php artisan webx:blocks:import --publish` reads them back on another machine.

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
5. Show the change: reload, or `npm run build`; run `php artisan webx:doctor`.
6. Look at a phone width as well as a wide one; for the panel, the dark theme too.
7. Commit what the site owns: `theme/`, `resources/`, `public/` (not `public/build`, `public/themes`), `config/`, and the
   block types after `php artisan webx:blocks:export`.
