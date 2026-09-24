---
'@webx-ui/php': patch
---

A media value saved together with its address — a block's sample is — no longer hands the template that old address: `resolve()` keeps only the key and the captions and works the address out again, so a site moved to https stops asking for its pictures over http.
