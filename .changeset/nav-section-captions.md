---
'@webx-ui/module-admin': minor
'@webx-ui/php': minor
---

A navigation group can split its entries with captions: the group declares `sections` in `webx-admin.groups`, a module stands under one by implementing `HasNavSection`, and the panel draws each caption with `WxMenuGroup`. The catalog group gets a «Dictionaries» caption for its reference lists, and «Categories» now comes before «Products».
