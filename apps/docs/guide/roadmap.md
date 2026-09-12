# Roadmap

The component list is taken from Element Plus as a checklist of what an admin panel needs. Every
component is implemented in-house; the library in brackets is what the behaviour leans on.

Legend: ✅ done · 🚧 in progress · ⬜ planned

## Wave 1 — basics

Complete: every component below has a demo, tests and a page it is documented on — the four
typographic ones share [Typography](/components/typography), since a page each would be four pages
of the same paragraph.

| Component                                                             | Status |
| --------------------------------------------------------------------- | ------ |
| [Button](/components/button)                                          | ✅     |
| [ButtonGroup](/components/button-group)                               | ✅     |
| [Icon](/components/icon)                                              | ✅     |
| [Input](/components/input)                                            | ✅     |
| [Textarea](/components/textarea)                                      | ✅     |
| [InputNumber](/components/input-number)                               | ✅     |
| [Select](/components/select) (Reka Combobox)                          | ✅     |
| [Checkbox, CheckboxGroup](/components/checkbox)                       | ✅     |
| [Radio, RadioGroup](/components/radio)                                | ✅     |
| [Switch](/components/switch)                                          | ✅     |
| [Form, FormItem](/components/form)                                    | ✅     |
| [Card](/components/card)                                              | ✅     |
| [Tag (as Badge)](/components/badge)                                   | ✅     |
| [TagsInput](/components/tags-input)                                   | ✅     |
| [Badge (count: Indicator)](/components/indicator)                     | ✅     |
| [Alert](/components/alert)                                            | ✅     |
| [Divider](/components/divider)                                        | ✅     |
| [Layout (Container, Header, Aside, Main, Footer)](/components/layout) | ✅     |
| [Grid (Row, Col)](/components/grid)                                   | ✅     |
| [Space](/components/space)                                            | ✅     |
| [Tabs](/components/tabs) (Reka)                                       | ✅     |
| [Menu / Sidebar](/components/menu)                                    | ✅     |
| [Breadcrumb](/components/breadcrumb)                                  | ✅     |
| [Link](/components/typography#link)                                   | ✅     |
| [Text, Heading, Prose](/components/typography)                        | ✅     |
| [Scrollbar](/components/scrollbar)                                    | ✅     |

## Wave 2 — data and overlays

Complete. Two notes on where this list stopped matching the one it was copied from:

- **Message and Notification are one component.** The difference between them is a title and a
  corner, so both are [Toast](/components/toast) — one queue, one `WxToaster`, a `placement`.
- **Upload is ours**, rather than a wrapper around Uppy or FilePond. Those bring their own UI and
  their own theme, which is the one thing this library does not do; and since a component here
  never calls an API, there was no request logic to inherit either.

| Component                                                         | Status |
| ----------------------------------------------------------------- | ------ |
| Table (Laravel `Paginated`)                                       | ✅     |
| Pagination                                                        | ✅     |
| Dialog (Reka)                                                     | ✅     |
| Drawer (Reka)                                                     | ✅     |
| Dropdown (Reka)                                                   | ✅     |
| [Tooltip](/components/tooltip)                                    | ✅     |
| Popover (Reka)                                                    | ✅     |
| [Popconfirm](/components/popconfirm)                              | ✅     |
| Message (one line, [Toast](/components/toast))                    | ✅     |
| Notification / [Toast](/components/toast) (Reka)                  | ✅     |
| [Loading](/components/loading)                                    | ✅     |
| [Skeleton](/components/skeleton)                                  | ✅     |
| [Empty](/components/empty)                                        | ✅     |
| [Result](/components/result)                                      | ✅     |
| [Progress](/components/progress)                                  | ✅     |
| [Descriptions](/components/descriptions)                          | ✅     |
| [Avatar](/components/avatar)                                      | ✅     |
| [Image](/components/image)                                        | ✅     |
| [Upload](/components/upload) (ours)                               | ✅     |
| DatePicker, TimePicker, DateTimePicker (`@vuepic/vue-datepicker`) | ✅     |
| DateRangePicker                                                   | ✅     |
| [Steps](/components/steps)                                        | ✅     |
| Collapse (as Accordion)                                           | ✅     |
| [Segmented](/components/segmented)                                | ✅     |
| Statistic, Countdown                                              | ✅     |
| [Backtop](/components/affix#backtop)                              | ✅     |
| [Affix](/components/affix)                                        | ✅     |

## Wave 3 — as needed

ColorPicker ✅, Slider ✅, Rate ✅, [Autocomplete](/components/autocomplete) ✅,
[Cascader](/components/cascader) ✅, [Timeline](/components/timeline) ✅ and
[Transfer](/components/transfer) ✅ are done.

[Tree](/components/tree) ✅ — ours rather than `he-tree-vue`, because a drop is three zones on a
row and a `<mark>` in a label, and neither survives being themed through somebody else's CSS. It
brings the part most trees skip: `Alt` and the arrow keys move a node the same four ways a drag
does, which is also the only way to rearrange one on a touch screen.

[Table in tree mode](/components/table#rows-that-nest) ✅ — the same nesting inside `WxTable`:
indentation and a disclosure in the first column, the rest of the row still columns. Not a second
component — both run on `useTreeNodes`, so a drop means the same thing in each. It is the screen a
pages or categories module wants: the tree and the data in one pane, instead of a sidebar tree
beside a list of the same records.

It is lazy by design — `data` is the roots, `load(row)` is one level — and it gives up the two
things a table does to a flat list, because both destroy a structure: sorting and pagination.
Holding a dragged row over a closed branch opens it, fetching it if need be, so a move across the
tree is one drag. What it does not have is a keyboard equivalent for the drag, the way
[Tree](/components/tree) does; for that, and for a move across a long distance, a row wants a
**Move** action and a picker.

[TreeSelect](/components/tree-select) ✅ — the same tree as a form field, single or with
checkboxes. It is the "parent category" field, so it reads a key and reports a key: `parent_id` is
what the form sends, and the node comes along with the event for whatever the screen needs to show.

Still open: Carousel, Anchor, Splitter, Watermark, Marquee.

**Some day, maybe** — wanted, but nothing is waiting on them:

- Mention — the `@name` picker inside a text field.

**Dropped**, so that the list stays a list of things somebody is going to write:

- Calendar — a month of boxes to pick a date out of is what [DatePicker](/components/date-picker)
  already does, and does with a keyboard. What a calendar would add is a month of _events_, which
  is a scheduler and a different component with a different name.
- Tour — the step-by-step walk over a screen. An admin panel earns its explanations in the screen
  itself; when one truly needs a tour, that is a sign to go back to the screen.

## CMS-specific (not in Element Plus)

RichText (Tiptap) ✅ — see [RichText](/components/rich-text).
EntityCard ✅ — one record as a row, see [EntityCard](/components/entity-card).
Actions ✅ — the icon buttons at the end of a row, see [Actions](/components/actions).
Kanban ✅ — a board of columns cards are dragged between, see [Kanban](/components/kanban).
ListDetail ✅ — filters, records, the open one, and the rule for when there is room for all three:
see [ListDetail](/components/list-detail). An inbox and an orders screen are the same furniture.

SelectionArea ✅ — the rubber band over a grid or a list, see
[SelectionArea](/components/selection-area). A media library is unusable without it.

FileCard ✅ — one file in that library: a preview or a glyph, the name, and the few things that can
be done to it, see [FileCard](/components/file-card). It does none of them — renaming reports a
name, deleting reports a wish — so the same card sits in a library, in a picker and in a form
field. The icon set gained a `file-<extension>` family to go with it, and an extension nobody has
drawn gets the plain page with its own name written underneath.

ImageEditor ⬜ — what the card's edit action asks for and nothing yet answers: a canvas, crop
handles, aspect ratios, a rotation, and a blob at the end of it. Wanted for the media library;
deliberately not folded into the card, which is a tile and should stay one.

SortableList ✅ — a list whose order is the point, dragged by a grip or moved with the arrow keys:
see [SortableList](/components/sortable-list).

Gantt ⬜ — the other way to look at the same work: a task per row, a bar across a timeline.
Decided, not started. It will be ours rather than a wrapper around `frappe-gantt` or
`vis-timeline`, because a Gantt is a header of dates and bars placed along it — a CSS grid, in
other words — and a wrapper would mean styling somebody else's SVG through their theme instead of
our tokens, which is the one thing this library does not do. The first pass reads: a sticky column
of task names, a scale of days, weeks or months, bars with progress, a marker on today, and
hovering or clicking a bar reported as an event. Dragging the dates and the arrows between
dependent tasks come after that, once the first pass has been lived with.

MediaLibrary / Gallery, Repeater, Markdown, LinkPicker, BlockPicker, and `SchemaRenderer` in
[`@webx-ui/schema`](/guide/#packages).

## Not components

Two things here are reached from code rather than from a template, and are easy to miss in a list
of components:

- [Toast](/components/toast) — `toast.success('Saved')` from anywhere, including an HTTP
  interceptor or a store, with one `WxToaster` on the page to show them.
- [Dialogs from code](/guide/modals) — `confirm()`, and `openModal` / `createModal` for
  mounting any component and awaiting its answer. A picker reached as `await productBrowser()`.

## Beyond components

1. `@webx-ui/adapter-laravel` — paginator, 422 validation errors, sort/filter query parameters.
2. CMS building blocks on top of `core`.
3. An in-admin component editor: fields defined as JSON plus a Blade template and CSS, generated
   into files by Laravel.
