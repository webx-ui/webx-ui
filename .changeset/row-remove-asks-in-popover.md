---
'@webx-ui/core': minor
'@webx-ui/module-admin': patch
'@webx-ui/module-banners': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-catalog': patch
'@webx-ui/module-catalog-landings': patch
'@webx-ui/module-catalog-properties': patch
'@webx-ui/module-inbox': patch
'@webx-ui/module-notifications': patch
'@webx-ui/module-seo': patch
'@webx-ui/php': patch
---

Removing one row of a list asks first, in a small popover by the button («Delete?» — «Yes» / «Cancel»), instead of removing at once. `WxAction` gains `confirm`, `confirmText` and `cancelText`: with a question set, `click` comes only on a yes. `WxPopconfirm` puts the focus on Cancel, goes ahead on Enter and reads the question out as the panel's description. The panel's words are `useRemoveConfirm()` of `@webx-ui/module-admin` (`webx-admin::confirm.*`, ten languages); labels and categories of a record, related records, banner layers, landing products and sets, category properties, intervals, inbox choices, notification chats and emails, SEO links, FAQ rows and the mapping and column rows of an exchange profile ask with it. Deleting a whole record keeps its dialog.
