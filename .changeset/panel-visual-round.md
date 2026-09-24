---
'@webx-ui/core': patch
'@webx-ui/schema': patch
'@webx-ui/module-admin': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-services': patch
'@webx-ui/php': patch
---

A round of panel fixes.

- `useModal` finds its host when the modal was opened inside `app.runWithContext()` — which is where vue-router runs every guard. "Leave without saving?" from `onBeforeRouteLeave` answered nothing: its buttons were the stand-in's, the dialog stayed open and the navigation hung.
- The shared category screens are a component per module, so going from the blog's rubrics to the services' categories mounts the list anew instead of keeping the rubrics on a page titled "Categories".
- The panel's toasts stack at the bottom centre instead of the bottom-right corner, where they covered the action bar's buttons.
- A category's cover is a card under its name rather than a tab of its own; the cover of an article and of a service sits right under the name too.
- A number field on a described screen stops at 240px instead of stretching to the width of a title.
- A new icon, `briefcase`, and the services section wears it instead of `star`.
