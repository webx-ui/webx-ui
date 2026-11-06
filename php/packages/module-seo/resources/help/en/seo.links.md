---
title: Interlinking
guide: seo#in-the-layout
---

# about

This tab is there only on a site with interlinking turned on. Interlinking puts a block of links on
a chosen page, usually under its text, to lead visitors and search engines on to other pages of the
site. A donor is the page that carries the block, an acceptor is the page a link leads to, and the
list has one row per donor.

# howto

## How to add links to a page

1. Press «New donor» and type the page's address into «Donor»: `/catalog/brakes`.
2. Fill in «Acceptor» and «Anchor», and press «Add a link» for each next one.
3. Drag the links into the order the page should show them.
4. Press «New donor» at the bottom of the window.
5. Open the page on the site and find the block.

If an address you gave redirects, its destination is saved instead: the window stays open and says
so, and «Done» closes it. The buttons are there, and the rows open, if you may change SEO.

## How to load links from a file

1. Press «Import» and then «Choose a file»: a CSV or XLSX table with the columns donor, acceptor,
   anchor and, if you like, heading.
2. Choose «Mode».
3. Press «Preview», and read the numbers and «Rows to look at».
4. Press «Apply».

Only rows without errors are written, and an address nothing answers at is an error here. Press
«Export as CSV» in the «···» menu first if you want a copy of what is there now.

## How to find and fix broken links

1. Open the filter over the list and switch on «Only with broken links».
2. Click a donor. Its broken links are red.
3. Change «Acceptor», or press «Remove the link».
4. Press «Save».

Visitors do not see a broken link: the page simply leaves it out. A renamed page is not broken,
because its links follow it, and «Written as» under the field shows the address first saved.

## How to give many donors one heading

1. Tick the donors and press «Set a heading» in the line that appears, or press «Set a heading» in
   the «···» menu and fill in «Address starts with».
2. Fill in «Heading». Left empty, the donors go back to the heading from the settings.
3. Press «Preview» and read how many donors it reaches. A prefix one part too short is a whole
   section of the site.
4. Press «Apply».

# fields

## Donor

A page has one block, so a second one for the same address is refused. It has to be an address of
this site. The block follows the page when it is renamed, and if the page is gone, the row says
«Leads nowhere» and nothing is shown.

## Heading

Visitors read it above the links. Left empty, the block takes the one from the «SEO» tab of the
settings, and the list says «From the settings».

## Active

Switched off, the block disappears from the page at once, and its links stay here for later.

## Acceptor

Start typing and pick the page from the suggestions. It has to be a page of this site, other than
the donor, and only once in a block. An address nothing answers at is saved and marked, but the page
does not show that link.

## Anchor

The words of the link: the visitor clicks them, and search engines read them as what the other page
is about. It is required, and up to 255 characters long.

## Mode

The first choice replaces the blocks of the donors in the file: links that are not in the file are
gone. The second adds the file's links after those a block already has. Donors that are not in the
file stay as they are either way.
