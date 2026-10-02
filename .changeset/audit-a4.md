---
'@webx-ui/module-audit': minor
'@webx-ui/php': minor
---

A site audit, A4: fixes with a preview. A finding whose check has a fix gets a «Fix» button that shows what would change — the records and fields with the number of replacements, or a setting before and after — and changes nothing until «Apply»; the finding then waits for the next run. `audit.replace-host` puts this site's address in place of a development stand's in the very field it was found in, through the module's own model, so the history journal keeps every change. SEO brings one-redirect address normalisation (main mirror, https, double slashes, index files, the trailing slash, lower case — each a setting, all off until turned on) with the fixes that turn each part on, collapsing redirect chains and the `Sitemap:` line in robots.txt, and checks its own tables; the address registry, the media library, the catalogue and the menus bring checks of their own. Agents get `audit_run`, `audit_status`, `audit_issues`, `audit_pages`, `audit_page_get`, `audit_hosts` and `audit_fix` with `dry_run`, and the catalogue of checks as a resource.
