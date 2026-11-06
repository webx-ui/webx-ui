---
title: Audit
guide: audit#running-it
---

# about

The audit checks the site the way a search engine and a careful visitor would: titles, broken links,
pictures, addresses, the site's own settings. This page shows what the last run found and starts the
next one. The audit changes nothing on the site by itself; visitors can only notice the extra
requests of a full run, and how many go at once is up to «Requests at a time» in the settings.

# howto

## How to run the audit

1. Choose «Quick» or «Full». Quick looks at the site's settings, its address and the database and
   takes seconds. Full does the same and then opens every page, so the bigger the site, the longer
   it takes.
2. Press «Run the audit».
3. Watch the stage on the card, or leave the page: the run goes on without you.
4. When it finishes, open «Findings» and start with the errors.

The button is there if you have the right to run the audit, and only one run goes at a time. If a
warning on top says the panel cannot start a run, or a run stays in «Waiting in the queue» for long,
ask your developer: that is a matter for the server.

This page and «Findings» show whichever run finished last, Quick or Full. A Quick run opens no
pages, so after it «Health» starts from 100 and everything about pages is gone from «Findings» until
the next Full run. To judge the whole site, look at a Full run.

## How to raise «Health»

1. Read the lines beside the circle: they say what the figure is made of.
2. Open «Findings» and take the errors first.
3. Begin with the errors of the whole site: each one costs ten points at once.
4. Then the pages with errors, then the checks with warnings.
5. Run the audit again to see the new figure.

## How to stop a run

1. Press «Cancel the run» on the card of the run.
2. Open «Runs»: the run is there as «Cancelled».

This page stays on the last finished run. What the cancelled one had found is not shown.

# fields

## Health

The share of pages without errors, less points for what is wrong with the site as a whole. A page
with three errors is lost once. Notices and hidden findings cost nothing, and the figure is the
panel's own: search engines know nothing of it.

## Errors, warnings, notices

An error is something broken or dangerous: fix it first. A warning works, but worse than it should.
A notice is advice: it does not touch «Health», and it is fine to leave one if it suits the site.

## New and Fixed

They compare this run with the one before it of the same scope: Full with Full, Quick with Quick.
«New» was not there last time. «Fixed» was there and is gone.

## By group

How many findings of each severity fall into each area of the site. It shows where the trouble sits:
in pictures, in links, in the server's settings. The rows themselves are in «Findings».

## Content searched

Besides opening pages, the audit reads the texts kept in the site's sections, drafts and hidden ones
too, looking for addresses of a development stand. A section in orange is installed but not read:
its published pages are still opened, but its drafts are not, and «Fix» cannot reach them.
