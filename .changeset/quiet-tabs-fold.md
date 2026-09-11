---
'@webx-ui/core': minor
---

New components: `WxTabs` / `WxTab` and `WxAccordion` / `WxAccordionItem`.

A tab and its panel are written in one place — `<wx-tab value="seo" label="SEO">` carries
both the button in the strip and the content behind it — and the tabs open the first
usable one by themselves, including when the tabs arrive from a request. Three variants:
an underlined strip, a segmented control, folder tabs; horizontal or a column beside the
panel; hidden panels are dropped from the DOM unless `keep-alive` says otherwise.

The strip is built for a phone. It scrolls sideways instead of wrapping into a second row
that would push the panel down, the open tab is scrolled into view whenever it changes,
the end with more behind it fades, arrows show up for a mouse and stay out of the tab
order, and a column of tabs lies back down into a strip when the container gets narrow —
measured on the tabs themselves, so a narrow drawer on a wide monitor is treated the same
as a phone.

The accordion folds a long page into headed sections: one open at a time or several,
headers that are real headings at the level you choose, a chevron on either side, and an
`extra` slot for a switch or a menu in the header. The heading holds the button and
nothing else — `extra` sits beside it — so skimming a page by its headings reads out the
section titles and not the controls next to them, and pressing one of those controls does
not open the section under the pointer.
