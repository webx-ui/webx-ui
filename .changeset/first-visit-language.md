---
'@webx-ui/php': minor
---

Localization: the language of the first visit. `WEBX_LOCALE_FIRST_VISIT=uk` (or `header`, the browser's Accept-Language) sends a person's first visit to a page of another language to the same page in that one — a 302, `private, no-store` — and keeps the cookie `webx_lang`; with it nobody is sent again. Robots, link previews and the site audit are never sent (`first_visit.bots`), nor non-GET, JSON, prefetch, the panel, sitemaps, feeds, `?no_redirect`, or a page that has only a home page in that language. New contract `Contracts\PageInLanguage`, bound by widgets to the language switcher's links; while it is on, the switcher's links carry `?webx_lang=<code>` and `rel="nofollow"`, and following one keeps the choice. `webx:doctor` warns when it is on but cannot work. Off by default: nothing changes on a site that does not set it.
