---
'@webx-ui/module-pages': patch
'@webx-ui/module-blog': patch
---

The content tab is no longer a box of a fixed height

Both editors kept a tab exactly one window tall, with its own scrollbar, for the sake of a
constructor whose three columns scrolled inside themselves. The constructor does not work that way
any more — its preview is as tall as the page it shows and the browser scrolls it — so the rules
that arranged all that are gone with the `fill` prop they hung on. A tab that grows with its
contents is also the only kind that does not clip them.
