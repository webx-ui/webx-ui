# @webx-ui/module-blog

The front end of the blog section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: articles, and — as they are written — rubrics and tags.

The other half is the Composer package `webx-ui/module-blog`, which owns the articles, their
addresses, their publication dates and the API. A section appears in the panel when both halves
are installed.

## Install

```bash
npm install @webx-ui/module-blog
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { blog } from '@webx-ui/module-blog'
import '@webx-ui/module-blog/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...blog()],
}).mount()
```

`blog()` answers with a section per entry of the navigation rather than with one module, because
the panel draws one entry per module and the blog wants three — articles, rubrics and tags,
under one heading. The server puts all three in the `blog` group; a section whose screen is not
here yet is silently left out of the menu.

`blog({ path: '/writing' })` puts the sections somewhere else inside the panel.

## The list

A page of articles, newest first, with whatever is pinned above them. Over it: the views of the
same list as tabs — everything, live, scheduled, drafts, taken off the site, and the bin — a
search box, and three dropdowns for the rubric, the tag and the author. All of it lives in the
address, so opening an article and coming back lands on the same page of the same filter.

Five states, and the pair worth knowing apart is the last two: an article that was never
published and one that was taken off the site this morning both have no publication date, and
only its history tells them apart. Calling the second one a draft would be a lie.

Below 640 pixels the row becomes a card: the cover on the left, the title, one rubric, the
state, the date and the author beside it. Ten articles on a phone screen instead of two.

## Licence

MIT
