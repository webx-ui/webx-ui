---
title: Block type
guide: blocks#a-block-type
---

# about

Everything one type of block is made of: the template, the styles, the script, the fields editors
fill in, and the settings. Every save writes a new version, and the site keeps showing the published
one. Publishing changes the block on every page it stands on, so check the draft on the preview
first.

# howto

## How to change a block and publish it

1. Change the template, the styles or the fields, and watch the block on the preview.
2. Press «Save»: a new draft version appears.
3. Press «Publish» and read how many pages will get it.

Before publishing, the block is drawn on the sample and on the values of every page that holds it.
If it breaks anywhere, publishing is refused and names the page and the line, and the site stays as
it was.

## How to add a field editors fill in

1. On the «Fields» tab, add the field to the list. The form of the sample shows it right away.
2. Fill in its value under «Sample values».
3. On the «Template» tab, press the field's name under the editor to insert it where it should
   print.
4. Press «Save» and then «Publish».

Removing a field does not clear what pages already hold, it just stops being printed.

## How to go back to an old version

1. On the «History» tab, find the version.
2. Press «Restore» and confirm: it becomes the draft.
3. Press «Publish» to put it on the site.

## How to stop offering a block without taking it off pages

1. On the «Settings» tab, switch off «Offered to editors».
2. Press «Save».

Pages that have the block keep it, but it can no longer be added.

## How to delete a block

1. Open «Where it is used» and take the block off those pages.
2. If other blocks call it, remove those calls from their templates.
3. On the «Settings» tab, press «Delete» and confirm.

All its versions go with it, and this cannot be undone. While the block stands on a page or is
called from another block, it cannot be deleted.

# fields

## Kind

A block is what editors add to pages. A component is called from other templates, and editors do not
see it. A block standing on pages must be taken off them before it can become a component.

## Identifier

It is the name of the type in the content of pages and the prefix of its styles. Changing it
rewrites every page and region that holds the block. If another block calls this one, correct that
block first: until then the change is refused.

## Group

The section of «Add a block» the type stands in. Visitors do not see it.

## Description

Editors read it in «Add a block», and an AI agent working on the site reads it as an instruction.
One sentence on what the block is for serves both. Visitors do not see it.

## Icon

Marks the block for editors in the panel. Visitors do not see it.

## Style order

Matters only when the styles of two blocks collide on one page: the later one wins. Leave it alone
if nothing collides. Visitors see only the result.

## May hold

Works only for a block with a field that holds other blocks. It narrows what editors can put inside,
and a field with its own list takes priority.

## Allowed in

Stops the block from being put where it would look broken, such as a column block inside a narrow
card. «The page itself» means the top level of a page.

## Per page

Useful for blocks that make sense only once on a page, such as a hero screen. Once the limit is
reached, an editor cannot add another one.

## Offered to editors

Off, editors no longer see the block in «Add a block», and the card says «Hidden from editors». It
is the safe way to retire a block: nothing changes for visitors.
