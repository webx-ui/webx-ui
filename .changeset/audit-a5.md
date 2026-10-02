---
'@webx-ui/module-audit': minor
'@webx-ui/php': minor
---

A site audit, A5: history, hiding and settings. «Hide» on a finding asks which addresses — this one, a mask like `/search/**`, or the whole check — and why, says first how many findings it would hide, and from then on they stop counting in every run; «Hidden» in the filter lists them with the reason and «Show again». «Runs» is the history: tick one run to compare it with the one before, two to compare them — new, persisting and fixed, check by check. «Outgoing» lists every host the site points at in its pages and its database, development stands on top, each opening into the pages and the records with «Open in the editor». The page's card gets «Recheck», which asks that one address again and says what of its findings is fixed, and «Export». The section has its own settings screen — the address, the stands, the crawl's limits and excluded paths, thresholds, a nightly run (off by default) and how many runs to keep; the «Audit» tab of the site's settings moved there. Agents get `audit_ignore` and `audit_run` with a list of addresses.
