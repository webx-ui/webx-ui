---
'@webx-ui/php': patch
---

Widgets: a link to an anchor of the page stops it right under the sticky header again, with the smooth scrolling and without it. An anchor inside a `data-webx-reveal` still waiting below the fold used to be measured while lowered by the effect and came up under the header afterwards: the link now brings that element in at once, and the anchor of the address a page opens with is never hidden. With `data-webx-smooth-scroll` a click on an anchor link also takes the focus there, as the browser's own jump does.
