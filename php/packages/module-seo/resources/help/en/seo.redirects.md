---
title: Redirects
guide: seo#redirects
---

# about

A redirect sends whoever opens an old address on to a new one, and search engines move the old
address's place in the results over to it. A page renamed in the panel needs none: the site does
that by itself, and the «Automatic» tab shows it. The list opens with the busiest redirects first,
so the ones nobody uses any more sit at the bottom.

# howto

## How to send an old address to a new one

1. Press «New redirect».
2. Leave «Kind» on «Exact», type the old address into «Address» and the new one into «Destination».
3. Read the warning under the address, if one appears: a page still stands there and will stop
   opening.
4. Press «New redirect» at the bottom of the window.
5. Open the old address in the browser and see where it lands.

The buttons are there, and the rows open, if you may change SEO.

## How to move a whole section

1. Press «New redirect» and choose «Mask» in «Kind».
2. Type `/catalog/**` into «Address» and `/shop/$1` into «Destination».
3. Press «New redirect» at the bottom of the window.
4. Press «Check an address» and try two or three old addresses.

`$1` puts back what the stars caught, so `/catalog/brakes/pads` goes to `/shop/brakes/pads`.

## How not to make a loop

1. Before saving, press «Check an address» and type what you are about to write into «Destination».
2. If it says that address is redirected too, send your redirect straight to the final address.
3. After saving, open the old address in the browser.
4. If the browser says there are too many redirects, switch off «Active» on the row at once.

The site refuses only an address sent to itself in one row. Two rows pointing at each other, `/a` to
`/b` and `/b` to `/a`, are saved without a word, and so is a redirect from a page's new address back
to its old one, or a mask whose destination falls under itself, such as `/catalog/**` to
`/catalog/new/$1`. Visitors then get an error from the browser instead of the page.

## How to stop a redirect

1. Click the row, switch off «Active» and press «Save».
2. Or press «Delete» in the «···» menu of the row, if it will not be needed again.

The old address answers as it would without the redirect. A browser that has already followed a 301
may remember it for a while.

## How to find redirects nobody uses

1. Click the heading of «Last used» or «Hits» to sort the list by it.
2. Look at the rows that have not worked for a long time.
3. Delete those you are sure of in the «···» menu of the row.

Search engine robots count as hits too, so a low number is not yet a dead redirect. A deleted
redirect cannot be brought back.

# fields

## Address

The part after `?` counts: an exact `/old-page` does not catch `/old-page?utm_source=mail`, and a
mask `/old-page*` does, but it catches `/old-pages` as well. Capitals count too, and on a site with
the language in the address, each language needs its own row. The addresses of the panel are never
redirected, so a mask cannot lock you out.

## Destination

A path on this site or a full address of another. An exact redirect sends the visitor to this
address as written, and what they had after `?` is not carried over.

## Code

301 is for a page that has gone for good. 302 is for a while, such as a sale or a page under repair.
Browsers remember a 301, so if you are not sure yet, start with 302.

## Active

Switched off, the row stays in the list and the address answers as if it were not there.

## Points at itself

This mark stands on a row that sends an address to itself. Such a row does nothing: correct
«Destination» or delete the row.
