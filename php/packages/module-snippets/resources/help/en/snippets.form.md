---
title: Code
guide: snippets#a-code
---

# about

One code: what it is, where on the page it goes, and on which pages and in which languages. There is
no draft here: «Save» on an enabled code puts it on the site at once, mistakes included. If the code
holds `<html>`, `<head>` or `<body>`, it is saved all the same, and a warning above the form says
what to take out.

# howto

## How to make a counter wait for consent

1. Paste the code of the counter into «Code».
2. In «Cookie consent», choose «Analytics» for a counter or «Marketing» for advertising.
3. Press «Save».
4. Open the site in a private window: the counter starts only after you agree in the banner.

«Analytics» here is what the banner calls «Statistics».

## How to put a code on a part of the site

1. In «Only on», add the paths the code is for: `/blog` and `/blog/*`, for example.
2. In «Except», add the paths to leave out of them.
3. In «Languages», tick the languages if the code is not for all of them.
4. Press «Save» and open one page on the list and one off it to check.

## How to bring back an earlier version of a code

1. Find the save you want under «History», below the form.
2. Copy the old code from it: the old value is the struck-through one.
3. Paste it into «Code» and press «Save».

There is no button that does this for you, and the panel keeps the whole code from before every
save.

# fields

## name

You will look for this name in the list and in «History», so name the service and its job: «Visit
counter», «Online chat». Two codes of one service with like names are easy to mix up.

## place

Follow the instructions of the service, which usually say where the code goes. «Head, as high as
possible» starts earliest, and a heavy script there can make pages open more slowly. In the wrong
place a code may simply not work, and the service will not see it.

## code

Whatever is pasted here runs for every visitor and for every administrator who opens the site, so
take it only from a service you trust. A mistake can spoil pages, so open the site after saving. A
code cannot be longer than 64 KB.

## enabled

A switched-off code is kept, and no visitor gets it. A new code starts switched off. The switch in
the list works at once, and this one waits for «Save».

## consent

«Necessary» starts for everybody straight away, so keep it for what the site cannot do without: a
counter belongs in «Analytics», advertising in «Marketing». The banner offers the category by itself
once a code needs it. If the banner is switched off, the code runs for everybody.

## on_stage

Leave it off for counters and advertising, or a copy of the site will add visits that never
happened. Switch it on to try a code on a copy first. On the real site it changes nothing.

## only_paths

Visitors on other pages do not get the code, and «Except» goes first. `/blog/*` covers the pages
inside the blog, not `/blog` itself, so add both. Write a path without a slash at the end, or it
matches nothing.

## except_paths

Goes first when a page is in both lists. Write these the way you write «Only on»: from the start of
the path, with `*`, without a slash at the end. A path covers the page in all languages at once.

## locales

Handy for an online chat that answers in one language. Visitors on a page in another language do not
get the code, even if the page is covered by «Only on».
