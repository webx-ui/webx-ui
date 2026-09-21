# Blog

`@webx-ui/module-blog` is the blog as three sections of the panel, and `webx-ui/module-blog` on
the server is what they edit. This page is both, because neither is useful alone.

Very little is written in either half, the same way the [pages](/guide/pages) module is mostly
somebody else's. The address is the registry of [`webx-ui/routing`](/guide/routing), the body is
[blocks](/guide/blocks), the draft and the history are `module-admin`, what an article says about
itself is [`module-seo`](/guide/seo), the covers are [`module-media`](/guide/media), the languages
are `webx-ui/localization`.

What this module adds is the three things that make an article an article rather than a page: it
has **a date**, it can be in **several rubrics**, and it carries **tags** — which get made by the
hundred and have to be raked over afterwards.

## Install

```bash
pnpm add @webx-ui/module-blog
composer require webx-ui/module-blog
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { blog } from '@webx-ui/module-blog'
import '@webx-ui/module-blog/style.css'

createAdmin({
  modules: [...blog()],
})
```

`blog()` returns three modules, not one: the panel's navigation is one entry per module and the
blog wants three of them. They arrive under one heading because the server puts all three in the
`blog` group. A section whose server half is not installed never appears, so leaving one out is
safe.

Permissions: `blog.articles.view` opens the list, `blog.articles.manage` writes, and
`blog.taxonomy.manage` covers rubrics and tags together — somebody who may rename a rubric may
rename a tag, and two permissions would be two places to forget.

## Addresses

Three types in the registry, all under one prefix and all in one flat namespace:

| Type      | Address              |
| --------- | -------------------- |
| `article` | `blog/how-to-choose` |
| `rubric`  | `blog/repairs`       |
| `tag`     | `blog/tag/belts`     |

The prefix is `webx-blog.prefix`; an empty one puts articles and rubrics at the root of the site
beside the pages. Either way the namespace is flat, so a rubric called "Repairs" and an article
slugged `repairs` are one address and the second of them is refused — `OnConflict::Fail` on all
three types. An address chosen deliberately should not quietly become `repairs-2`.

**The rubric is not part of an article's address,** and that follows from an article being able to
have three of them: "which of the three" has no answer, and any answer would be a hidden main
rubric that moved the article when somebody reordered the checkboxes.

Changing the prefix afterwards is one command — and a decision made once:

```bash
php artisan webx:routes:rebuild --type=article --type=rubric --type=tag
```

The paths are recomputed and the old ones stay behind as aliases that redirect.

`title`, `slug` and `lead` are translatable and the address is per language. An article with no
slug in a language has no address in it and does not open there: an address built out of another
language's slug would serve English words in front of content nobody translated.

## The date is the publication

There is no `is_published` column, no queue and no scheduler. `published_at` is the whole of it:
in the future the article is waiting, in the past it sits where that date puts it in the feed, and
which of the two it is gets decided at the moment somebody reads it.

```php
$article->publish(at: now()->addWeek());   // on the site next Tuesday

$article->isPublished();   // false
$article->isScheduled();   // true
$article->status();        // 'scheduled'

Article::query()->published()->get();   // what a reader can see, right now
```

Five states and not three: never published · dated ahead and waiting · on the site · on the site
with edits waiting · taken off it. The last one is why this is not a boolean — an article that was
never published and one that was pulled this morning both have an empty `published_at`, and only
the history tells them apart. "Draft" on something that was live at breakfast is a lie.

::: warning To the frame underneath, a scheduled article is already published
`published_at` is not null, which is all `HasDraft` looks at, so a general count of live records
elsewhere in the panel will include it. Inside this module everything goes through `published()`.
A test that only ever uses `now()` is green against code that never checks.
:::

Dates travel with an offset — `2026-09-26T09:00:00+03:00` — in both directions. A wall clock with
no zone is read by the server in the application's timezone and by the browser in the reader's,
and one article ends up listed at one time and edited at another.

## Rubrics and tags

A rubric is navigation, so instead of a draft it has `is_visible`. Hidden, it answers 404 and
drops out of the menu; its articles go on answering at their own addresses, because they are not
its property. A rubric that still holds articles **refuses to be deleted**, and says how many — a
soft-deleted rubric with live articles in it is a hole in the navigation nobody notices.

An article's rubrics are ordered, and **the first one is the main one**: the breadcrumbs, "more in
this rubric" and `<category>` in the RSS all read it. There is no separate switch for it, because
one control is better than two that can disagree.

A tag is a word. It is made from the article form and sorted out later on a screen of its own:
renamed, opened to the index, deleted, or merged into another.

