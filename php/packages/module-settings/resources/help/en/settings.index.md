---
title: Settings
guide: settings
---

# about

Here lives what belongs to the site as a whole: its name, its logo, the ways to reach you and the
shortcodes that texts write in brackets. One «Save» keeps every tab at once, and while any value is
refused nothing is saved. Which tabs and cards you see depends on what the site prints, so a site
that shows no opening hours does not ask for them.

# howto

## How to change a phone number all over the site

1. Open the «Contacts» tab.
2. Under «Phone numbers», find the row and change «Number», country code included.
3. If people should call this number first, switch on «Main number» here and off on the other row.
4. Press «Save».

Every place that reads this tab shows the new number. A number typed by hand into a text stays as it
was, so it is better to keep such numbers in a shortcode.

## How to write a phone number into texts with a shortcode

1. On the «Shortcodes» tab, press «Add a shortcode».
2. In «Name», write `phone`.
3. In «Prints», leave «Its own value» and type the number into the field below it.
4. Press «Save».
5. In a text of the site, write `[phone]` in place of the number.

Visitors see the number as a link that dials. To change it on every page, change this one row.

## How to repeat a setting in texts

1. On the «Shortcodes» tab, press «Add a shortcode» and fill in «Name»: `site`, for example.
2. In «Prints», choose «A setting».
3. Start typing the name of the field and pick it from the list: «Project name», for example.
4. Press «Save» and write `[site]` in a text.

The text now follows the setting, with nothing more to update.

## How to set opening hours with a lunch break

1. On the «Contacts» tab, under «Opening hours», press «Add hours».
2. Tick the «Days», and set «Opens» and «Closes» for the morning: 09:00 and 13:00.
3. Press «Add hours» again, tick the same days, and set the afternoon: 14:00 and 18:00.
4. Press «Save».

Between the two rows the site says it is closed, and a day ticked in no row is a day off.

## How to tell visitors you are closed on a holiday

1. On the «Contacts» tab, under «Special dates», press «Add a date».
2. Choose the «Date» and write the «Occasion».
3. Leave «Closed all day» on. For a short day, switch it off and set «Opens» and «Closes».
4. Press «Save».

The date works only if «Opening hours» are filled in. It holds a year, so a yearly holiday has to be
added again next year.

## How to put your logo in the panel

1. Open the «Branding» tab.
2. In «Logo», choose a picture, a wide one that reads well when small.
3. In «Mark», choose a square version for the collapsed menu.
4. Press «Save». The corner of the panel changes at once, with no need to reload the page.

## How to save when a value is refused

1. Look at the field that is marked: the panel opens the tab that holds it.
2. Correct what the line under the field says, or delete a row left half filled.
3. Press «Save» again.

Until then nothing is saved, on any tab. The usual causes are a number with no country code, a
latitude with no longitude, and a row of hours with no days ticked.

# fields

## project-name

Left empty, the panel goes by the name it was set up with. Where the site has the «SEO» section,
visitors meet this name too: in the titles of pages, in shared links and in what search engines are
told. The name in the header of the site may be a separate one, which this field does not change.

## logo

The screen where an agent asks for access shows it as well. Whether visitors see it depends on the
design of the site, so do not count on it there. It is drawn 28 pixels tall, so thin lines and small
words will be lost.

## mark

Visitors do not see it. It stands for the logo where the menu is too narrow, because a wide logo
cut down to a square would be just its first letters.

## contacts-phones

Visitors usually find the numbers in the header, in the footer and in a contacts block, and with an
empty list these places print no number at all. Drag the rows to set the order: the main number
comes first and the rest follow as they stand here.

## phone-number

A number with no country code is refused, because the site does not guess the country: a wrong guess
would ring a stranger. Visitors read it just as it is typed here, spaces and brackets included.

## phone-label

Visitors read it beside the number in a list of numbers, and in the quick contact list beside the
chats of this number. With one number on the site it can stay empty.

## phone-messengers

Each tick puts a small icon beside the number, and it opens a chat with exactly this number. Tick
only the messengers that somebody really answers on this number.

## phone-primary

This is the number of the call buttons, and the one search engines are told about where the site has
the «SEO» section. Keep it on for one row: with two, the higher one wins.

## contacts-emails

The first address counts most: it stands above the header and in the quick contact list, and it is
the one search engines are told about where the site has the «SEO» section. Drag the one you want
there to the top. The footer and a contacts block print them all.

## email-address

It is printed as a link that opens the visitor's mail program, in plain sight for everybody, so
choose one that somebody reads. Anything that is not an e-mail address is refused.

## email-label

