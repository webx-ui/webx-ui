---
'@webx-ui/php': minor
---

The page of a blog article is the composition `blog.article`: `article`, `article.header`, `article.breadcrumbs`, `article.title`, `article.meta`, `article.cover`, `article.content` (protected), `article.tags`, `article.related`, each leaf a view of module-blog (`webx-blog::article.*`). A theme moves the cover above the title, drops "Read next", prints the byline with a view of its own or adds a node before the text in `theme/compositions/blog.article/default.json` — no copy of `article.blade.php`. The HTML of an article without a patch is the same but for the whitespace between tags. module-blog now requires `webx-ui/compositions`.

A site with its own `resources/views/vendor/webx-blog/article.blade.php` (or a theme's `views/vendor/webx-blog/article.blade.php`) keeps it: the copy wins over the composition, and `webx:theme:check` says so as a note. To go back to the package's page: `php artisan webx:composition:show blog.article --blade` prints what the composition gives, the difference with the copy becomes a patch, and the copy is deleted.

`webx:theme:check` notes a copy of a module's page that a composition replaced (`vendor/webx-<module>/<view>.blade.php` for `<module>.<view>`), not only `components/layout.blade.php`.

Fix: a module that loads its compositions as `__DIR__.'/../resources/compositions'` — module-seo, module-snippets, module-blog — is named after its package. They were all one layer named `..`, and the last one loaded replaced the others: on a site with module-seo and module-snippets one of them added nothing to the layout.
