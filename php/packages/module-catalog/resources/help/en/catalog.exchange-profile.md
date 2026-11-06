---
title: Exchange profile
guide: catalog#import-and-export
---

# about

The saved settings of one regular import or export. An import profile remembers which header of the
file goes into which column of the catalogue and how to write it. An export profile remembers which
columns to write and in what order.

# howto

## How to teach a profile a new column of the file

1. In «Columns of the file», press «Add a column».
2. Type the header exactly as it is in the file and choose the column of the catalogue.
3. Press «Save».

For a header it does not know, the import makes a guess, so look through the «Columns» step.

## How to change the order of columns in an export

1. In «Columns», move the columns up and down.
2. Press «Save». The next file has them in this order.

# fields

## name

Only you and your colleagues see it, in the lists of profiles and of runs. Name it after the source:
«Supplier price list, weekly».

## format

The type of file. An export profile writes this format. Spreadsheet programs open both; XLSX keeps
long numbers such as barcodes intact, so it is the safer choice for a file a person will edit.

## encoding

Set it only if the letters of an imported file come out as gibberish in the first rows. Old
accounting programs often write Windows-1251 or Windows-1252.

## delimiter

Set it only if all the columns of the file are read as one. Spreadsheet programs often separate CSV
columns with a semicolon, and a wrong guess shows at once on the «Columns» step.
