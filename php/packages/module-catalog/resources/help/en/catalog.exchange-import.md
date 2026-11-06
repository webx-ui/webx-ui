---
title: Import products
guide: catalog#import-and-export
---

# about

Brings a CSV or XLSX file into the catalogue in three steps: the file, its columns, and how to write
it. Each row is saved like a product form, with the same checks. Run a check first: it shows every
error and writes nothing.

# howto

## How to run an import safely

1. On the «File» step, upload the file or give its address, and press «Read the file».
2. On the «Columns» step, say which column of the catalogue each column of the file goes into.
   Choose «Do not import» for what you do not need.
3. On the «Settings» step, choose how to find products and which rows to take.
4. Type a name in «Save as profile» and press «Save», so you need not repeat all this.
5. Press «Check without writing». The check opens in «Exchange»: read its errors.
6. Press «Import» again, choose the profile, give the file and press «Run the import».

## How to update only the prices

1. On the «Columns» step, keep the key column and the price, and choose «Do not import» for the rest.
2. In «Rows of the file», choose «Only update products that are found».
3. Leave «Empty cells clear the field» off, so that a gap in the file does not wipe a price.

## How to take off the site what a supplier no longer sells

1. In «Products that are not in the file», choose «Take them off the site».
2. In «Among», choose «Products of the categories the file has», unless the file is your whole
   catalogue.
3. Run a check first, and read how many products it would take off.

## How to import the same file again in one step

1. On the last step, type a name in «Save as profile» and press «Save».
2. The next time, choose this profile on the first step, or press «Import with it» in «Profiles».

# fields

## key

Choose what never changes between your file and the catalogue. With the article number, a product
whose number was changed by hand will look new to the import. Buyers do not see this choice.

## mode

The safest way to bring in a price list is «Only update products that are found»: an unknown row
cannot create a half-filled product on the site by accident.

## empty_clears

Be careful: when it is on, an empty cell wipes that field on the product, price included. Switch it
on only if empty cells in your file really mean «there is nothing here any more».

## create_missing

Handy for a first big import. For regular updates leave it off: a typo in a category name would
otherwise create a new category on the site instead of giving an error you can see.

## absent

Choosing «Take them off the site» can hide a large part of the catalogue if the file is incomplete,
so check the file first. Products taken off can be published again. If the run stops early, nothing
is taken off.

## absent_scope

«The whole catalogue» takes off the site everything the file does not name, so use it only with a
file of the full range. With a price list of one supplier, stay with the categories of the file.

## images

Matters only if the file has a column of picture addresses. «Replace the gallery» takes off the
pictures the cell does not name, so buyers will no longer see them. Pictures are downloaded after
the rows are written, so they may appear a little later.
