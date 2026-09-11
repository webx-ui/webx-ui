---
'@webx-ui/core': minor
---

Wave 2 is complete: seventeen components, and a toast queue

The ones the playground had been faking by hand:

- **Avatar** and **AvatarGroup** — a picture where there is one, initials where there is not. The
  colour comes from the name by default, so the same person is the same colour on every screen, and
  a list of twenty is scannable without anybody choosing twenty colours. The initials sit under the
  picture rather than instead of it, so a slow connection shows them and nothing moves when the
  photograph lands.
- **Empty** — what a list says when it has nothing in it, with room to say _which_ kind of empty:
  nothing yet, or nothing matched.
- **Descriptions** and **DescriptionsItem** — a record read rather than edited. A `<dl>` laid out as
  a grid, because the pairs are a list and not tabular data; each pair is two grid items rather than
  a box holding two, which is what lets labels in different rows line up. Bordered draws the lattice
  with the grid's own gaps, exact at any column count and under any span.

The rest of the wave:

- **Toast** — `useToast()` and `WxToaster`. The queue is module state on purpose: a toast almost
  always comes from a place with no view of its own. Message and Notification are one component
  here; the difference between them is a title and a corner.
- **Tooltip**, **Popconfirm**, **Loading**, **Skeleton**, **Progress**, **Result**, **Segmented**,
  **Steps**, **Image**, **Upload**, **Affix**, **Backtop**.

Three of those are ours rather than what the checklist suggested, for the same reason each time.
`Upload` does not upload: a component that owned the request would own the URL, the headers, the
CSRF token and the shape of an error, none of which it can know — so it collects and checks, and
the caller sends. `Affix` sticks with `position: sticky` and uses JavaScript only to report it,
which avoids both bugs a `fixed` switch inherits. `Segmented` is a radio group rather than a row of
buttons, so a screen reader announces "2 of 4" and the arrow keys work.
