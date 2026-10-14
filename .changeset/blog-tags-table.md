---
'@webx-ui/php': patch
---

`webx-ui/module-blog`: saving an article with a tag or a pinned related article no longer fails with "The server could not do that". The two fields were checked against `tags` and `articles` by name, which the rename of the tables to `blog_*` left behind; they now take the tables from the models.
