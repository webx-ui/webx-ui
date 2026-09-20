---
'@webx-ui/module-seo': minor
---

The share image appears wherever the library is installed, and is as wide as the fields beside it

**It finds the picker itself.** `wx-seo` drew its share image only when a panel had passed
`seo({ mediaField: WxMediaField })`. A panel that installed the library had already said so
once, and the second saying is a thing to forget — silently: every other SEO field is there, and
the picture is present on the screen somebody remembered and missing on the one they did not.
The card now looks `wx-media` up in the panel's own registry, the way `module-blog`'s rubric
form already did. `mediaField` stays, for a caller that wants a different field than the
registered one; nothing is imported from the media package, so a panel without a library still
edits everything else and simply has no picture.

Injected rather than taken with `useAdmin()`, because the card is mounted by the docs and by its
own tests outside any panel, and there it has to draw the rest rather than throw.

**It stops where the other fields stop.** The picture is the one control in the card that is not
inside a `WxFormItem` — it draws its own label — so the cap a form item puts on its control never
reached it: the image ran the full width of the card while the title under it stopped at 640. It
now reads the same `--wx-field-max-width`, so a screen that widens its fields widens this too.

**And it shows what a share would look like.** Under the two fields, the same idea the first
tab has for search results: the picture in the 1.91:1 crop every network uses, the title and the
description with the fallbacks those fields promise — an empty share title takes the page’s
title. It costs no request: the address travels beside the key in the value, which is what
`seoFieldValues()` put it there for. An empty picture is not left blank but says the site fills
it in from the record itself, because a blank frame reads as “nothing will be shown” and that is
not what happens.