The ready parts of the site do not print this label, so visitors usually do not see it. It matters
only if the design of your site was made to show it.

## contacts-addresses

Visitors get every address in a contacts block, while the footer and the map show only the main one.
With an empty list these places print no address.

## address-text

A row with no text in any language is refused. A language left empty borrows the text of another
language of the site, so a visitor may read the address in that language.

## address-latitude

With «Longitude» it puts the pin on the map and makes the address a link. Copy both from any map
service: south of the equator the number is negative. One without the other is refused.

## address-longitude

West of Greenwich the number is negative. Without coordinates the map shows only the address, with
no map around it. Where the site has the «SEO» section, search engines are not told where the place
is.

## address-map

Use it for the page of your place on a map service: visitors go there by clicking the address. Only
a link starting with `http://` or `https://` is accepted. With no link and no coordinates the
address is plain text.

## address-primary

The footer and the map take this address, and so do search engines where the site has the «SEO»
section. Keep it on for one row: with two, the
higher one wins, and with none it is the first in the list.

## contacts-hours

Visitors see a line like «Open until 19:00» with the week under it, or the week as a table in a
contacts block. The line follows the clock of the place, not the visitor's. With an empty list
nothing is printed, and the site does not say it is closed.

## hours-days

A row with no day ticked is refused. On the site, days in a row with the same hours join up:
Mon–Fri.

## hours-opens

From this moment the site says it is open. A row with only one of the two times is refused.

## hours-closes

Up to this moment the site says it is open, and then it says when it opens next. Hours past midnight
belong to the row of the day they started on, so no second row is needed for them.

## contacts-hours-exceptions

On such a date the status follows this row, and the table lists the dates of the coming 30
days under the week. Without «Opening hours» the dates do nothing. A date that has passed is
harmless and can stay.

## exception-date

The date has a year in it, so a yearly holiday is added anew each year. Two rows on one date work
like two rows of hours: a break between them.

## exception-label

Visitors read it beside the date in the table of hours: the name of the holiday, for example. It can
stay empty, and then the date stands alone.

## exception-closed

Left on, the site says it is closed all that day and names the next opening. Switch it off for a
short day: «Opens» and «Closes» appear, and both have to be filled in.

## exception-opens

From this moment on this date the site says it is open, whatever the weekday usually says.

## exception-closes

On this date the site says it is open up to this moment. Earlier than «Opens» means past midnight,
just as in the ordinary hours.

## contacts-timezone

It decides when the site says «open»: a wrong zone puts the status hours off for every visitor. The
table of hours prints the times as typed either way. A name the world list does not know is refused.

## contacts-messengers

Visitors find them in the quick contact list, where the site has one, after the chats of the
numbers. The first chat of all can also become the button for writing on the bar of a phone screen.

## messenger-channel

It gives the row its icon and the name visitors read. A row without it is refused.

## messenger-url

A link of the messenger's own, such as `tg://`, is accepted too, and anything else that is not a
link is refused. Open it yourself once: visitors who land on a dead chat simply leave.

## messenger-label

Visitors read it after the name of the messenger in the quick contact list: «Telegram · Support».
Left empty, the name stands alone.

## contacts-socials

Each profile opens in a new tab. Where the site has the «SEO» section, search engines are also
told that these profiles are yours. With an empty list no icons are printed.

## social-network

It gives the row its icon and the name a screen reader says. «Other» gets a plain link icon, and
«Name» appears for it. A row without a network is refused.

## social-url

Only a full link starting with `http://` or `https://` is accepted. Give the public page of the
profile, the one visitors can open without signing in.

## social-label

This field exists only for the network «Other», because the rest are named by themselves. Sighted
visitors do not see it. Left empty, nobody hears what the network really is.

## shortcodes-data

Shortcodes work in texts: blocks, descriptions, articles, answers. In a title, an address or a code
the brackets stay as typed. Delete a row with care: every text that uses it then shows the bare
brackets to visitors.

## shortcode-name

Changing it later breaks the texts that use the old name: they show `[old-name]` to visitors until
somebody corrects them. If the site already has a shortcode of this name built in, that one prints
and this row does not.

## shortcode-source

Visitors see no difference. Take «A setting» when the value already lives in a field of these
settings and should not be typed twice. Take «Its own value» for everything else.

## shortcode-key

Only single fields are offered. The lists of the «Contacts» tab are not, and a shortcode cannot
print from them, so a phone number goes into a shortcode with its own value. A setting the site does
not have is refused.

## shortcode-value

Visitors see it in place of the name, and each language can have its own. What looks like a phone
number or an e-mail address becomes a link, and `[phone link=no]` prints it without one.
