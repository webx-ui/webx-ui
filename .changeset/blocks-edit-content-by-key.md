---
'@webx-ui/php': minor
---

An agent changes part of a page without sending the page. `blocks_edit_content` takes operations
by key — `set`, `add`, `move`, `remove`, the same vocabulary screen patches use — so changing one
heading no longer means reading the whole tree and writing it back, which cost the page twice and
quietly dropped whatever an editor had done in between. `set` merges field by field, and `locale`
writes one language of a localized field rather than replacing the map with a string; a localized
field refuses a bare value, and a plain field refuses a `locale`. Reading is cheaper too:
`blocks_get_content` answers with the map of the page under `outline`, with one node under `key`,
and always with a `revision` — a short hash of the content, which both writing tools accept and
refuse to write over when the entity has changed since. The tree is edited by `ContentEdit`, pure
functions the panel can use as well. `media_*` now report a file's `path` beside its `url`: a media
field stores the key, so an agent that could only see the address had nothing to write into the
field it belongs in.
