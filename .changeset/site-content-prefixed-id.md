---
'@webx-ui/php': minor
---

theme-default, compositions, widgets, module-orders, module-users: the `<main>` of every public layout is `id="site-content"`, not `id="content"`, so a browser extension's stylesheet with a generic `#content { width: 460px }` no longer narrows the page. "Skip to content" (`site-skip`, the `webx-header` `skip` default) leads to `#site-content`; a patch that sets the main's `id` is told so by the patcher. The public markup holds only prefixed ids and classes (`site-*`, `webx-*`, `wx-*`) — THEMES §10.1.

**Behaviour change for themes:** a site theme that styles or links `#content` — its own `components/skip-link.blade.php`, a `<x-webx-header skip="#content">`, CSS or JS with `#content` / `getElementById('content')` — moves to `site-content`. Find it with `grep -rnE "#content\b|id=\"content\"|getElementById\('content'\)" resources/ themes/`. No block type needs re-importing: none of the shipped types names `#content`; a type a site wrote itself with such a link is re-imported after the edit (`php artisan webx:blocks:import <type> --replace=<type> --publish`).
