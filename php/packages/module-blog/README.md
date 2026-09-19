# webx-ui/module-blog

A blog as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: articles
made of blocks, rubrics and tags, addresses from the registry, scheduled publication, a feed and
an RSS.

Very little of that is written here, and that is the point. The address is `webx-ui/routing`, the
content is `webx-ui/module-blocks`, the draft and the history are `webx-ui/module-admin`, what a
page says about itself is `webx-ui/module-seo`, the covers are `webx-ui/module-media`, the
languages are `webx-ui/localization`. What this package adds is the three things that make an
article an article rather than a page: it has a date, it can be in several rubrics, and it
carries tags.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-auth`, `webx-ui/module-blocks`, `webx-ui/module-media`,
  `webx-ui/module-seo`, `webx-ui/routing`, `webx-ui/localization`, `webx-ui/mcp`

## Install

```bash
composer require webx-ui/module-blog
php artisan migrate
```

Permissions: `blog.articles.view`, `blog.articles.manage`, `blog.taxonomy.manage`.

## Addresses

Three types in the registry, all under one prefix and all in one flat namespace:

| Type      | Address              |
| --------- | -------------------- |
| `article` | `blog/how-to-choose` |
| `rubric`  | `blog/repairs`       |
| `tag`     | `blog/tag/belts`     |

The prefix is `webx-blog.prefix`, and an empty one puts articles and rubrics at the root of the
site beside the pages. Either way the namespace is flat, so a rubric called "Repairs" and an
article slugged `repairs` are one address and the second of them is refused — `OnConflict::Fail`
on all three types. An address chosen deliberately should not quietly become `repairs-2`.

The rubric is not part of an article's address, and that follows from rubrics being something an
article can have three of: "which of the three" has no answer, and any answer would be a hidden
main rubric that moved the article when somebody reordered the checkboxes.

Changing the prefix afterwards is one command — and a decision made once:

```bash
php artisan webx:routes:rebuild --type=article --type=rubric --type=tag
```

The paths are recomputed and the old ones stay behind as aliases that redirect.

## The date is the publication

There is no `is_published` column, no queue and no scheduler. `published_at` is the whole of it:
in the future the article is waiting, in the past it sits where that date puts it in the feed,
and which of the two it is gets decided at the moment somebody reads it.

```php
$article->publish(at: now()->addWeek());   // on the site next Tuesday

$article->isPublished();   // false
$article->isScheduled();   // true
$article->status();        // 'scheduled'

Article::query()->published()->get();   // what a reader can see, right now
```

**Worth remembering:** to the frame underneath, a scheduled article is already published —
`published_at` is not null, which is all `HasDraft` looks at. Any general count of live records
elsewhere in the panel will include it. Inside this module everything goes through `published()`.

## Rubrics and tags

A rubric is navigation, so instead of a draft it has `is_visible`. Hidden, it answers 404 and
drops out of the menu; its articles go on answering at their own addresses, because they are not
its property. A rubric that still holds articles **refuses to be deleted**, and says how many —
a soft-deleted rubric with live articles in it is a hole in the navigation nobody notices.

A tag is a word. It is made from the article form and sorted out later on a screen of its own:
renamed, opened to the index, deleted, or merged into another.

```php
app(TagMerge::class)->merge([$belt, $driveBelts], $belts, redirect: true);
```

The articles move over, the duplicates go, and with `redirect: true` the addresses that existed
stay alive as rows in `seo_redirects`. They have to be redirects rather than aliases: an alias of
`webx-ui/routing` is keyed to the entity and dies with it.

## Tags and the index

A tag page has an address, because people follow tags, and by default it is **not** in the
index — a hundred thin listings is how a site teaches a search engine to ignore it. Two things
open it, and both stay visible in the panel:

- clearing `noindex` on the tag;
- writing a rule in `seo_urls` for the tag's address.

The second one is a fact about the rule and not about its contents. A rule that fills in a title
and a description and leaves `robots` empty still means "this page is wanted" — and a merge of
fields could never see that, because the empty `robots` would leave the module's `noindex`
standing underneath it. So `TagSource` asks `module-seo` whether a rule matches at all:

```php
app(TagIndexing::class)->state($tag);   // 'open' · 'rule' · 'noindex'
```

Three answers rather than two, so that the editor who wrote the rule is not looking at a row
that says `noindex` and disagreeing with it.

## The public half

```php
'views' => [
    'article' => 'blog.article',
    'feed'    => 'blog.feed',
    'rubric'  => 'blog.rubric',
    'tag'     => 'blog.tag',
    'rss'     => 'blog.rss',
],
```

Until the site has written those, the package's own are used — bare documents, so a fresh
installation serves a blog rather than an error. Publish them and rewrite them:

```bash
php artisan vendor:publish --tag=webx-blog-views
```

The feed is at `{prefix}` and the RSS at `{prefix}/rss`; page two of any listing is `?page=2`
rather than an address of its own. With no prefix the feed route is not registered at all: `/`
belongs to the site, and a list of articles on it is a page the site writes.

## Configuration

```bash
php artisan vendor:publish --tag=webx-blog-config
```

| Key            | Default | What it is                                            |
| -------------- | ------- | ----------------------------------------------------- |
| `prefix`       | `blog`  | The first segment of every blog address               |
| `per_page`     | `12`    | Articles on a listing page                            |
| `related`      | `3`     | How many "read next" are worked out beyond the pinned |
| `tags.noindex` | `true`  | What a new tag starts with                            |
| `views.*`      |         | The views each page is printed with                   |
| `middleware`   |         | What the feed and the RSS run through                 |

## License

MIT.
