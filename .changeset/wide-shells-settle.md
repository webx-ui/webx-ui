---
'@webx-ui/core': minor
---

The rest of Wave 1: `WxAlert`, `WxDivider`, `WxSpace`, the layout shell, the grid, the menu, the
breadcrumb trail and `WxScrollbar` — thirteen new components, each with a page of its own in the
documentation.

`WxAlert` is the message that stays on the page rather than the one that flies past: four types
across the usual three weights, an icon that follows the type, a `title` over a body, an `actions`
slot, and a × that hides the alert itself — unlike a badge, an alert has nobody else to remove it.
`live` announces one that appears in response to something, and is off by default so a message
rendered with the page is not read out of nowhere.

`WxDivider` draws a rule across the flow or a hairline along the line. A plain one is a
`role="separator"`; one carrying a label is not, because a separator with words inside it tells a
screen reader two contradictory things at once.

`WxSpace` is the even gap between things — the answer to the margin that would otherwise be added
to a button "just this once".

The shell is `WxContainer` with `WxHeader`, `WxAside`, `WxMain` and `WxFooter`, each rendering the
element it is named after, so a page has real landmarks. The bars are chrome, and padded like it:
10px, which is the gutter the sidebar's icons stand in, so a toggle in the header sits exactly
above the icons below it. The sidebar collapses to a rail, the main
column takes a reading width, and either can scroll on its own while the chrome stays put. The same
five parts make the other shape an admin panel takes: a horizontal menu in the header and no
sidebar at all, with the whole width left to the content.

`useResponsiveShell` is the rule those shapes follow — the full sidebar, an icon rail under 1024px,
a drawer behind a burger under 640px — out of one measurement and two thresholds. It returns
`layout`, `collapsed`, `showAside`, `drawerOpen`, `toggle` and `close`, so one button in the header
collapses and expands the sidebar while the sidebar is on the page, and opens the drawer once the
menu has left it — which is also when a burger is the right icon for it, and not before. The width
chooses the shape rather than holding it: on a tablet the sidebar starts as a rail and the button
still expands it in place, since the reader can see what they are expanding.

The two answers that button can give are not kept the same way. Closing a sidebar is a decision: it
holds at every width, and with `persist` across reloads, under a key in `localStorage`. Opening one
only says "not collapsed, here", and is let go as soon as the screen changes size class — otherwise
a sidebar opened on a desktop would be sitting there on a tablet over a screen with no room for it.
Like the
grid, it measures an element rather than the viewport, which is what makes a shell inside a preview
or a split screen behave like the narrow thing it is; `shellLayoutFor` is the same rule as a pure
function, for a page that would rather drive the state itself.

`WxRow` and `WxCol` are the 24-column grid, and its breakpoints measure the row rather than the
window: `md` means "from 768px of row", so the same grid stacks inside a 400px drawer and spreads
across a wide screen without being told which it is in. Every width a column is given is published
as a CSS variable and the stylesheet holds one `@container` rule per breakpoint, each falling back
to the one below it — four rules instead of the several hundred a class-per-span grid ships, and a
column that can still be adjusted from the outside. The gutter is padding on the columns pulled
back by a negative margin on the row, which is what keeps `span="12"` an honest half.

`WxMenu` with `WxMenuItem`, `WxSubmenu` and `WxMenuGroup` is the navigation: a sidebar or a bar, an
icon rail, `accordion`, and a branch that expands itself around the active entry. A branch opens
inline where there is room and as a flyout where there is not — a bar, or a collapsed rail — and
the branches inside a flyout open inline in the same panel, so three levels deep is still one panel
rather than a chain of them across the screen. It renders a list of links and buttons rather than
`role="menu"`, whose keyboard model promises a desktop application menu that admin navigation is
not.

`WxBreadcrumb` and `WxBreadcrumbItem` are the trail above a title; a crumb that links nowhere is
recognised as the page you are on and gets `aria-current`.

`WxScrollbar` is native scrolling, themed: `scrollbar-color` where it is honoured and
`::-webkit-scrollbar` where it is not, with the scrolling element and `scrollTo`, `scrollToTop` and
`scrollToBottom` exposed for a log that follows its own output.
