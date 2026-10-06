---
'@webx-ui/php': patch
---

A new page, article, event, service, recipe, vacancy, tag or menu is named in the site's main
language, never in the panel's: a Russian panel over an English-only site used to store the title
and slug under `ru`, leaving a page with no address in any language the site has. A plain string
written to a translated attribute now lands in the content language (`Locales::content()`), and
an agent's text keyed by a language the site is not published in is refused with the list of the
site's languages.
