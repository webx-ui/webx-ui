---
'@webx-ui/php': minor
---

Four findings of the first site on compositions.

- compositions: the compiled set writes `.gitignore` (`*`) into its directory
  (`storage/framework/compositions/`) when there is none: `build-*` and `manifest.php` stay out of
  `git status` on every site, made before or after, with no file of the site to change. A site
  that already added `compositions/` to `storage/framework/.gitignore` may keep the line or drop
  it; a `.gitignore` of its own in the directory is left as it is.
- compositions: `null` in a patch's `props` takes the prop off (the component's default applies),
  and `webx:composition:show --origin` names the file that did. theme-default's `header` keeps
  `mode: "drill"` — its header was drill before compositions; `{"props": {"header": {"mode": null}}}`
  gives the widget's `accordion` back.
- widgets: `<x-webx-mobile-menu.nav parent="split">` (and `<x-webx-header parent="split">`) leaves
  out a child that leads where its parent does («All showers» → /showers under «Showers» →
  /showers; the path without its trailing `/`, the site's own host aside, the query counted). A
  branch left with no children loses its arrow and is a plain link. `repeat` and the header's
  dropdown are unchanged. A site that filtered such children in its own leaf can drop the filter.
- theme-default: the `contact` block has the switch `show_form_description` («Description of the
  form»), **off by default** — the line under the form's title is for its dialog; in the block its
  headings say what the form is for, and before this the description was printed twice under a
  heading. A block saved before reads the default too: a contact block that should print the
  description turns the switch on. The Inbox guide and `AGENTS.md` name `:description="false"`
  for a form in a block with headings of its own.

**For existing sites.** Nothing to do for the `.gitignore`. The new field of `contact` reaches a
site's block type only by an import a person runs (`php artisan webx:blocks:import --theme
--replace=contact --publish`, or the panel's import); until then the block prints as before. A
site that copied `contact.json` into its theme keeps its copy: add the field there if it wants
the switch.
