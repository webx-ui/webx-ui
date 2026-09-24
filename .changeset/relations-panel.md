---
'@webx-ui/module-admin': minor
---

The panel half of relations between records: the field type `wx-relations` (`WxRelationsField`) —
the chosen records of another section as rows to drag and remove, "Add" searching
`GET /api/cms/relations/{target}`, a mark on a record the site does not show, and only the list
for an administrator who may not see the target — and "only related to" in `wx-collection`, for a
source whose records point at another section. `provideRelationOwner()` keeps the record itself out
of its own search. Also: the collection field's muted text uses a colour token that exists.