```php
app(TagMerge::class)->merge([$belt, $driveBelts], $belts, redirect: true);
```

The articles move over, the duplicates go, and with `redirect: true` the addresses that existed
stay alive as rows in `seo_redirects`. They have to be redirects rather than aliases: an alias of
`webx-ui/routing` is keyed to the entity and dies with it. The operation is irreversible and the
dialog says so — the rows that say which article carried which word are gone.

## Tags and the index

A tag page has an address, because people follow tags, and by default it is **not** in the index —
a hundred thin listings is how a site teaches a search engine to ignore it. Two things open it,
and both stay visible in the panel:

- clearing `noindex` on the tag;
- writing a rule in `seo_urls` for the tag's address.

The second one is a fact about the rule and not about its contents. A rule that fills in a title
and a description and leaves `robots` empty still means "this page is wanted" — and a merge of
fields could never see that, because the empty `robots` would leave the module's `noindex`
standing underneath it. So the module asks whether a rule matches at all:

```php
app(TagIndexing::class)->state($tag);   // 'open' · 'rule' · 'noindex'
```

Three answers rather than two, so the editor who wrote the rule is not looking at a row that says
`noindex` and disagreeing with it. The tags screen shows the same three.

## The panel

**Articles** is a page of twenty with filters over it — rubric, tag, author, state, a search box
that looks in every language the site has. Pinned articles carry a mark and are always on top. The
table has fixed widths so that the row menu stays a row menu; on a phone it becomes cards.

**The editor** has four tabs: Content · Settings · SEO · History. The form is a
[described screen](/guide/screens), `blog.article-form`, registered by the package, so a module or
a project adds to it with a patch rather than a fork — the SEO card arrives that way, from
`module-seo` and not from the site.

The settings tab is where the three differences live: the rubrics as a list that is dragged into
order, a tag box that offers to make the word it cannot find, and the day and hour the article
goes out. The action bar below says which day that is before "Publish" is pressed.

::: tip Rubrics, tags, the pin and the related list are not drafted
They are rows in a pivot, not values in a column, and there is no such thing as half a row.
Moving an article between rubrics and not publishing has moved it on the site — which is right,
the navigation is not a draft, but it is not what a "Save draft" button appears to promise, so the
panel says so beside the field.
:::

The panel sends back the `revision` it read the article at — a short hash of the values and of
what is held in the pivots — and a save whose revision is no longer the current one is answered
with a `409` carrying the article as it now is. Two writers who saved the same thing did not
conflict, and the check says so. The same guard covers an agent.

**Rubrics** is the whole menu of the site on one screen, dragged into its order, and a dialog over
it for the one being edited — a form beside the list would take two thirds of the view the drag
needs. The dialog has three tabs. **Content** is the words: the name, the address, whether it is
on the site, and the introduction — a rich text editor, printed above the list of articles.
**Image** is the cover, which is a frame rather than a field and does not sit well beside a
column of inputs. **SEO** is the card, written once and then left to the rules. One `Save` for
all three, and a `422` opens the tab the failing field is on.

**Tags** is a table with selection, inline renaming, and a selection bar that opens, closes
or deletes thirty tags in one gesture — thirty requests for one gesture is thirty chances to do
half of it.

```
GET    /api/cms/blog/articles                      q, rubric, tag, author, status, sort, page
POST   /api/cms/blog/articles                      { title, slug? } — nothing else
GET    /api/cms/blog/articles/{id}                 values, the revision, a preview link
PUT    /api/cms/blog/articles/{id}                 the draft: { values, revision }
POST   /api/cms/blog/articles/{id}/discard         throw away what is waiting
POST   /api/cms/blog/articles/{id}/publish         { at? } · /unpublish
DELETE /api/cms/blog/articles/{id}                 to the bin · POST /restore
GET    /api/cms/blog/articles/{id}/versions        the publications, newest first
POST   /api/cms/blog/articles/{id}/versions/{n}/restore

GET    /api/cms/blog/rubrics                       POST · PUT · DELETE
POST   /api/cms/blog/rubrics/reorder               { ids: [] }

GET    /api/cms/blog/tags                          q, empty, noindex
POST   /api/cms/blog/tags                          PUT · DELETE
POST   /api/cms/blog/tags/merge                    { ids, keep, redirect }
POST   /api/cms/blog/tags/mass                     { ids, action: index|noindex|delete }
```

## The public half

Three listings and an article page, plus an RSS. The feed is at `{prefix}`, a rubric and a tag at
their own addresses, and page two of any of them is `?page=2` rather than an address of its own —
a listing in the registry would be two hundred rows where one belongs.

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

