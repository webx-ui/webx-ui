---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/php': minor
---

The blog gets a picture of its own, and so can every other navigation group

Two separate things made the sidebar say the wrong thing about the blog.

**A group could not carry an icon at all.** `AdminNav` drew `icon="gear"` on every branch, so
"Blog" and "System" looked like the same kind of thing — one is what the site is about, the other
is what keeps the panel running. A group now names its own picture: `'icon' => 'newspaper'` beside
the title in `webx-admin.groups`, through the manifest, into `NavGroup`. The key is optional and
falls back to the gear, so a site that published `webx-admin.php` before this — or a group written
by a module that has not been updated — looks exactly as it looked.

**`ArticlesModule` named `file-text`, which was not an icon.** The set has `file-txt`, `file-md`
and the rest of the file family, but nothing under that name, so `resolveIcon` came back empty and
`WxIcon` rendered no `<svg>` at all: no warning, no placeholder, just a menu line whose label had
slid left into the room the picture was meant to occupy. Both halves type-check a name neither of
them can check, so the seam is now tested — every `icon()` and every `'icon' =>` in the PHP
packages is looked up in the set.

New in `@webx-ui/core`: `file-text`, the page with three lines of prose that the file family
already drew, under the name a section full of writing asks for; and `newspaper`, a folded sheet
with the one behind it curling out at the bottom left — the fold is the only thing that tells a
paper from a document at 16 px.
