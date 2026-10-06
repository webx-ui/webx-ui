---
'@webx-ui/module-audit': minor
'@webx-ui/php': minor
---

The audit keeps every heading of a page in order, and the page card shows them as a tree on a «Headings» tab: indented by level, a skipped level drawn where it should have been, and above the tree what breaks the usual order (no H1, several, not first, skipped levels, empty headings).

Two new page checks join the findings: `headings.h1_not_first` (another heading comes before the H1) and `headings.empty` (a heading with no text).

The overview of a page has a «Headings» line: whether the order is fine, how many there are, what breaks the rules, and a link to the map.
