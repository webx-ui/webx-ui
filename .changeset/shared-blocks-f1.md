---
'@webx-ui/php': minor
---

`webx-ui/module-blocks`: shared blocks, the server half (F1 of `WEBX_UI_BLOCK_FRAGMENTS.md`). A region declared with `'fragment' => true` is printed not by the layout but where it is placed: a template prints its tag, a page holds the offered block «Shared block» (`webx:blocks:offered --install --module=blocks`, field `wx-fragment`, which takes only a shared block). A shared block that would end up inside itself is refused at saving and publishing with the path, and a cycle published before the check is left empty on the site. `POST /api/cms/regions/{name}/copy` gives the published tree with new keys for «make it this page's own»; the region preview shows the stage with a notice when the page does not hold the shared block; the region list and `blocks_regions` say `fragment` and `used_on`. `webx-ui/module-admin`: the doctor no longer looks for a shared block in the layout — `module-blocks` checks whether any content places it.
