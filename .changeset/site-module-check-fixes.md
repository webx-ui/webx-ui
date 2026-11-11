---
'@webx-ui/php': patch
---

`module-admin`: what the check of a site module on a copy of the lab found. The site's `AGENTS.md` links a site module by its folder (`modules/offices/AGENTS.md`) when Composer installed it through its symlink in `vendor/site/` — the `install-path` Composer really writes. The scaffold of `webx:make-module --local` writes "an office" where English wants it, and its `PerformanceTest` no longer fails on a site with the SEO module: the repeated read of `cms_settings` inside the test's transaction is the cache's, not the section's N+1.
