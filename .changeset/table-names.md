---
'@webx-ui/php': minor
---

Tables follow one naming rule: the frame of the panel is `cms_*`, a section is named after
itself. `module-admin` renames `entity_versions`, `entity_notes`, `webx_relations`,
`admin_history` and `admin_uploads` to `cms_versions`, `cms_notes`, `cms_relations`,
`cms_history` and `cms_uploads`; `module-blog` moves `articles`, `rubrics`, `tags` and the
`article_*` links under `blog_*`; `module-services` renames `service_category` to
`service_category_service`. The rows stay — run `php artisan migrate` after updating. Code of a
site that queries these tables by name needs the new names.
