# Roadmap

The component list is taken from Element Plus as a checklist of what an admin panel needs. Every
component is implemented in-house; the library in brackets is what the behaviour leans on.

Legend: ✅ done · 🚧 in progress · ⬜ planned

## Wave 1 — basics

| Component                                                 | Status |
| --------------------------------------------------------- | ------ |
| Button                                                    | ✅     |
| Input                                                     | ✅     |
| Card                                                      | ✅     |
| ButtonGroup                                               | ✅     |
| Icon                                                      | ✅     |
| Textarea                                                  | ✅     |
| InputNumber                                               | ✅     |
| Select (Reka Combobox)                                    | ✅     |
| Checkbox, CheckboxGroup                                   | ✅     |
| Radio, RadioGroup                                         | ✅     |
| Switch                                                    | ✅     |
| Form, FormItem                                            | ✅     |
| Tag (as Badge)                                            | ✅     |
| TagsInput                                                 | ✅     |
| Badge (count: Indicator)                                  | ✅     |
| Alert                                                     | ⬜     |
| Divider                                                   | ⬜     |
| Layout (Container, Header, Aside, Main, Footer, Row, Col) | ⬜     |
| Space                                                     | ⬜     |
| Tabs (Reka)                                               | ⬜     |
| Menu / Sidebar                                            | ⬜     |
| Breadcrumb                                                | ⬜     |
| Link                                                      | ✅     |
| Text, Heading, Prose                                      | ✅     |
| Scrollbar                                                 | ⬜     |

## Wave 2 — data and overlays

| Component                                                         | Status |
| ----------------------------------------------------------------- | ------ |
| Table (Laravel `Paginated`)                                       | ✅     |
| Pagination                                                        | ✅     |
| Dialog (Reka)                                                     | ⬜     |
| Drawer (Reka)                                                     | ⬜     |
| Dropdown (Reka)                                                   | ✅     |
| Tooltip (Reka)                                                    | ⬜     |
| Popover (Reka)                                                    | ⬜     |
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
| Collapse                                                          | ⬜     |
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

MediaLibrary / Gallery, Repeater, Markdown, LinkPicker, BlockPicker, SortableList
(`vue-draggable-plus`), and `SchemaRenderer` in [`@webx-ui/schema`](/guide/#packages).

## Beyond components

1. `@webx-ui/adapter-laravel` — paginator, 422 validation errors, sort/filter query parameters.
2. CMS building blocks on top of `core`.
3. An in-admin component editor: fields defined as JSON plus a Blade template and CSS, generated
   into files by Laravel.
