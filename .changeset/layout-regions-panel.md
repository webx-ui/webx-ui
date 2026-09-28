---
'@webx-ui/module-blocks': minor
---

Site regions — the header and the footer made of blocks, as a section of their own: `regions()`
beside `blocks()`, a card per region the site declares (on the site with its version, a draft, or
the fallback from code) and an editor like a page's — the blocks field with a preview on a real
page of the site and a picker of which page, the history with a restore into the draft,
«Publish», «Take off the site», autosave and the revision guard, and «Move the markup into a
block» for an empty region. Opened by `blocks.regions`; the picker and the preview answer that
permission too, and the catalogue narrows to a region with `?region=`.
