---
'@webx-ui/php': minor
'@webx-ui/module-payments': minor
---

`webx-ui/module-payments`: the groups of methods in the panel. The screen of methods lists the groups in their order with their methods: drag a group, drag a method inside its group or into another. «Edit» changes a group's title and description by language and its pictogram, as edits over `webx-payments.groups`: the config is not touched, and the panel has nothing to create or remove a group with. The form of a method gets the field «Group», the way to move it from the keyboard: it goes to the end of the group chosen, and the group of its type stores nothing. Two enabled methods of one group with the same marks are warned of in the list, over the form and after a save, which is never refused. New routes `GET groups` and `PUT groups/{key}`, behind `payments.view` and `payments.manage`; `PUT methods/order` also takes `{ groups: [{ key, methods }] }`, and `ids` still serves a site without groups. A row of `GET methods` has `group`, `group_key`, `marks` and `similar_to`, and the answer has `marks`.
