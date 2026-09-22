---
'@webx-ui/module-admin': minor
'@webx-ui/php': minor
---

A link is chosen rather than typed: the contract for what a panel can point at

The address registry answers "what is this entity's address". Nothing answered "what can I link to
at all" — a `RouteType` has a model, a formatter and a handler, and nowhere in it a title to show or
a way to search — so every field that wanted a link had to be told by hand. This is that second
question, and it lives in the frame rather than in any one section, because the menu is only the
first of the fields that will ask it.

On the server: `LinkSource`, `LinkCandidate` and the `LinkSources` register that content modules fill
on boot, the `Link` value every place keeps a link as, and four addresses under `/api/cms/links` —
the sections of the picker filtered by the reader's permissions, a search inside one, a resolve of
several types in one query per type, and the site's own named addresses for the field where a path
is typed. `module-pages` registers pages, `module-blog` registers articles, rubrics and tags.

`available` is deliberately apart from having an address: the registry holds one for a draft too, so
a picker that trusted it would offer a link to a page the site answers 404 for. A draft is offered,
drawn dimmed, and left out by whoever renders.

The anchor is a field of the link rather than part of the address. A typed address can carry one
inline; a chosen page has nowhere to write one, because its address is looked up rather than
written. So `hash` sits beside the target, is kept without its `#`, and is appended on every read —
and an address typed as `/about#team` is taken apart on the way in, so that a link cannot end
`#team#top`.

In the browser: `WxLinkPicker`, `wx-link` on described screens, and `createLinksApi`.

In `webx-ui/routing`: `SiteUrl`, with the language prefix that used to be private to `HasUrl` — a
hand-written `/account` needs the same prefix an entity's address gets, and a second reading of the
strategy is a second reading that drifts.
