---
'@webx-ui/php': minor
---

A site with no public part. `webx:setup` sees when none of the chosen modules shows a visitor anything — `admins`, `settings` and `tasks` are such — and makes the site private: `/` leads to the panel instead of the placeholder page, no theme is installed, and `WEBX_PUBLIC=false` keeps the whole site out of search engines (`robots.txt` disallows everything and every response says `noindex`, a 404 included). `webx-ui/module-tasks` gets its guide.
