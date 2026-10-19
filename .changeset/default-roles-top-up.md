---
'@webx-ui/php': minor
---

module-auth: готовые роли доливаются правами модулей, поставленных позже. Роль помнит, что ей уже предлагалось (`cms_roles.offered`), и получает только непредложенное — снятое владельцем право не возвращается, свои роли сайта не трогаются. Доливают `webx:boot` (новый шаг `webx:roles:defaults --top-up`), `webx:module:add`, миграция обновления и вручную `webx:roles:defaults [--top-up] [--dry-run]`; MCP-инструмент `admins_sync_default_roles` с `dry_run`; `webx:doctor` предупреждает о недолитых правах.
