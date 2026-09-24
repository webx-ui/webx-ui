---
'@webx-ui/php': minor
---

`webx-ui/module-admin`: relations between records of different modules, or of one — a recipe to its services, a recipe to the recipes like it. One table, `webx_relations`, with no foreign keys, so a module pointed at may be installed later or removed and put back; a module registers what can be pointed at in `RelationTargets`, and a record that points uses `HasRelations` (`related()`, `Relations::load()` for a list, `Relations::owners()` and `relatedTo()` from the other end). Deleting for good takes the rows along on both ends, the bin does not. The `wx-relations` field edits one relation, goes into the draft of a drafted record and takes effect on publishing, and leaves the screen when its target is not installed — the value stays. `GET /api/cms/relations/{target}` finds candidates and names the chosen behind the target's permission. `wx-collection` gets a relation filter (`related`), including "related to the record whose page the block is on", and `CollectionSource` a `relations()` method. `HasCategories` writes and filters a second kind of category by name. `webx:doctor` counts relations pointing at a module that is not installed.

`webx-ui/module-services`: services can be pointed at (`service`), and the editor's save now puts block values through their field types, as an agent's edit already did.

`webx-ui/module-blocks`: the renderer hands the entity whose page it prints to a field type that reads differently on it (`ResolvesForEntity`).
