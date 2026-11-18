---
'@webx-ui/php': patch
---

themes: with critical CSS the deferred stylesheets of the head apply together and in document order — when the last one has loaded (or failed), one small script switches all of them to `media="all"` at once. Each used to switch itself on when it arrived, so an early package sheet applied over the critical CSS before the site's theme and beat its rules for a while (layout shift up to 1 on a throttled phone). The `<noscript>` links stay; without the critical file the page is unchanged.