With no prefix the feed route is not registered at all: `/` belongs to the site, and a list of
articles on it is a page the site writes. The RSS still gets an address, because a site without
one has nothing to subscribe to.

"Read next" is the articles pinned by hand first, then filled in automatically — most tags in
common, then the main rubric, published only, never the article itself. How many is
`webx-blog.related`; zero turns the automatic half off.

## For an agent: MCP

The three sections are also a set of tools. With `webx-ui/mcp` installed (it comes with this
module), the panel serves one MCP server at `/api/cms/mcp`:

Passport comes with the panel, and a site switches it on once:

```bash
php artisan vendor:publish --tag=passport-migrations && php artisan migrate
php artisan passport:keys
```

Somebody then connects their own agent to it: they paste that address into Claude, ChatGPT or
`claude mcp add --transport http webx <address>`, the client sends them to the panel to sign in
and agree, and it leaves with a token of theirs. The agent acts as that administrator. On the
machine the site runs on, `php artisan mcp:start webx` is the same server over stdio, trusted the
way tinker is.

| Tool               | What it does                                                               |
| ------------------ | -------------------------------------------------------------------------- |
| `articles_list`    | The blog, or a state, a rubric, a tag, an author, a word — or the bin      |
| `articles_get`     | One article in full: the values, the revision, a preview link              |
| `articles_create`  | A new article as a draft, with a title and the address made out of it      |
| `articles_update`  | The values into the draft, guarded by the revision                         |
| `articles_publish` | The draft onto the site under a date · `articles_unpublish` takes it off   |
| `articles_delete`  | To the bin, and its address is released                                    |
| `rubrics_list`     | The sections of the blog, in menu order, with how many articles each holds |
| `tags_list`        | The tags, most used first, with how each stands with the index             |
| `tags_merge`       | Several tags into one — irreversible, optionally leaving redirects behind  |

Every tool that changes something accepts `dry_run: true` and then reports what it would do
without doing it. An article is named by its id or by its address — `"/blog/how-to-choose"` — and
a rubric or a tag by its id or its slug, because that is what an agent has in its hands. Text
fields answer with every language at once: an agent that got one title has no way of knowing
whether the others exist.

**The body does not travel through these tools.** Blocks are `blocks_edit_content`, which names
the node it changes and leaves the rest alone; sending a `blocks` key to `articles_update` is
refused with that sentence rather than ignored.

There is no `rubrics_create` and no `tags_create`, and both absences are deliberate. Deciding the
site has a ninth section is a decision about its navigation; inventing a tag while writing one
sentence is how a blog ends up holding three spellings of one word. What an agent gets instead is
`tags_merge`, which is the job nobody gets round to.

Before writing, an agent reads `blog://feed`: the last thirty articles as a reader sees them, with
their addresses, rubrics and tags. Half of what it is for is finding out that the blog published
exactly this in March; the other half is picking up how the blog writes. One prompt,
`write_article`, packages the loop — read the feed, the rubrics, the tags and the block catalogue,
create the article, fill it with `blocks_edit_content`, settle the settings, look at the preview,
write the SEO card, and leave it as a draft.

::: warning The prompt tells the agent not to set `published_at`
In this module the date _is_ the publication. An agent that writes one while filling in the
settings has put a half-written article on the site — or scheduled it there — without anything
named "publish" being called.
:::

## Config

`config/webx-blog.php`:

| Key            | Default | What it is                                            |
| -------------- | ------- | ----------------------------------------------------- |
| `prefix`       | `blog`  | The first segment of every blog address               |
| `per_page`     | `12`    | Articles on a listing page                            |
| `related`      | `3`     | How many "read next" are worked out beyond the pinned |
| `tags.noindex` | `true`  | What a new tag starts with                            |
| `views.*`      |         | The views each page is printed with                   |
| `middleware`   |         | What the feed and the RSS run through                 |

## What is deferred

- **Comments** — a module the size of `module-inbox`: moderation, spam, notifications.
- **Subscriptions and a newsletter** need a scheduler and outgoing mail that actually run.
- **A view counter** is a write on every GET, and a conversation about caching.
- **Series, co-authors, authors who are not administrators.**
- **A tree of rubrics** — if it ever turns out to be wanted, `nested-set` and `TreePath` are there.
- **Permissions** beyond the three above, like everywhere else, wait for the wider conversation
  about roles.
- **`sitemap.xml`** belongs to `module-seo`; the blog only hands over its canonical addresses.
