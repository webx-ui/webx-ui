---
'@webx-ui/php': patch
---

`Ordering::move` with part of a list: the rows named trade the places they hold among
themselves, and every row not named stays where it is (`[4, 2]` out of four gives 1, 4, 3, 2, no
longer 1, 4, 2, 3). Tied places are made a run first. The `*_reorder` tool descriptions say so.
`CategoryTools` takes the module id, and a refusal names the list tool as the agent sees it
(`tariff_groups_list`, not `groups_list`).
