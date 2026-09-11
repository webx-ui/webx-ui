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
[Cascader](/components/cascader) ✅ and [Timeline](/components/timeline) ✅ are done.

Still open: Tree, TreeSelect (`he-tree-vue` / Reka Tree), Transfer, Calendar, Carousel, Mention,
Anchor, Splitter, Watermark, Tour, Marquee.

## CMS-specific (not in Element Plus)

RichText (Tiptap) ✅ — see [RichText](/components/rich-text).
EntityCard ✅ — one record as a row, see [EntityCard](/components/entity-card).
Actions ✅ — the icon buttons at the end of a row, see [Actions](/components/actions).
Kanban ✅ — a board of columns cards are dragged between, see [Kanban](/components/kanban).
ListDetail ✅ — filters, records, the open one, and the rule for when there is room for all three:
see [ListDetail](/components/list-detail). An inbox and an orders screen are the same furniture.

SelectionArea ✅ — the rubber band over a grid or a list, see
[SelectionArea](/components/selection-area). A media library is unusable without it.

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

MediaLibrary / Gallery, Repeater, Markdown, LinkPicker, BlockPicker, and `SchemaRenderer` in [`@webx-ui/schema`](/guide/#packages).

## Beyond components

1. `@webx-ui/adapter-laravel` — paginator, 422 validation errors, sort/filter query parameters.
2. CMS building blocks on top of `core`.
3. An in-admin component editor: fields defined as JSON plus a Blade template and CSS, generated
   into files by Laravel.
