# @webx-ui/module-audit

## 0.5.3

### Patch Changes

- e42643b: Every picture the panel shows small opens large: a click on it opens `WxLightbox` over the whole set it stands in, on the full picture and in the panel's language. `@webx-ui/module-admin` gains `WxPreviewThumb` (a thumbnail that is a button opening the gallery, keeping its click from the row) and `useLightbox()` (`open`, `labels`, and `click`, which leaves Ctrl and the middle button to a link); the words move from `webx-media::lightbox.*` to `webx-admin::lightbox.*` and gain `play` and `view`. Wired in: the product gallery (with its videos), the gallery and file fields and the picture field of forms — whose picture now opens large, swapping it is a button of the bar — the library (its double click and «View»), the covers of the lists of products, articles, events, services and recipes, the banners of a place, the pictures of property values and their dialog, the share picture of the SEO card and the pictures of an audited page. The panel rows of events and recipes carry `cover.url`, and those of banners `image`, for the full picture.
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
  - @webx-ui/module-admin@0.33.0
  - @webx-ui/core@0.45.0
  - @webx-ui/schema@0.9.5

## 0.5.2

### Patch Changes

- 9dcb323: `WxDescriptions` with `layout="vertical"`: a label sits 2px over its value and the next pair stands
  12, 16 or 24px off (`sm`, `md`, `lg`) instead of the same few pixels either way, so the pairs no
  longer run together. In the Inbox, whether a submission was notified, and to whom, is one more pair
  of its details, a row per address; the audit's "found / why / fix" is a `WxDescriptions` too.
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
  - @webx-ui/module-admin@0.32.0
  - @webx-ui/core@0.44.0
  - @webx-ui/schema@0.9.4

## 0.5.1

### Patch Changes

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-admin@0.31.0

## 0.5.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0
  - @webx-ui/schema@0.9.3

## 0.4.7

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0
  - @webx-ui/schema@0.9.2

## 0.4.6

### Patch Changes

- Updated dependencies [152a118]
  - @webx-ui/module-admin@0.28.0

## 0.4.5

### Patch Changes

- Updated dependencies [be30498]
  - @webx-ui/module-admin@0.27.0

## 0.4.4

### Patch Changes

- Updated dependencies [e107a30]
- Updated dependencies [e107a30]
  - @webx-ui/module-admin@0.26.1
  - @webx-ui/core@0.41.0
  - @webx-ui/schema@0.9.1

## 0.4.3

### Patch Changes

- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
  - @webx-ui/module-admin@0.26.0
  - @webx-ui/core@0.40.2

## 0.4.2

### Patch Changes

- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
  - @webx-ui/core@0.40.0
  - @webx-ui/schema@0.9.0
  - @webx-ui/module-admin@0.25.0

## 0.4.1

### Patch Changes

- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
  - @webx-ui/core@0.39.0
  - @webx-ui/module-admin@0.24.0
  - @webx-ui/schema@0.8.1

## 0.4.0

### Minor Changes

- 52f4e14: The runs screen clears every run with everything it found, behind a warning — for whoever manages the section; the hiding rules and the settings stay.

### Patch Changes

- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/schema@0.8.0
  - @webx-ui/module-admin@0.23.5

## 0.3.0

### Minor Changes

- a814314: The audit keeps every heading of a page in order, and the page card shows them as a tree on a «Headings» tab: indented by level, a skipped level drawn where it should have been, and above the tree what breaks the usual order (no H1, several, not first, skipped levels, empty headings).

  Two new page checks join the findings: `headings.h1_not_first` (another heading comes before the H1) and `headings.empty` (a heading with no text).

  The overview of a page has a «Headings» line: whether the order is fine, how many there are, what breaks the rules, and a link to the map.

### Patch Changes

- a814314: The audit shows how an external link is written: its `target` and `rel` (`_blank`, `nofollow`,
  `noopener`…) as badges — an «Attributes» column in the external redirect and broken link findings,
  and beside the address on «Outgoing». When the pages write one link differently, each page says
  its own. `_blank` without `noopener` or `noreferrer` is marked, with why in the tooltip.
- a814314: The audit's page card reads at a glance. «Overview» opens with the page's numbers as tiles — code,
  size in KB, first byte, answer, words, links, pictures — then sections with an edge each: server
  answer, crawl, markup, preview and structured data, headers; the Open Graph and Twitter keys line
  up in a column of their own. «Findings» are cards with the count in the head, like the findings
  list, and short columns (kind, KB, type) keep narrow.
- a814314: The audit's address lists read at a glance. A finding is a card: its page, the «New» badge and the
  count as a counter on one line, the table under it. Columns take their width from their type, so
  the tables of one check line up. A redirect row reads from → to, the target emphasised and a
  trailing slash, `www.` or `http → https` named; 302 is coloured apart from 301. Long addresses
  keep to one line, losing their middle, with the whole in the panel's tooltip. A redirect check
  can be read by link — one card per link with the pages it is on. On «Outgoing» a host's links are
  grouped by where they lead, every page counted (`targets` in the hosts answer), and a card's
  «Show all» folds back with «Collapse».
- a814314: The audit's settings can be left again: «Back» beside «Save» returns to the overview, whose tab
  was lit and so could not be clicked to.
- a814314: Every answer code in the audit says what it means: hover a 403, a 301 or «No answer» for its name
  and what to do about it — in the findings, on «Outgoing», in the page list and the page card. A
  code without a line of its own is explained by its class.
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
  - @webx-ui/core@0.37.1
  - @webx-ui/module-admin@0.23.4
  - @webx-ui/schema@0.7.4

