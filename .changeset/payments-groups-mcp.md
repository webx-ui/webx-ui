---
'@webx-ui/php': minor
---

`webx-ui/module-payments`: the groups of methods for an agent. New tools `payments_groups_list`, `payments_groups_save` and `payments_groups_reorder`, behind `payments:read` / `payments:write` with `payments.view` / `payments.manage`: a group is edited over the config by the panel's own form, with `dry_run`, and no tool creates or removes one. `payments_methods_save` takes `group` and answers `warnings`, `payments_methods_reorder` also takes `groups: [{ key, methods }]` and moves a method as the panel does, `payments_available` says `group` and `apple_pay_only`, and `payments://catalog` lists the groups, the pictograms and the marks. The guide has a section for the developer of a site: `groups` in the config and official files of the marks.
