---
'@webx-ui/php': minor
'@webx-ui/module-blog': patch
---

`webx-ui/module-blog`: author pages, AU1 — the site half (§19 of the blog spec). With `WEBX_BLOG_AUTHORS=true`, an administrator who writes may have a profile (`blog_authors`: the name a reader sees, a slug, a role, a photo, a bio) and, when the profile asks for it, a page at `{prefix}/author/{slug}` with all of their articles; the byline links there and the article's `author` is that `Person` with its address. Off — the default — or with the profile closed, nothing changes: the account's name, no link, a 404 at the address. `ProfilePage` markup, the sitemap through the registry, the panel on the site, a link source for menus.
