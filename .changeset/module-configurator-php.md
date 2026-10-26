---
'@webx-ui/php': minor
---

New package `webx-ui/module-configurator` — the server half of a product configurator (section
«Configurators», id `configurators`).

- **Storage.** Table `configurators`: a key, a translated title, the schema as one JSON document, the
  inbox form; drafts and versions of the frame.
- **The check.** `Schema\Validator` gives every problem with its path and code; a draft is saved
  with problems, a publication is refused with them.
- **The engine.** Visibility (`when`/`unless`), a choice that becomes hidden moves to the first
  visible one, `defaults` and `limits`, an area formula without `eval`, `fixed`/`perM2`/simple
  `percent`, a discount with a last day, rounding half away from zero, a breakdown line by line.
  Parity cases in `resources/fixtures/pricing/` for the PHP and the coming JS copy.
- **MCP.** `configurators_list`, `_get`, `_create`, `_update`, `_publish`, `_unpublish`,
  `_discard`, `_versions`, `_version_restore`, `_delete` (with `dry_run`), `configurators_quote` and
  `configurators_validate`; resources `configurators://catalog` and `configurators://schema`.
- **Demo.** `webx:demo --module=configurators`: a shower enclosure configurator with neutral
  drawings and, with the inbox, its request form.

The panel editor, the block on the site and the configuration in a request follow.
