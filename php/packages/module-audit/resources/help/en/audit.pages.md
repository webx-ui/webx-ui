---
title: Audit pages
guide: audit#the-section
---

# about

Every address the last full run opened, with what it answered and what is written in it. Use it to
pick out pages by any property, such as those without a description, and to look closely at one
page. It is a snapshot taken during that run: later edits show here only after the next full run,
and nothing on this page reaches visitors.

# howto

## How to find pages with a certain problem

1. Press «Filters».
2. Choose the check in «With any findings or none»: only the pages it found stay.
3. For anything else press «Add a filter» and set a field, a condition and a value: for example
   «Description», «is empty».
4. To look for an address, type part of it in «Search by address».

Filters work together: a page has to meet all of them.

## How to get the list into a spreadsheet

1. Set the filters and the search you need.
2. In «Columns» tick what the table should have. This browser remembers your choice.
3. Press «Export CSV».

The file holds every page under the current filters and only the columns you ticked.

## How to look at one page

1. Press its row. The card opens on the right.
2. Go through the tabs: «Problems» has its findings, «Headings» its outline, «Incoming» and
   «Outgoing» its links, «Pictures», «CSS» and «JS» what it loads.
3. Press «Open the page» to see the live page beside it.

«Export» on the card downloads everything known about the page as a file for your developer.

## How to check one page after a correction

1. Open the page's card.
2. Press «Recheck» and wait: the audit asks this one address again.
3. Read how many findings are fixed and how many are new since the last full run.
4. Press «Compare the runs» to see which ones.

The checks that weigh a page against the whole site, such as broken links, pages without incoming
links, depth and the sitemap, do not run on one page. The card, «Findings» and «Overview» stay as
they were until the next full run. «Recheck» is refused while another run is going.

# fields

## Code

The server's answer. 200 means the page opens, 301 and 302 send the visitor elsewhere, 404 means
there is nothing there, 500 and above mean the site broke. «No answer» means the server did not
respond in time or could not be reached.

## Indexable

Whether search engines may show this page: it is a normal page that opens, no mark on it closes it
from search (`noindex`), and it does not name another page as the main one. «No» is not always a
mistake, but a page you want people to find has to say «Yes».

## Found through

How the audit came to this address: the home page, the sitemap, the address registry or a link from
another page.

## Depth

How many clicks from the home page. A dash means no link led to the address and the audit took it
from the sitemap or the address registry.

## Links in

How many other pages link here. A page nobody links to can only be reached from search or by its
address.

## First byte, ms

How soon the site began to answer, in thousandths of a second, measured from the site's own server.
A visitor waits longer, because the answer still has to travel to them.

## Closed in robots.txt

The site asks search engines not to visit this address. The audit opens it all the same, so that you
can see what is there.
