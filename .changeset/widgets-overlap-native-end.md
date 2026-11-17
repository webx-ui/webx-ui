---
'@webx-ui/php': patch
---

widgets: `data-webx-overlap` without scroll-driven animations (Safari before `timeline-scope`, Firefox) no longer shows a band of the pinned section under the one sliding over it while the page is scrolled by touch. The pinned section used to go up by a `translate` written after each `scroll` — a frame behind the compositor's scroll, so its bottom stood under the covering section's bottom by a frame's scroll (30 px per gesture step, more with inertia). Now its `position: sticky` ends by itself: a `margin-bottom` reaching from where it is let go to the end of its parent, and the covering section's `margin-top` pulled back by as much, so nothing moves on the page. Written with the sizes, never on scroll; where the margins do not cancel out, the written `translate` stays. Scroll-driven path, reduced motion, print and no-JS are unchanged. The runtime's gzip budget goes from 16 to 16.25 KB for it.
