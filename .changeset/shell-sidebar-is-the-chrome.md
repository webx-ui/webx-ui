---
'@webx-ui/module-admin': minor
'@webx-ui/core': minor
'@webx-ui/php': minor
---

The panel's frame: the bar goes into the sidebar, and the page scrolls itself.

On a desktop and a tablet there is no bar across the top of the panel any more. The sidebar is
the whole of the chrome and has three zones — the brand and the collapse button, the menu with its
own scrollbar, the account at the bottom with its menu opening upwards — and the 56px the bar took
out of the window's height go to the screen. A phone has no such column, so there the bar comes
back with the burger, the brand and the account, and the menu is a drawer; choosing a section
there now closes the drawer, which it did not before.

The frame floats: the sidebar and the phone's bar are cards inset from the edges of the window,
with the body colour running all the way round them. The inset is the panel's spacing step —
8 on a phone, 12 on a tablet, 16 on a desktop — and the column is 220px wide, 56px as a rail,
which leaves a screen exactly the width it had under the old frame at 1280 and at 1440.

What scrolls is the page, natively: the shell no longer caps itself at one viewport, and the
sidebar stands still beside a document that moves. A screen that has to be exactly as tall as the
window still says `data-wx-fill`, but the height it gets is now measured from the window rather
than from a scrolling column.

`WxAside` grew the `top` and `bottom` slots — with either of them filled, `scroll` moves to the
middle zone — plus `sticky`, for a column that stands beside a scrolling page, and `floating`, for
one drawn as a card. `WxHeader` takes `floating` too. Both are additions: every existing shape
behaves exactly as it did, and `viewport` shells are untouched.

`webx-ui/module-admin` adds `nav.expand` in all ten languages, for the button on the rail.
