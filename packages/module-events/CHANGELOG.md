# @webx-ui/module-events

## 0.1.3

### Patch Changes

- Updated dependencies [2ba4290]
  - @webx-ui/module-admin@0.20.0

## 0.1.2

### Patch Changes

- Updated dependencies [d1ff63d]
  - @webx-ui/module-admin@0.19.0

## 0.1.1

### Patch Changes

- Updated dependencies [50ea8a5]
- Updated dependencies [85b8fca]
  - @webx-ui/core@0.34.0
  - @webx-ui/schema@0.6.2
  - @webx-ui/module-admin@0.18.1

## 0.1.0

### Minor Changes

- 7c0fac5: `module-events` for agents and for a first look: eight `events_*` tools and the shared
  `event_categories_*`, with "Duplicate" as `events_duplicate` and every date read as ISO 8601 in
  the site's timezone when it names no offset; `events://catalog` with the events to come per
  category and a count of the past ones; and demo content whose dates are counted from the moment it
  is seeded. In the panel an event of days now shows the same calendar days in every browser, west
  or east of the site.
- 7c0fac5: The events section of the panel: a page of events split into upcoming and past, with the past
  ones muted in "All"; the editor with its Event, Settings, SEO and History tabs, autosave into the
  draft, the revision and publishing; "Duplicate" in the row menu and in the editor's bar, which
  opens the copy; the categories on the panel's shared category screens.
