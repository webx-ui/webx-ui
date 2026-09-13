---
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': patch
'@webx-ui/module-media': patch
'@webx-ui/php': minor
---

Каркас панели называется `module-admin`: `@webx-ui/module-admin` на npm и `webx-ui/module-admin` на
Packagist вместо `@webx-ui/admin` и `webx-ui/admin`. Правило теперь одно на обе половины: всё, из
чего состоит панель, — каркас и разделы — с префиксом `module-`, библиотеки, которые живут и без
панели (`nested-set`, `localization`, `mcp`), — без него.

Код не изменился: namespace `WebxUi\Admin`, конфиг `webx-admin`, экспорт `createAdmin` — те же.
В composer `webx-ui/module-admin` объявляет `replace: webx-ui/admin`, так что сайт, который ещё
требует старое имя, получит новый пакет; в npm старый пакет помечен deprecated. В сайте меняется
импорт: `from '@webx-ui/module-admin'` и `'@webx-ui/module-admin/style.css'`.
