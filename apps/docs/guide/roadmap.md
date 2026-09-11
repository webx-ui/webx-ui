# Roadmap

The component list is taken from Element Plus as a checklist of what an admin panel needs. Every
component is implemented in-house; the library in brackets is what the behaviour leans on.

Legend: ✅ done · 🚧 in progress · ⬜ planned

## Wave 1 — basics

Complete. Every component below has a page of its own, a demo and tests.

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

| Component                                                         | Status |
| ----------------------------------------------------------------- | ------ |
| Table (Laravel `Paginated`)                                       | ✅     |
| Pagination                                                        | ✅     |
| Dialog (Reka)                                                     | ✅     |
| Drawer (Reka)                                                     | ✅     |
| Dropdown (Reka)                                                   | ✅     |
| Tooltip (Reka)                                                    | ⬜     |
| Popover (Reka)                                                    | ✅     |
| Popconfirm                                                        | ⬜     |
| Message                                                           | ⬜     |
| Notification / Toast (Reka)                                       | ⬜     |
| Loading                                                           | ⬜     |
| Skeleton                                                          | ⬜     |
| Empty                                                             | ⬜     |
| Result                                                            | ⬜     |
| Progress                                                          | ⬜     |
| Descriptions                                                      | ⬜     |
| Avatar                                                            | ⬜     |
| Image                                                             | ⬜     |
| Upload (Uppy / FilePond)                                          | ⬜     |
| DatePicker, TimePicker, DateTimePicker (`@vuepic/vue-datepicker`) | ✅     |
| DateRangePicker                                                   | ✅     |
| Steps                                                             | ⬜     |
| Collapse (as Accordion)                                           | ✅     |
| Segmented                                                         | ⬜     |
| Statistic, Countdown                                              | ✅     |
| Backtop                                                           | ⬜     |
| Affix                                                             | ⬜     |

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

Gantt ⬜ — the other way to look at the same work: a task per row, a bar across a timeline.
Decided, not started. It will be ours rather than a wrapper around `frappe-gantt` or
`vis-timeline`, because a Gantt is a header of dates and bars placed along it — a CSS grid, in
other words — and a wrapper would mean styling somebody else's SVG through their theme instead of
our tokens, which is the one thing this library does not do. The first pass reads: a sticky column
of task names, a scale of days, weeks or months, bars with progress, a marker on today, and
hovering or clicking a bar reported as an event. Dragging the dates and the arrows between
dependent tasks come after that, once the first pass has been lived with.

MediaLibrary / Gallery, Repeater, Markdown, LinkPicker, BlockPicker, SortableList
(`vue-draggable-plus`), and `SchemaRenderer` in [`@webx-ui/schema`](/guide/#packages).

## Beyond components

1. `@webx-ui/adapter-laravel` — paginator, 422 validation errors, sort/filter query parameters.
2. CMS building blocks on top of `core`.
3. An in-admin component editor: fields defined as JSON plus a Blade template and CSS, generated
   into files by Laravel.
