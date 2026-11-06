---
title: Channel
guide: notifications#channels
---

# about

A channel says where to write, who gets the message and what it says. The preview shows the message
as it goes out, on example values. «Check the connection» and «Send a test» work with what the form
has now, saved or not, and everything else waits for «Save».

# howto

## How to connect a Telegram bot

1. Create a bot at @BotFather and paste what it gives you into «Bot token».
2. Press «Check the connection».
3. Write to the bot, or add it to a group and write there.
4. Under «Chats», press «Find chats», tick the chats and press «Add the chosen».
5. Press «Send a test», then switch on «Enabled» and press «Save».

Telegram keeps what the bot has seen for 24 hours, so write to it shortly before searching. If the
search says the bot has a webhook, press «Add a chat» and type the chat id by hand.

## How to send letters through a mail server of your own

1. In «Send through», choose «Own SMTP server».
2. Fill in «SMTP server», «Port» and «Encryption» from the instructions of your mail service.
3. Fill in «User name» and «Password».
4. Press «Check the connection», then «Send a test».
5. Press «Save» and read the line «Letters leave through» above the form.

## How to write to the visitor who filled in a form

1. Choose the event in «For the event».
2. Under the recipients, press the button that starts with «From the event».
3. Write the message for the visitor, not for your team.
4. Press «Save».

The button is there if the event brings such an address. If a visitor left it empty or wrote
something else, the log shows a «Skipped» line, and nothing is sent to them.

## How to put values into the message

1. Choose the event in «For the event»: only then the panel knows its variables.
2. Click in «Template» and type `{{` to see them, or press a variable under the editor.
3. Watch the preview: example values stand where the real ones will go.
4. Press «Save». A variable the event does not have is refused.

## How to go back to the ready message

1. Press «Insert the default template» under the template.
2. If something is already written, press «Replace»: the subject and the template change in every
   language.
3. Press «Save». Until then the old words can still be kept by leaving the page.

The button is there for a channel of an event that has a ready message.

## How to check a channel before it goes to work

1. Press «Send a test».
2. In «To», choose one recipient or «Everybody on the list».
3. Press «Send» and read the result for each recipient in the window.

A test goes at once with example values, even from a channel that is switched off, and it writes to
real people. It is marked in the log. After ten tests in a minute the panel asks you to wait.

## How to replace a token or a password

1. Type the new one over the field: the old one is not shown, and the field says it is set.
2. Press «Save».
3. To remove it, press «Forget» with its name in the «···» menu, and then «Save».

A channel that is switched on cannot be saved without the token.

# fields

## name

You will see this name in the list, in the log and wherever a channel is chosen, so say where it
writes: «New orders to the managers' chat». Buyers never see it.

## provider

It decides which fields the form has, and it is set when the channel is created. To write through
another service, make a new channel. Buyers do not see this.

## event

Careful with a channel at work: whoever chose it for the old event gets «Skipped» lines saying the
channel is for another event, and a template with variables of the old event is refused on save.
Buyers do not see this.

## enabled

It will not switch on until the channel is complete: a token or a mail server, recipients, a
template, and a subject for a letter. What is missing is named when you try. While it is off, the
messages of the channel are lost, not kept for later.

## token

Whoever has the token can write in the name of your bot, so do not send it around. If the token is
changed at @BotFather, paste the new one here at once, or every message will fail.

## telegram_recipients

Every chat on the list gets every message of the channel, with all the values it carries, so check
who is in a group. A chat that has blocked the bot does not hold up the others. «Label» is for you
and the log.

## mode

Leave the site's mail unless this channel has to send from a mailbox of its own. Read «Letters leave
through» above the form after saving: a warning there means letters may reach nobody.

## host

The address of the mail server, from the instructions of your mail service: `smtp.example.com`, for
example. A channel with its own server cannot be switched on without it.

## port

Comes from the instructions of your mail service, together with «Encryption»: often 587 with TLS and
465 with SSL. With the wrong one, «Check the connection» reports an error.

## encryption

Take it from the instructions of your mail service. With «None» the password and the letters travel
unprotected, so keep it for a server inside your own network.

## username

The login of the mailbox on that server, often the whole address. Leave it empty only if the server
asks for none.

## password

Saved encrypted and never shown again, not even to you. Mail services often give a separate password
for programs, and that is the one to use here.

## from_address

Recipients see this as the sender. Use an address of your own domain: many mail servers refuse
letters from somebody else's address, or put them in spam.

## from_name

Recipients see it beside the address: the name of the shop, for example. It works only with
«Sender's address» filled in, and is ignored without it.

## email_recipients

Each recipient gets a separate letter and does not see the others. An administrator is written to at
the address of their account, whatever it becomes. One deleted or switched off gets nothing, and the
log says so.

## subject

The first thing a person sees in the mailbox, so put what matters first: the form or the number of
the order. A letter cannot go without it: a channel with no subject will not switch on.

## template

What recipients read, with real values in place of the variables. Telegram takes only a few tags and
refuses the rest on save, and a message over 4096 characters goes as plain text, cut to fit. A
letter that starts with a tag is treated as a layout, and any other as plain text.
