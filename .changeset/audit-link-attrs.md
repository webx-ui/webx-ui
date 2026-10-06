---
'@webx-ui/module-audit': patch
'@webx-ui/php': patch
---

The audit shows how an external link is written: its `target` and `rel` (`_blank`, `nofollow`,
`noopener`…) as badges — an «Attributes» column in the external redirect and broken link findings,
and beside the address on «Outgoing». When the pages write one link differently, each page says
its own. `_blank` without `noopener` or `noreferrer` is marked, with why in the tooltip.
