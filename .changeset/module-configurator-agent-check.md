---
'@webx-ui/php': patch
'@webx-ui/module-configurator': patch
---

What an agent building a configurator over MCP stumbled on, fixed after a run end to end.

- **A card picture that is not a library key is one problem, not two.** `image.missing` was told
  twice for `{ "id": 35 }` — by the PHP check and by the JS one alike.
- **The tools and `configurators://schema` say where a picture key comes from:** the `path` of
  `media_get_file`, since `media_upload_from_url` answers with the id. `configurators_quote` says
  that `values` are resolved before `actions`, the schema reference that a size in `initial` is
  pulled into its range, and `configurators://catalog` what the block `configurator` takes.
- **A tool argument's own description reaches the agent.** It was built as `$base + [...]`, which
  keeps the shared one: `configurators_quote` and `_create`, `tags_merge`, `articles_*` `slug` and
  the banner tools showed the generic text. Now the specific words win, after the shared format.
