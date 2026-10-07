---
'@webx-ui/php': patch
---

`module-blocks`: the seven `blocks_region_*` tools are offered only when the layout declares a region. `blocks_usage` lists the views of the site that call a type by tag (`views`; folders in `webx-blocks.views`, `resources/views` by default) and `blocks_delete` refuses such a type unless `force: true`. `blocks_update` lists changed content fields in `changes` and counts what it wrote (`versions_written`, a rename included). `blocks_render` answers `values_from`, leaves out the preview markers and warns about values a write would refuse — a field the type lacks, a `wx-link` without `target`. `blocks_get` warns about sample values for fields the schema lacks. A dry run of `blocks_edit_content` / `blocks_set_content` answers the current `revision` and the would-be one as `would_be_revision`. `blocks://fields` leaves out the field types of module screens and says `wx-link` needs `target`.
