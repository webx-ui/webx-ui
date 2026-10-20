---
'@webx-ui/core': patch
'@webx-ui/module-inbox': patch
'@webx-ui/module-audit': patch
---

`WxDescriptions` with `layout="vertical"`: a label sits 2px over its value and the next pair stands
12, 16 or 24px off (`sm`, `md`, `lg`) instead of the same few pixels either way, so the pairs no
longer run together. In the Inbox, whether a submission was notified, and to whom, is one more pair
of its details, a row per address; the audit's "found / why / fix" is a `WxDescriptions` too.
