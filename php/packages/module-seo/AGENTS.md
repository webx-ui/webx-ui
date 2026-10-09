# webx-ui/module-seo

What a page says about itself and where a moved address goes: the `<head>` (title, meta,
canonical, Open Graph, hreflang, JSON-LD), rules written for addresses, redirects and address
normalisation, `robots.txt`, the sitemap, and — when turned on — interlinking blocks and page FAQs.
The section «SEO» of the panel and the MCP tools `seo_*` edit it. Addresses themselves and the
trail a rename leaves are `webx-ui/routing`'s, the site-wide values live in
`webx-ui/module-settings`, the checks and fixes of the site audit in `webx-ui/module-audit` — read
their guides when the question is about one of those.

## What it owns

- **Tables** `seo_urls` (rules for addresses, `SeoUrl`), `seo_redirects` (`SeoRedirect`, 301 or
  302), `seo_meta` (one translated row per entity, `SeoMeta`), `seo_link_blocks` and
  `seo_link_items` (interlinking), `seo_faq_items` (page FAQ). The last three migrate even while
  their feature is off.
- **The head**: `@webxSeo`, `@webxSeo($page)` or `<x-webx-seo::head :for="$page" />`. Values come
  from sources merged field by field, highest first: `UrlRuleSource` (100), `EntitySource` (50,
  `seo_meta` through the `HasSeo` trait), `FallbackSource` (30, the entity's own name, lead and
  picture through `HasSeoFallback::seoFallback()`, or `@webxSeo(fallback: [...])` from a view),
  `DefaultsSource` (10, `settings('seo.*')`). The title template applies to every title except one
  that already names the site; a view never prints `<title>` of its own.
- **Social cards** (`Rendering\SocialTags`): every `og:*`, `article:*` and `twitter:*` line is
  derived from the merged values — never typed. `og:type` from `Contracts\HasOpenGraph` (blog
  articles and recipes are `article`, the rest `website`); `og:image` is the first source picture
  a network can show (an SVG falls through) and is described by `Contracts\SharesImages` — the
  library's record and a 1200×630 variant with `webx-ui/module-media`, the extension without it.
  The card's share fields (`og_title`, `og_description`, `og_image`) are hidden unless
  `WEBX_SEO_OG_FIELDS=true`; stored values are still honoured.
- **Components** `<x-webx-seo::breadcrumbs />` (the same list as the `BreadcrumbList`),
  `<x-webx-seo::links />`, `<x-webx-seo::faq />`; views `webx-seo::head`, `breadcrumbs`, `links`,
  `faq`. The last two print nothing while their feature is off.
- **Global middleware** `NormaliseAddress` then `RedirectRequests` (aliases `webx.normalise`,
  `webx.redirects`): they run before routing, so they answer addresses that have no route.
  The resolver's spelling (routing's `Contracts\Spelling`) is bound to the same settings
  (`SeoSpelling`): an address the registry answers takes one 301, never two, and «keep as it is»
  is obeyed. «With a slash» is not offered: every link the site prints is written without one.
- **Organization** markup on every page: name, logo and `sameAs` from the SEO tab, `telephone`
  (E.164), `email`, `address` from the Contacts tab of `module-settings`, and its networks merged
  into `sameAs`; with coordinates or opening hours on that tab it is a `LocalBusiness` with `geo`
  and `openingHoursSpecification` (the special dates of the next two months included). There are
  no phone or address fields of SEO's own.
- **Public routes** `/robots.txt` (the `seo.robots-txt` setting; 404 while it is empty),
  `/sitemap.xml` and `/sitemap-{file}.xml`, one file per address type.
- **Panel** module `seo` (group `system`); permissions `seo.view`, `seo.manage`; API under
  `/api/cms/seo` (`urls`, `redirects`, `aliases` read only, `test-url`, `sitemap`, and `links`,
  `faq` only with the feature on).
- **Screen patches**: an SEO tab on `settings.index` (node ids `seo`, `seo-card`, `default-og`,
  `title-template`, `home-crumb`, `robots-txt`, `org-name`, `org-logo`, `org-socials`,
  `seo-addresses`, `normalise-host`, `normalise-https`, `normalise-slashes`, `normalise-index`,
  `normalise-trailing`, `normalise-case`, plus `links-heading`), and the card `seo-card` with the
  field `seo-fields` (type `wx-seo`) in place of a `seo-placeholder` node on `pages.form`,
  `blog.article-form`, `services.form`, `recipes.form`, `events.form`, `vacancies.form` and the
  category forms. A screen that is not registered is simply not patched.
- **MCP** tools `seo_urls_list`, `seo_urls_get`, `seo_urls_set`, `seo_urls_delete`,
  `seo_test_url`, `seo_sitemap_status`, `seo_redirects_list`, `seo_redirects_set`,
  `seo_redirects_delete`, `seo_import_redirects`; with
  interlinking `seo_links_list`, `seo_links_get`, `seo_links_set`, `seo_links_delete`,
  `seo_links_import`, `seo_links_heading`; with page FAQ `seo_faq_get`, `seo_faq_set`,
  `seo_faq_import`. Scopes `seo:read`, `seo:write`.
- **Command** `php artisan webx:seo:sitemap`. Also registered: the field type `wx-seo`, audit
  checks and fixes when `webx-ui/module-audit` is installed, demo content (`resources/demo`).

## Change it without forking

