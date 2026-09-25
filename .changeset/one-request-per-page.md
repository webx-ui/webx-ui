---
'@webx-ui/module-blog': patch
'@webx-ui/module-inbox': patch
---

Turning a page of articles or submissions sends one request, not two. The watcher over the filters
read them through one getter that returned a new array each time, so every change of the address —
the page turn included — fired it, and a second request without `per_page` raced the table's own.
