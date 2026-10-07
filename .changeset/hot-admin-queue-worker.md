---
'@webx-ui/php': patch
---

`webx:doctor` and the audit see a database queue nobody works through: jobs due for more than five minutes and not taken make doctor warn (`Queue`) and the audit report `config.queue_worker` (an error on a live site), with the fix — a worker, `queue:work --stop-when-empty` on the schedule, or `sync`. A missing jobs table fails the doctor.
