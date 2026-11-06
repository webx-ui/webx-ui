---
title: Audit settings
guide: audit#settings
---

# about

Where the audit goes, how much it opens, what it counts as a problem, when it runs by itself and how
much history it keeps. None of this changes what visitors see on the site. New values work from the
next run: what is on «Findings» now stays as it is until then.

# howto

## How to keep the audit from slowing the site

1. Leave «Requests at a time» at 2, or set 1 on a weak server.
2. Turn on «Run the audit every night» and set «Hour» to when the site has the fewest visitors.
3. In «Paths not to crawl» list the places with endless addresses, such as `/search/**`.
4. Press «Save».

## How to set up the nightly run

1. Turn on «Run the audit every night».
2. Choose «Scope». «Full» is what you want unless you run it by hand.
3. Set «Hour».
4. Press «Save».

The next morning the run is in «Runs» with «Schedule» beside it. If it is not, ask your developer
whether the site's scheduler is running.

## How to stop findings that do not suit your site

1. Find the threshold behind the finding: a page of short product cards, say, trips «Words on a page
   at least».
2. Change the number and press «Save».
3. Run the audit again: the old findings stay until a new run.

A threshold applies to the whole site. To let off one address or one section, use «Hide» in
«Findings» instead.

# fields

## base-url

Usually empty: the audit takes the site's main address. If you change it, every page has a new
address for the audit, so the next run shows all page findings as new and the old ones as fixed.

## resolve-to

A setting for your developer: leave it empty unless told otherwise. It makes the audit's requests go
to another server under the site's own name, so a wrong value means no answers, or a check of the
wrong server. Visitors are not affected.

## other-hosts

A link to a listed host counts as an error on pages and in drafts alike, and the host goes to the
top of «Outgoing» as «Stand». Each line covers exactly that host, not its subdomains. Common names,
such as ones starting with `dev.` or `staging.`, are recognised without being listed.

## pages-limit

Raise it if the site has more pages: what does not fit is not checked at all, and every language
version counts as a page of its own. A higher number makes the run longer and takes more room, and
the server carries the audit's requests for longer.

## concurrency

The audit shares the server with visitors, and its requests go one after another without a pause for
the whole run. More at a time makes the run shorter, but visitors get a slower site, and the hosting
may start refusing, which then shows up as false errors. If the site slows down during a run, lower
the number and save: a run already going picks it up.

## resources-limit

Pictures go first and pages of other sites last, so a low number leaves external links unchecked.
Whatever does not fit is marked «Not checked» on the page's card and cannot be found broken or
heavy; 0 turns this part of the audit off. Some of these requests go to other people's sites.

## exclude

The audit follows every link and pays no attention to robots.txt, so this is the only way to keep it
out: search results, filters with endless combinations, links that do something when opened. Such
paths get no findings and do not use up «Pages at most»; visitors still open them as usual.

## title-min

«Title» is the name of the page in search results and on the browser tab. A shorter one tells the
searcher too little. A finding here is a notice and does not touch «Health», and a page with no
title at all is a separate check.

## title-max

Search results cut a long title short, and the end of it is lost to the reader. It is a notice and
does not touch «Health». «Title, pixels at most» measures the same thing more exactly.

## description-min

«Description» is the text search engines may show under the title. A short one wastes the chance to
talk the reader into clicking. It is a notice, and a page with no description is a separate check.

## description-max

Whatever runs over is cut off in search results, so the point should come early. It is a notice and
does not touch «Health».

## thin-words

Search engines rarely show pages with little to say. Every word on the page counts, the menu and
footer too, but only pages open to search engines are checked. It is a notice: lower the number if
short pages are normal for your site.

## text-ratio

How much of the page's code is text a visitor can read. A low share means a page heavy with code and
light on words. It is a notice for pages open to search engines, and it does not touch «Health».

## url-length

The whole address counts, with the site's name. Long ones are cut in search results and awkward to
share. It is a notice: do not rename a working address just for this, because old links would break
unless a redirect is set up.

## ttfb-ms

«First byte» is the very start of the answer: until it arrives, the visitor stares at an empty
screen; 1000 is one second. The audit measures it from the site's own server, so a real visitor
waits longer. A slow page is a warning, and the check takes points off «Health».

## depth

Counted along links from the home page, or from the home of a language version. Visitors and search
engines rarely reach pages buried deep. It is a notice for pages open to search engines; a page no
link leads to is a separate check.

## image-kb

Heavy pictures hold the page up, most of all on a phone; 1024 KB is one megabyte. The audit goes by
the size the server states, and a picture without a stated size is not judged. It is a warning, and
the check takes points off «Health».

## external-links

Links to other sites on one page, the ones in the footer and menu included. So many usually mean a
list of links rather than a page. It is a notice: raise the number if a page like a list of partners
is meant to be long.

## title-px

This catches titles that fit in characters but not in width: capitals and wide letters take more
room, and search results cut by width. The panel's figure is an estimate within a few pixels. It is
a notice and does not touch «Health».

## vague-anchors

An anchor is the text of a link, and one that says nothing tells neither a visitor nor a search
engine where the link goes. The whole text of the link is compared, capitals aside, so «see the
offer» does not catch «see the offer on tyres». A match is a notice.

## schedule-on

The audit then runs without anyone having to remember it, but nobody is told: look at «Overview» in
the morning. A night run loads the server like one started by hand, and if a run is still going at
that hour, the night's run is skipped.

## schedule-scope

Empty means «Full». With «Quick», every morning «Overview» and «Findings» show only the settings,
the address and the database, and everything about pages disappears from them until a Full run. So
choose it only if you run the full audit by hand.

## schedule-hour

Pick the hour with the fewest visitors, because the server carries the audit's requests from then
until the run ends. The run starts on the hour.

## keep-runs

Only runs that are kept can be compared in «Runs». Lowering the number deletes the older runs when
the next run ends, not on saving. Visitors see nothing of it, and hiding rules stay.

## keep-snapshots

Past this number a run keeps its findings and loses its pages and links, which saves the most room.
«Pages» always shows the last full run, so a small number costs little. Rechecks are counted
separately, and visitors see nothing of it.
