---
'@webx-ui/php': minor
---

The blog through an agent's doors, and the guide

Nine tools, one resource and one prompt, all of them the same doors the panel uses: `articles_list`
is the panel's own query, so the five states of an article are one answer and not two;
`articles_update` goes through the described screen, so a tab `module-seo` put on the editor is a
field an agent can write; `articles_publish` takes `at`, because in this module the date _is_ the
publication and there is nowhere else for it to live.

`rubrics_list` only looks, and `tags_create` does not exist — both on purpose. Deciding the site
has a ninth section is a decision about its navigation, and inventing a tag while writing one
sentence is exactly how a blog ends up holding "belts", "belt" and "drive belts". What an agent
gets instead is `tags_merge`, sorted by use so the duplicates stand next to the word they
duplicate: the irreversible half of the job nobody gets round to, with a dry run that reports how
many articles would come out carrying the surviving word — counted once, because an article that
carried both tags is one article.

`blog://feed` is the last thirty articles as a reader sees them rather than a second editor's
view. Half of what it is for is finding out that this was published in March; the other half is
picking up how the blog writes before writing for it. The prompt `write_article` puts the loop in
front of the agent, and spends two of its lines on the step a first attempt gets wrong twice: the
body is `blocks_edit_content` and not `articles_update`, and writing `published_at` while filling
in the settings puts a half-written article on the site without anything named "publish" being
called.

`apps/docs/guide/blog.md` is both halves on one page, and the package README now says what an
agent may do.
