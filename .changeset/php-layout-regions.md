---
'@webx-ui/php': minor
---

Layout regions in `webx-ui/module-blocks`: `<x-webx-blocks::region name="header"
fallback="components.header" />` in the site's layout, the regions declared in
`webx-blocks.regions`, and the header or footer an editor publishes as a tree of blocks printed in
place of the markup from code — which stays the fallback while the region is empty, unpublished,
or has a block that throws. The region brings its own stylesheet and script; its blocks see
`$entity` (the page the visitor is on), `$region` and the tag's `$attributes`; the published tree
is cached, the HTML never is. A draft is previewed on a real page at `/_preview/region/{name}`,
the panel edits regions under the new permission `blocks.regions`, `allowed_in:
["region:header"]` offers a type only there, and `webx:blocks:regions --prune` drops the rows the
configuration no longer names. To an agent a region is the entity `region` of the content tools,
plus `blocks_regions`, `blocks_region_publish` and `blocks_region_unpublish`. `webx:doctor` warns
about a region that is declared and that no layout prints; `webx:demo` publishes a header and a
footer of blocks where both are declared, and `--remove` gives the site back its own. The
skeleton `webx-ui/site` declares `header` and `footer` and prints both tags around its own.

`webx-ui/module-inbox`: `<x-webx-inbox::form placement="footer" />` — a class `wx-form--footer` on
the form, `$placement` in its views, and a `placement` on the submission that the panel shows and
filters by, as do `inbox_get` and `inbox_list`.

`webx-ui/module-menu`: the offered block type **Menu** (`webx:blocks:offered --install
--module=menu`) — one menu of the site in one line, with dropdowns, or in columns for a footer.
