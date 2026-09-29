---
'@webx-ui/module-admin': minor
---

`WxHistory` and the `wx-history` screen node: who changed a record, when, through which door, and
each field as "was → is", with a link from a row made in an import or a bulk action to the whole
run. A tab of any form is one node — `{ "type": "wx-history", "props": { "type": "catalog.product" } }`
— and the editor hosting the screen says which record with `provideHistorySubject({ id })`. Also
`createHistoryApi`, `historyValue` and the `history.*` words.
