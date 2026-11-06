---
title: Audit runs
guide: audit#the-section
---

# about

The history of the audit: every run kept, newest first, with its scope, result and who started it.
Tick runs to see what has changed between them. Everything here is the audit's own data: visitors
do not see it, and deleting it does not touch the site.

# howto

## How to see what changed since the run before

1. Tick a finished run. Only finished runs can be ticked.
2. Under the table «Compare» shows one row per check: how many findings are new, fixed and
   persisting.
3. Press a row to see the addresses, and switch between the three kinds above them.

One ticked run is compared with the run before it of the same scope. A recheck is compared with the
last full run.

## How to compare two particular runs

1. Tick the two runs. The order does not matter: the older one counts as «Earlier run».
2. Read «Compare» under the table. A third tick replaces the first.

Compare runs of the same scope. If they differ, only the checks that the later run carried out can
count as fixed, and the panel says so above the comparison. Hidden findings are left out on both
sides.

## How to clear the audit data

1. If a run is going, cancel it on «Overview» or wait for it to finish.
2. In the menu «···» at the top press «Clear audit data».
3. Read the warning and press «Delete everything».

Every run goes with everything it found, and it cannot be undone: «Overview», «Findings», «Pages»
and «Outgoing» stay empty until the next run, and there is nothing left to compare with. Hiding
rules and settings stay. The menu item is there if you have the right to change things in the audit.

# fields

## Scope

«Full» opened the pages, «Quick» only looked at the settings, the address and the database, and
«Recheck» asked a page again. A recheck does not change «Overview» or «Findings».

## Status

«Done» counts. «Failed» stopped on an error, shown on «Overview» if it was the last run, and
«Cancelled» was stopped by hand. Neither can be ticked, and what they found is not shown.

## Health

The figure of that run. Compare it only between runs of the same scope: a Quick run has no pages and
starts from 100, so it says little next to a Full one.

## Severity

How many errors, warnings and notices the run found. A kind the run did not find is left out.

## Started by

«Administrator» started it from the panel, «Schedule» is the nightly run, «Console» was started
by your developer or by an update of the site, and «Agent» is an assistant connected to the panel.
