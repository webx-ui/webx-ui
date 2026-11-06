---
title: Audit findings
guide: audit#fixing
---

# about

Everything the last run found, one row per check, worst first; a row opens into its addresses. For
each one you decide: correct it on the site, press «Fix» where the panel can do it itself, or «Hide»
it if the site is meant to be that way. Nothing here changes the site until you press «Apply».

# howto

## How to find out what a finding means

1. Press «?» beside the check. It says what was found, why it matters and how to fix it.
2. Press the row to open its addresses.
3. Read what is written under each address, and open the address to see the page for yourself.

## How to correct a finding yourself

1. Read «How to fix it» under «?».
2. Make the change in the section it belongs to. Where the row has «Open in the editor», that button
   leads straight to the right place.
3. Run the audit again. The finding goes only when a run no longer finds it.

To check one page without waiting for a full run, press «Recheck» on its card in «Pages».

## How to use the «Fix» button

1. Open the row. «Fix» stands beside an address when the panel knows how to close that problem and
   you have the right to change things.
2. Press «Fix» and read the table: what changes, «Now» and «After».
3. Press «Apply». Nothing changes before that.
4. The address is marked «Fixed — waiting for the next run». Run the audit again to confirm it.

The change reaches the site at once, and visitors get it: a link in a text leads elsewhere, or an
address starts to redirect. A development stand's address replaced this way goes into the history
and can be rolled back there. If the dialog says there is nothing left to change, someone has
most likely edited that place already.

## How to hide a finding that is meant to be there

1. Press «Hide» beside the address.
2. In «Addresses» keep this address, type a mask such as `/search/**`, or empty the field to hide
   every finding of this check.
3. Look at the line that says how many findings of the last run this would hide.
4. Fill in «Why it is all right» and press «Hide».

The finding is still found, but it stops counting: in every run kept so far and in every run to
come. Hide what was decided on purpose, say a search page closed to search engines. Hiding a real
problem fixes nothing: visitors go on meeting it, and you stop seeing it.

## How to show a hidden finding again

1. Press «Filters» and choose «Hidden» in «New and persisting».
2. Open the check. Each address says who hid it and why.
3. Press «Show again».

This removes the whole rule: if it was a mask, everything it covered comes back.

## How to bring back the findings about pages

1. Open «Overview» and choose «Full».
2. Press «Run the audit» and wait for it to finish.
3. Come back here.

This list shows the last finished Quick or Full run. After a Quick run only the settings, the
address and the database are here. «Recheck» on a page changes nothing on this list.

## How to see only what has just appeared

1. Press «Filters».
2. Choose «New» in «New and persisting».
3. Narrow it down with «Any severity» or «Any group» if you need to.

# fields

## Severity

The worst of what this check found. An error is broken or dangerous, a warning works worse than it
should, and a notice is advice that does not touch «Health».

## New

The address was not in the previous run of the same scope. The rest, «Persisting», were there before
too and have not been corrected.

## By page and By link

Some checks of links can be read two ways: by the pages with the link, or by the link with all its
pages. By link is handier when one link in the menu stands on every page.
