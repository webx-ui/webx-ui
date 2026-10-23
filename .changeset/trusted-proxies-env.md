---
'@webx-ui/php': patch
---

`webx-ui/site`: whom the site believes `X-Forwarded-*` from (https and the visitor's IP) moves from `bootstrap/app.php` to `config/trustedproxy.php`. The default is unchanged in spirit and a little wider on loopback — `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `::1`, `fc00::/7`; a public address is never believed. A proxy elsewhere (a CDN) is named in `TRUSTED_PROXIES` in `.env`, comma-separated, and replaces the default. The skeleton's `TrustedProxiesTest` now checks the IP as well as the scheme, the env list, and asks with a full `http://` address — a relative path was resolved against `APP_URL`, so on a site whose `.env` said `https://` the request was secure before any proxy was asked and the public-address test failed for that reason alone.

`webx:doctor` warns when every address is trusted (`*`, `**`, `0.0.0.0/0`, `::/0`), from the config or from `trustProxies(at: ...)` in `bootstrap/app.php`.

`module-seo`: the address normalisation and the redirects table, both global middleware, let a request through when their tables are not there (before `migrate`, a site's test without `RefreshDatabase`) instead of answering 500 on every page.

**Existing sites** keep their own `bootstrap/app.php`, and a release does not change it. Their `trustProxies(at: [...private networks...])` is already safe; to take the env-driven list: copy `config/trustedproxy.php` from the skeleton, remove the `trustProxies(at: ...)` call from `bootstrap/app.php` (it wins over the config), and copy `tests/Feature/TrustedProxiesTest.php` (its `RefreshDatabase` can go). A site that trusts `'*'` there should replace it with its proxy's addresses. Then `php artisan config:clear` and `php artisan webx:doctor`.
