---
title: Feed
guide: catalog-feeds
---

# about

A feed is made of a template, a selection of products and a schedule; the file it builds waits at a
permanent address. Each feed holds its own copy of the template, so changing one touches no other. A
feed that would not make a valid file is not saved, and the form shows what is wrong.

# howto

## How to change what a product looks like in the file

1. Open the «Template» tab and edit the part you need. Typing `{{` opens the list of variables.
2. Check the preview, then press «Check on the server».
3. Save. The file changes with the next build.

## How to keep one product out of this feed

1. Save the feed, if it is new.
2. Open the «Exclusions» tab and find the product.

It stays in the other feeds. The file changes with the next build.

## How to tell a marketplace what your categories are

1. On the «Mappings» tab, choose the «Category list» and save the feed.
2. Set a value for a category. Everything under it takes the same value until you set its own.

A value is kept at once, and the file changes with the next build.

## How to change the address if it has leaked

1. In «Address», press «Reissue the key» and confirm.
2. Paste the new address into every platform that reads this feed.

The old address stops working at once, so platforms lose the file until you do.

# fields

## name

Only you see it, in the list of feeds. Name it after the platform: «Marketplace, main shop».

## format

Must match what the platform asks for. Presets set it for you, so change it only if you write the
template yourself.

## locale

Names and descriptions in the file come in this language. A product with no name in it is left out
of the file.

## currency

Prices in the file are in this currency. The platform must accept it, otherwise it will refuse the
prices. It cannot be changed here.

## is-active

Turn it off for a platform you stopped using. A platform that still reads the address will start
reporting an error.

## schedule

Decides how fresh prices and stock are on the platform. Most platforms read a file a few times a day,
so «Every hour» is rarely needed. «By hand» builds only when you press «Rebuild».

## schedule-at

A quiet hour at night suits most shops: the build does not get in the way, and the file is ready by
the morning.

## rebuild-on-change

Keeps prices on the platform close to the site without building every hour. On a catalogue that
changes all day, the file will be rebuilt often.

## filter

Decides which products go into the file. The number under it is what fits now, before products with no
price, picture or name in this language are left out.

## stock-map

Platforms know only their own words for availability. Say which of them each of your statuses means,
or a product out of stock can be advertised as in stock.

## taxonomy

Choose a list if the platform asks for its own categories: it helps products land in the right place
there. You can import a list from its address or as a file.
