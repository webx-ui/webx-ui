---
title: Outgoing addresses
guide: audit#the-section
---

# about

Every host the site points at, a host being the name of a site in an address, such as `example.com`.
The main use is finding links left over to a development stand: a visitor who clicks one lands on an
unfinished copy of the site. Links on pages come from the last Full run, addresses in the database
from the last Quick or Full run, and nothing here changes the site.

# howto

## How to get rid of links to a development stand

1. Find the rows marked «Stand»: they are red and stand first.
2. Press a row. «On pages» lists the addresses with their pages and link texts, «In the database»
   the fields that hold the host.
3. For a field, press «Open in the editor» and correct the address. Or do it in one go: in
   «Findings» open «Links to a development stand in the content» and press «Fix».
4. A link that is on pages but not in the database comes from the site's design, a setting or a
   section the audit does not read: ask your developer about it.
5. Run the audit again to see the row go.

Check «Not published» beside a field too: a visitor does not see that text yet, but will once it is
published.

## How to find broken links to other sites

1. Press «Filters» and choose «External».
2. Look at the «Broken» column.
3. Press a row: the addresses that do not answer come first, each with its pages.
4. Correct or remove the link on those pages.

## How to teach the audit a new stand

1. Open «Settings» and add the host to «Other addresses of this site».
2. Press «Save».
3. Run the audit again: this list changes only with a new run.

# fields

## Own

The site itself. Nothing to do here: links to the site's own pages.

## Other mirror

The same site with or without `www`. A link to it sends the visitor through a redirect, or shows the
same pages under a second address if there is no redirect, so it is better to link to the main one.

## Stand

A copy of the site for development; a link to it is always a mistake on a working site. The audit
recognises the usual names and everything listed in «Other addresses of this site». Stands are not
asked for an answer, so «Broken» stays at 0 for them.

## External

Somebody else's site. Normal in itself: watch «Broken», and whether you recognise the host.

## Links, Pages, Broken

How many links lead to this host, from how many pages, and how many of them answered with an error
or did not answer. Past the limit of «Pictures, styles and external links at most» a link is not
asked and does not count as broken.

## Fields in the database

How many places in the site's texts hold this host, drafts and unpublished ones included.

## First seen

The date of the earliest kept run that found a link to this host. A host you do not recognise with a
recent date is worth a look: somebody may have just put that link on the site.