| You want                                          | Do this                                                                                                                                                        |
| ------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| The head not to print a part the site writes      | `php artisan vendor:publish --tag=webx-seo-config`, set that key of `print` to `false`                                                                         |
| A default title pattern                           | `title_template` in `config/webx-seo.php` (`{title}`, `{site}`); editors use the setting                                                                       |
| Titles cut to the limits                          | `'trim' => true`; the limits are `limits.title`, `limits.description`, `limits.keywords`                                                                       |
| Keep more of the query in the self-canonical      | `canonical.query` (default `['page']`); `canonical.self` off for no self-canonical                                                                             |
| Turn interlinking or page FAQ on                  | `WEBX_SEO_LINKS=true`, `WEBX_SEO_FAQ=true` — a developer's decision, not an editor's                                                                           |
| No redirects, no sitemap, own sitemap             | `WEBX_SEO_REDIRECTS=false`, `WEBX_SEO_SITEMAP=false`; `sitemap.per_file`, `WEBX_SEO_SITEMAP_TTL`                                                               |
| Own `robots.txt` route                            | `robots_txt.enabled` to `false` in the published config                                                                                                        |
| One address per page (www, https, slash, case)    | the `normalise-*` settings on the SEO tab; slashes, case and the trailing slash start on (the registry's spelling), the rest off; one 301 for every difference |
| Restyle crumbs, links, FAQ or the head            | `php artisan vendor:publish --tag=webx-seo-views`, keep only the files you change                                                                              |
| Other words in the panel                          | `php artisan vendor:publish --tag=webx-seo-lang`                                                                                                               |
| The SEO card on your own entity                   | `use HasSeo;` on the model, a patch putting a `wx-seo` node on its screen                                                                                      |
| A page's kind for social networks (`article`)     | implement `HasOpenGraph` on the model: `openGraphType()`, `openGraphProperties($locale)` (`article:*` lines)                                                   |
| Share fields back in the SEO card                 | `WEBX_SEO_OG_FIELDS=true` (`og.panel_fields`)                                                                                                                  |
| `og:locale` other than the guess (`en` → `en_US`) | `og.locales` in the published config: `['en' => 'en_GB']`                                                                                                      |
| A title, lead and picture without a card          | implement `HasSeoFallback` (`SeoData::fallback(...)`); a view with no entity: `@webxSeo(fallback: ['title' => ...])`                                           |
| Breadcrumbs or JSON-LD from an entity             | implement `HasBreadcrumbs` (list of `Crumb`) or `HasStructuredData` on the model                                                                               |
| JSON-LD for one response (a list on this page)    | `app(Seo::class)->push([...])` in the handler before the view renders                                                                                          |
| SEO values from somewhere else                    | implement `SeoSource`, `app(SeoSources::class)->register(...)` in a provider                                                                                   |
| Sitemap addresses no registry row stands behind   | implement `SitemapSource`, `app(SitemapSources::class)->register(...)`                                                                                         |
| A redirecting type out of the sitemap             | implement `WebxUi\Routing\Contracts\NotAPage` on the handler you bound over the module's                                                                       |
| A field on the SEO tab                            | a patch: `Screens::extend('settings.index', [...])` against the ids above                                                                                      |

## Do not

- Do not edit anything in `vendor/webx-ui/module-seo` or copy it into the site. Every row above
  is the supported way; if none fits, the package is missing a seam — say so.
- Do not move the redirect middleware into the `web` group: an address with no route never
  reaches a group, so the table would only fire for pages that still exist. It is global already.
- Do not print your own `<title>`, description or canonical beside `@webxSeo`: the page gets two
  of each. Turn that part off in `print` instead.
- Do not type `Article`, `Product` or `BreadcrumbList` JSON-LD into a rule by hand — it drifts
  from the page. Let the entity generate it (`HasStructuredData`, `HasBreadcrumbs`); the JSON-LD
  field of a rule is for one-off exceptions.
- Do not leave a `public/robots.txt` and expect the `seo.robots-txt` setting to work: the web
  server hands the file over before Laravel is asked. Delete the file or keep it and empty the
  setting.
- Do not write `seo_urls` or `seo_redirects` with SQL: the compiled rules in the cache are
  thrown away by the models' save and delete, and a raw write leaves the site matching the old
  list. Go through the panel, the API or `seo_urls_set` / `seo_redirects_set` and
  `seo_urls_delete` / `seo_redirects_delete`.
- Do not mark every address of a redirecting type `noindex` to get it out of the sitemap: the
  handler that redirects implements `NotAPage`, and the whole type goes. A list of types in config
  is not offered on purpose — it would drift from the binding.
- Do not add a redirect over a live address to "fix" it: it shadows the page. `seo_test_url`
  says what `webx-ui/routing` holds there; a renamed entity already left a 301 alias.

## Check your work

- `seo_test_url` (or `POST /api/cms/seo/test-url`) with the address — what the head ends up
  saying and which source each part came from. The first question for "why is this title wrong".
- View the page source: one `<title>`, one canonical, the JSON-LD blocks you expect.
- `php artisan webx:seo:sitemap` builds the map and prints the count per file; `seo_sitemap_status`
  says the same plus how many addresses were left out for noindex or a foreign canonical, and
  under `excluded_types` the address types left out whole because their handler is `NotAPage`.
- Open an old address in the browser and check it answers 301 to the new one, in one hop.
- With MCP: every `*_set` and `*_import` tool takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory — sources, matching (`exact`, `mask`, `regex`), the API.
- Guide: https://webx-ui.github.io/webx-ui/guide/seo
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_SEO.md
- Site audit: https://webx-ui.github.io/webx-ui/guide/audit
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
