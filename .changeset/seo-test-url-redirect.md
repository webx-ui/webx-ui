---
'@webx-ui/php': patch
---

`seo_test_url` over MCP answers what the panel's test-url does: `redirect` (the one that catches the
address, with `leads_to` — where it sends this very address, `$1` filled in) and `route` (what the
address registry holds there). Both now come from one `AddressReport`, and the redirect is found by
the same `RedirectFinder` the middleware uses, so a looping redirect the site steps over is not
reported as the one that catches the address.
