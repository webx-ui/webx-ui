---
'@webx-ui/php': minor
---

`module-media` stores and resolves the three new field types, `module-blocks` tells an agent
about them.

`GalleryFieldType`, `FileFieldType` and `FilesFieldType` join `wx-media` in
`WebxUi\Media\Screens`, sharing one set of rules, one `store` and one `resolve`. A resolved value
now carries the whole of what the library knows — `url`, `thumb`, `name`, `extension`, `mime`,
`size`, `width`, `height` — because a Blade template has nothing else to ask with, and a whole
list is looked up in one query rather than one per value. `props.accept` is checked on the way in
against the row fetched for the resolve; a key whose file has been deleted is kept and resolves
to `url: null`.
