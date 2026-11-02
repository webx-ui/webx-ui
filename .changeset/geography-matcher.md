---
'@webx-ui/php': minor
---

`webx-ui/geography`: `Matcher` binds a carrier's localities to the shared geography (delivery spec §4.3–§4.4) — a settled binding is kept (a manual one never moves), the region by its key or an alias, then one locality of the same region, type and name (`auto_exact`), a namesake or another type within `match_radius_km` (`auto_coords`), else the binding queue with the reason (`region_unknown`, `ambiguous`, `kind_mismatch`, `not_found`) and the candidates with distances. The leading carrier of a country (`LeadingCarriers`, the first to sync unless set) brings in the regions and localities nobody has; a hand-made or imported locality is bound to, not duplicated, and a field set by hand is never written over. The carrier's spellings become locality and region aliases; `bind()` and `create()` are the queue's actions. A page of records is matched in four queries.
