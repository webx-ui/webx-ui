---
'@webx-ui/php': patch
---

`webx-ui/module-blocks`: the preview answers in the language the site answers in.

Its route ran through `web` alone, and the language of a page is not decided there — it is
decided by a middleware the site puts on the route that answers for a page. So the preview came
out in the application's default: an editor writing a Russian page was shown it in English, with
every localized thing in it — the words of a block, the labels of a form standing on it — in the
wrong language, while the published page was right. The default is now `['web', 'webx.locale']`,
which is what the setting already said it was for.

A site that published `config/webx-blocks.php` keeps its own copy of that list and has to add
`'webx.locale'` to `preview.middleware` itself.
