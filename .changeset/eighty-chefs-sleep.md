---
'@webx-ui/module-admin': minor
'@webx-ui/module-pages': patch
'@webx-ui/module-blog': patch
---

`WxSaveState`: the save says so with a mark, and then stops saying it

The bar of the page and article editors carried the word "Saved". It is right nearly all of the
time, which is what makes it furniture: it is on screen when nothing is happening, and nothing is
happening is exactly when nobody is asking. What anyone wants to know is whether _this_ save
landed, and only until it has.

So the word is a mark now: a wheel while the save is in flight, a green tick when it lands, and
nothing two seconds later. Nothing for unsaved work either — the head already carries a badge
beside the name, and the enabled save button is the plainest statement that there is something to
save. The element keeps its place while it is empty, or the buttons beside it would shift by its
width twice per save.

The words stay for whoever is not looking at the bar: the mark is a live region carrying "Saving…"
and then "Saved", which is what a screen reader hears. Its own `state-saved` and `state-saving`
lines are gone from both modules, along with the `state-unsaved` that nothing says any more.
