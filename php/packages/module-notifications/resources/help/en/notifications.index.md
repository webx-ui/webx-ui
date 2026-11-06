---
title: Notifications
guide: notifications#channels
---

# about

Here you set up where the site writes when something happens. A channel is a Telegram bot with its
chats, or e-mail with its recipients, and the parts of the site that have something to say choose
the channels to say it through. «Log» shows what went out and what did not, and buyers see none of
this unless a channel writes to them.

# howto

## How to set up a channel

1. Press «New channel».
2. Choose the «Kind», and in «For the event» what it is for, or leave «Any event».
3. Press «Continue» and fill in the form of the channel.
4. Press «Send a test» to see the message arrive.
5. Switch on «Enabled» and press «Save». A new channel starts switched off.
6. Choose the channel where the site offers it: in the settings of a form, for example.

A channel nobody has chosen sends nothing, even when it is switched on.

## How to find out why a message did not arrive

1. Open the «Log» tab.
2. Under «Filters», choose «Failed» or «Skipped» in «Status».
3. Open the line: it says why, in the words of the service or of the site.
4. Put right what it names: a token, a chat, an address.
5. In the «···» menu of the line, press «Send again».

«Send again» is there for what failed or got stuck. A line marked «Test transport» went nowhere: the
site's mail is set to a test address, and nobody received the letter. Lines older than 90 days are
removed by themselves, unless the site is set up otherwise.

## How to stop a channel for a while

1. Open the «Channels» tab and find the channel.
2. Switch off the switch in its row. This happens at once, without saving.
3. Switch it on again when it is needed.

Whoever chose the channel keeps it. Meanwhile its messages are not sent and are not kept for later:
the log shows them as «Skipped», and they do not go out when the channel comes back.

## How to make a second channel like one you have

1. In the «···» menu of the channel, press «Make a copy».
2. Open the copy: it starts switched off, with the token and the passwords of the first one.
3. Change what differs, then switch on «Enabled» and press «Save».

The new channel is not chosen anywhere yet, so choose it where it should work.

## How to delete a channel and get it back

1. In the «···» menu of the channel, press «Delete».
2. Confirm.
3. To bring it back, open the «Bin» tab and press «Restore» in its «···» menu.

In the bin a channel sends nothing, and whoever chose it keeps it. «Delete for good» takes it away
from them too. Its lines stay in the log.

## How to keep a copy of the site from writing to real people

1. Open the panel of the copy, and the list of channels there.
2. Switch off the channels nobody should hear from.
3. For a channel you want to try, give it a test bot or your own address and press «Save».

Notifications go out from a copy of the site just as they do from the real one, and a note over the
list reminds you of it.
