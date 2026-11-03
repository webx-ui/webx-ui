---
'@webx-ui/php': minor
---

`delivery-novaposhta`: tracking. The three types track their parcels — one `getStatusDocuments` for the base's batch of up to a hundred, the carrier's codes translated to the base's statuses; «not found» is asked again later, a new code keeps the status and logs a line. The base stops at a final status and marks «received» once. `NOVAPOSHTA_TRACKING` switches it; a read-only key tracks nothing unless it says `true`.
