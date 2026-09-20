---
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': patch
'@webx-ui/module-blocks': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-inbox': patch
'@webx-ui/module-media': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-seo': patch
'@webx-ui/module-settings': patch
'@webx-ui/php': patch
---

One head for every screen of the panel

Eight screens each answered "what goes at the top" on their own, and gave eight answers: the
heading at three sizes, the way out as an arrow on four of them and as a line of breadcrumbs on the
rest, the buttons folding into a `···` on two editors and wrapping onto a third line everywhere
else. Writing a new screen meant writing that line again and getting it slightly different again.

`WxScreenHead` is that line, once: the way out, the name with the state said beside it, the line
under it that says which record this is, and what can be done here. `WxListScreen` is built on it,
so a list and the editor a row opens are the same object rather than two similar ones — and it
takes `back` now, which is what the statuses screen used to draw above its own heading for want of
anywhere to put it.

**The actions are declared rather than drawn.** The same action has to be a button on a desktop and
a line of a menu on a phone, and one vnode cannot be mounted in two places — as markup it had to be
written twice, which is exactly what the page and article editors did. As `ScreenAction[]` it is
written once: `primary` is the one thing the screen exists for and the one that keeps a button when
the head runs out of room, `danger` is never a button at all, `menu` is in the `···` at every
width, and `loading`, `disabled` and `href` mean what they say. Below 720px — 480 on a list, which
carries one word and no trail — everything but the primary folds behind the `···` and that primary
takes the line under the name, full width.

The name’s line is the head: the way out at the start of it and the actions at the end, both
centred on it however many badges stand beside the name. The trail is the line above, and it
scrolls sideways with no scrollbar showing rather than wrapping — on a phone a path four levels
deep was two lines of the smallest type on the screen, standing between the reader and the name of
what they had opened.

Two things that were quietly wrong come out with it. Nineteen buttons across the panel passed
`icon="plus"` to `WxButton`, which has no such prop: the attribute landed on the `<button>` and
drew nothing, so the panel’s main actions had no icons at all. And the `···` said `More` in English
in every language, because the core carries English defaults and knows no dictionary — the panel
gives it the word now, in all ten.