## 0.2.1

### Patch Changes

- 17c2dfd: Under a host on «Outgoing», the broken links come first and carry their badge, «No answer»
  included. The row counted them, but the fifty links shown were the first fifty found, so a broken
  picture deep in the site never came into view.
- 17c2dfd: «Content searched» on the audit overview has a «What this means» popover: besides the crawl, the
  audit reads the text modules keep in the database; green modules hand it over, orange ones are
  installed but not searched yet — their pages are still crawled, their drafts and hidden fields are
  not, and a fix cannot reach them.

## 0.2.0

### Minor Changes

- f54ac14: Audit: the health is now the share of pages without errors, minus 10 per site-wide error check and 2 per warning check (20 at most), with its parts in `counts.health_parts` and on the Overview; a full run seeds the home of every prefixed language; findings that count elements (`images.alt`, `a11y.button_name`, `a11y.form_label`) quote up to five of them as a `code` cell.
- 3dfe40a: The console's `webx:catalog:index --rebuild` and the panel's «Rebuild» share one lock: the second
  one is refused, and the search index page holds its button back while the console rebuilds.
  `WxTable` keeps its heading row in sight while the page scrolls past a long table (`stickyHeader`,
  on by default). In the audit's page card, a picture that answered shows its thumbnail and opens
  full size; the stand's, unchecked and broken ones keep the placeholder and are not fetched.

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2
  - @webx-ui/schema@0.7.3

## 0.1.0

### Minor Changes

- 11088ba: A site audit, A1: `webx-ui/module-audit` and `@webx-ui/module-audit` — the «Audit» section in the system group with an overview and findings, runs in the queue a piece at a time, the production config and host checks, and every absolute address in the database classified, so links to a development stand are found in published records and drafts alike. `webx:audit:run --quick --fail-on=error` is the deploy step. Pages and regions hand their content to the audit when it is installed.
- 37a4550: A site audit, A2: a full run crawls the site — the home page, the sitemap and the address registry first, then breadth first, two requests at a time, a piece per queued job — and keeps a snapshot of every page and every address it points at. Fifty checks read that snapshot: title, description, headings, canonical, the language, viewport, Open Graph, thin and duplicate content, address format, speed, broken and empty links, mixed content, insecure forms, pictures without `alt`, accessibility, depth, orphans and dead ends, and the outgoing hosts — a development stand linked from a page above all. The «Pages» screen lists every crawled address with any field as a column, a filter on any field and a CSV export; a row opens the page's card with its answer, its head, its problems and its links in and out.
- d96c9cf: A site audit, A3: robots.txt and the sitemap are read by every run, the quick one too — a file that closes the whole site or its CSS and JS, lines search engines skip, a sitemap that does not answer or parse, files over the protocol's limits, a `lastmod` that says nothing. A full run adds what needs the crawl: addresses of the sitemap that are redirects, errors or closed pages, indexable pages missing from it, chains, loops and temporary redirects, internal links to redirects, hreflang without a link back, without `x-default` or with a code search engines do not read, a `lang` that disagrees with hreflang, and JSON-LD that does not parse or lacks what Product, Article, Event, JobPosting, FAQPage and BreadcrumbList need. A new stage asks what the pages load and where their external links lead — `HEAD`, `GET` when the server does not do `HEAD`, once per run under a limit of its own — for broken, heavy and old-format pictures, a broken or small Open Graph picture, an icon that does not open, and external links that are broken or redirect. The page's card gets four tabs: pictures, CSS and JS with what each answered, and the structured data with what its types lack.
- 3236f0a: A site audit, A4: fixes with a preview. A finding whose check has a fix gets a «Fix» button that shows what would change — the records and fields with the number of replacements, or a setting before and after — and changes nothing until «Apply»; the finding then waits for the next run. `audit.replace-host` puts this site's address in place of a development stand's in the very field it was found in, through the module's own model, so the history journal keeps every change. SEO brings one-redirect address normalisation (main mirror, https, double slashes, index files, the trailing slash, lower case — each a setting, all off until turned on) with the fixes that turn each part on, collapsing redirect chains and the `Sitemap:` line in robots.txt, and checks its own tables; the address registry, the media library, the catalogue and the menus bring checks of their own. Agents get `audit_run`, `audit_status`, `audit_issues`, `audit_pages`, `audit_page_get`, `audit_hosts` and `audit_fix` with `dry_run`, and the catalogue of checks as a resource.
- 34c346f: A site audit, A5: history, hiding and settings. «Hide» on a finding asks which addresses — this one, a mask like `/search/**`, or the whole check — and why, says first how many findings it would hide, and from then on they stop counting in every run; «Hidden» in the filter lists them with the reason and «Show again». «Runs» is the history: tick one run to compare it with the one before, two to compare them — new, persisting and fixed, check by check. «Outgoing» lists every host the site points at in its pages and its database, development stands on top, each opening into the pages and the records with «Open in the editor». The page's card gets «Recheck», which asks that one address again and says what of its findings is fixed, and «Export». The section has its own settings screen — the address, the stands, the crawl's limits and excluded paths, thresholds, a nightly run (off by default) and how many runs to keep; the «Audit» tab of the site's settings moved there. Agents get `audit_ignore` and `audit_run` with a list of addresses.

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1
  - @webx-ui/schema@0.7.2
