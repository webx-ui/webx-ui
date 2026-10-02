---
'@webx-ui/module-audit': minor
'@webx-ui/php': minor
---

A site audit, A2: a full run crawls the site — the home page, the sitemap and the address registry first, then breadth first, two requests at a time, a piece per queued job — and keeps a snapshot of every page and every address it points at. Fifty checks read that snapshot: title, description, headings, canonical, the language, viewport, Open Graph, thin and duplicate content, address format, speed, broken and empty links, mixed content, insecure forms, pictures without `alt`, accessibility, depth, orphans and dead ends, and the outgoing hosts — a development stand linked from a page above all. The «Pages» screen lists every crawled address with any field as a column, a filter on any field and a CSV export; a row opens the page's card with its answer, its head, its problems and its links in and out.
