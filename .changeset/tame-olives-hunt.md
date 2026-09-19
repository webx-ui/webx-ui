---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-blog': minor
'@webx-ui/module-auth': minor
'@webx-ui/module-seo': minor
'@webx-ui/module-inbox': minor
'@webx-ui/php': minor
---

The panel's lists take their filters behind the funnel and draw their narrow rows as entities.

`WxFilterChips` and `AppliedFilter` in `module-admin` give every section the same chip, and the
panel's own two words — the name of the funnel and "reset all" — live with it in all ten
languages. Articles, the SEO rules and the administrators put their dropdowns in `#filters` and
what they are set to in `#applied`; submissions, administrators and articles draw a card below
their breakpoint as `WxEntityCard` rather than as a stack of labelled lines, with the `···` in
the card's own top strip beside the checkbox.

`WxEntityCard` gained `titleLines`, because an article's headline is a sentence: one line of it
on a phone is half a thought, and the list it replaced already clamped at two.
