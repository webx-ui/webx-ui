---
title: SEO rules
guide: seo#writing-a-rule
---

# about

Here you say what one page or a whole group of pages tells search engines and social networks,
without opening each page. What a rule fills in wins over the page's own SEO card, and what it
leaves empty stays as it was. The sitemap above the list is the first place to look when a page is
missing from search.

# howto

## How to set the title of one page

1. Press «New rule».
2. Leave «Kind» on «Exact» and type the address into «Address»: `/catalog/brake-pads`.
3. Fill in «Title» and «Description».
4. Press «New rule» at the bottom of the window.
5. Press «Check an address», type the same address and press «Check».

The address works exactly as typed, so `/catalog/brake-pads?page=2` needs a rule of its own or a
mask. The buttons are there, and the rows open, if you may change SEO.

## How to keep a group of pages out of search

1. Press «New rule», and choose «Mask» in «Kind».
2. Type the address with stars: `/catalog/sale/**`.
3. On the «Advanced» tab, tick «Keep out of the index» under «Indexing».
4. Press «New rule».
5. Read the line «Left out» on the sitemap above the list.

Every address under the mask leaves the sitemap at once and search over the following days. Right
after saving, use «Check an address» on a page that has to stay in search.

## How to find out why a page is wrong or missing from search

1. Press «Check an address».
2. Type the address as the browser shows it, and press «Check».
3. Read from the top: a warning about a redirect means nobody reaches the page at this address.
4. Under «Sitemap», read whether the page is in the map, and if it is not, why.
5. Under «Matched rule» and «What the page will say», see which rule answers and what search engines
   get.

This check does not read `robots.txt`. If the page looks fine here and is still missing, read the
field «robots.txt» on the «SEO» tab of the settings.

## How to stop a rule for a while

1. Click the rule in the list.
2. Switch off «Active» and press «Save».

The pages go back to what their cards say at once. «Delete» in the «···» menu removes the rule for
good.

## How to add questions and answers to a page

1. Open the rule of that address, or make one: it has to be «Exact».
2. On the «FAQ» tab, press «Add a question» and fill in «Question» and «Answer».
3. Drag the questions into order and press «Save».

The tab is there only on a site with page FAQ turned on. Visitors read the questions on the page,
under its text, and search engines get them too. Many at once come from a file: «Import FAQ» in the
«···» menu above the list.

# fields

## Kind

«Exact» is one address, «Mask» is a shape of them, and «Regular expression» is for whoever can write
one. The site tries exact rules first, then masks, then regular expressions, and uses only the first
that fits. The list stands in that same order, so reading it from the top is reading what will
happen.

## Address

Write it as the browser shows it, without the name of the site, and mind the capitals: `/Catalog` is
not `/catalog`. On a site with the language in the address, `/en/catalog` is another address too. In
a mask `*` is one part between slashes and `**` is any number of them, so `/catalog/*` covers
`/catalog/brakes` and not `/catalog/brakes/pads`.

## Priority

It matters only when two rules of the same kind fit one address: the higher number wins, and with
equal numbers the older rule does. Visitors see nothing of it.

## Active

Switched off, the rule stops at once and stays in the list. A rule that is off answers for nothing.

## Heading

A rule changes the heading on the page only on the pages of catalog categories and brands, if the
site has them, and in templates built to read it. Everywhere else the page keeps its own heading.

## Indexing

A tick on a mask closes every page under it, which is the quickest way to lose a section from
search. An empty «Indexing» does not reopen a page: a page closed on its own card stays closed.

## Sitemap

Search engines read this map to find the pages, and it rebuilds itself after every save. «Rebuild»
is for what changes with no save, such as an article whose date has come. «Left out» counts the
pages that closed themselves from search, and it is worth reading after every wide rule.
