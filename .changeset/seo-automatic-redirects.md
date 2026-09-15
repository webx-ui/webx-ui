---
'@webx-ui/module-seo': minor
---

The redirects nobody wrote, beside the ones an editor did

A third screen in the SEO section, **Automatic**: the addresses a rename left behind. Renaming a
page or moving a branch leaves its old address answering 301, which is what carries a bookmark, an
inbound link and a search result through an edit in the panel — and a reader who lands on a dead
address does not care which half of the system answered, so an editor chasing one should not have
to either.

Read only, and not for want of a form: an alias belongs to the entity that moved, which makes it,
repoints it on the next rename, and takes it away when it is deleted. A screen that could edit one
would be a screen that can make the registry disagree with the site. To give an old address a
different answer, write a rule on the **Redirects** tab — rules are tried before routing, so yours
wins and the alias underneath stops mattering.

Which is why the redirect form now says when the address it is about to take over is a live page of
the site. It says it and saves anyway: shadowing a page is a legitimate thing to want, and refusing
it here would make the common case impossible to express. **Check an address** answers the same
question for any address you type into it.

Needs `webx-ui/routing` on the server, which `webx-ui/module-seo` now requires.
