---
'@webx-ui/php': patch
---

Services, recipes, reviews, FAQ and press: the panel's lists, filters, names, MCP catalogues, and a bare string written into a translated field use the site's content language (`Locales::content()`), not the interface language — a panel read in a language the site has no content in showed `#id` instead of the question.
