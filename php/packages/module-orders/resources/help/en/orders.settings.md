---
title: Order settings
guide: checkout#what-the-buyer-goes-through
---

# about

Here you set how orders are numbered, which status a new order comes into, who is told about what
and what the buyer is asked at the checkout. New values work for new orders: orders already placed
keep their numbers and their statuses. An administrator sees only the tabs their role opens, and
the tab «Checkout» is there only on a site with a checkout page.

# howto

## How to change the look of the order number

1. On the tab «Orders», in «Order number», fill in «Prefix», «Start» and «Width».
2. Look at the line «The next order gets» under them.
3. Press «Save».

If an order already has that number, saving is refused: change the prefix or the start.

## How to decide what the checkout asks

1. If you have the tab «Checkout», open it.
2. Under «Buyer's contacts», choose «Required», «Optional» or «Hidden» for each field.
3. Press «Save».

The first name is always asked. If neither the phone nor the e-mail stays required, saving is refused.

## How to ask the buyer something of your own

1. If you have the tab «Checkout», find the card «Extra fields».
2. Press «Add a field» by the place it should stand in: in the contacts, or in «Additional» before or
   after the comment.
3. Give it a label in every language, choose the type and, for a list, add the options.
4. Press «Save». The field is saved at once, without the button under the form.

The key and the type stay as they were saved: to change them, delete the field and add another.
Orders already placed keep what their buyers answered, whatever happens to the field later. Do not
ask for card numbers, passwords or documents: the answers go into letters and the history as they are.

## How to show a field only when a checkbox is ticked

1. Add the checkbox first, for example «Buying for a company».
2. Add the field and choose that checkbox in «Show». The field stands in the checkbox's place.
3. Drag the fields under the checkbox to change their order.

A checkbox with fields behind it cannot be deleted until they are deleted or shown always. Switched
off, it hides them too.

## How to ask buyers to agree with the terms

1. If you have the tab «Checkout», set «Agreement with the terms» to «Notice» or «Checkbox».
2. Choose «Terms page» and, if the site has one, «Privacy policy».
3. Press «Save».

## How to turn on «Buy in one click»

1. If you have the tab «Checkout», turn on «The button «Buy in one click»».
2. Change «The words after the order» if your shop calls back differently.
3. Press «Save». On a site with a catalogue, the button now stands on the product page.

## How to change who is told about an order

1. If the tab «Orders» has the card «Notifications», find the event there.
2. Add or remove channels. The channels themselves are made in the section «Notifications».
3. Press «Save».

An event with no channels tells nobody. A public status has channels of its own, on the page of the
statuses.

# fields

## number-prefix

Buyers see the number with its prefix in letters and on the page of the order. A new prefix goes to
new orders only: numbers given out stay as they are.

## number-start

Raising it is safe. Do not lower it on a working site: new numbers would climb into those given out,
and the site would stop placing orders when they meet. Saving refuses only a clash with the very next
number.

## number-pad

It only makes short numbers look even: with 6, the number 1001 becomes 001001. The prefix does not
count, and a longer number is never cut.

## initial-status

An order created in the panel comes here too. The tab «New» of the list shows exactly this status, and
a status chosen here cannot be deleted or switched off. Empty means the first stage of the list.

## initial-awaiting

It matters only if the site takes payment online: otherwise no order ever comes here. Empty means the
stage after the first one in the first public status.

## after-payment

«Mark as paid» in an order works here the same way as a payment online. Empty means the status of a
new order, so a paid order turns up on the tab «New» of the list.

## notify-placed

It goes for every order, wherever it was placed. At the start there are two channels here: «Order
accepted — to the buyer» and «New order — to the shop». Take the second one away and the shop hears
of no new order.

## notify-paid

It goes once, however the mark came: a payment online, «Mark as paid» or a status that sets it. A
top-up of a paid order sends nothing.

## notify-payment-failed

It goes after every failed try, and the order keeps waiting for its payment. Only a site with payment
online has such tries. A channel to the shop is useful here, so that somebody can call the buyer.

## notify-received

It goes when the order gets the mark «Received», by hand or through a status that sets it. There is no
channel here at the start, so buyers hear nothing until you choose one.

## notify-cancelled

It goes however the order was cancelled, one by one or from the list. There is no channel here at the
start. The text of a channel can include the reason the manager typed, so check that text before
sending it to buyers.

## notify-refunded

It goes after each refund, a partial one too, with the amount. There is no channel here at the start.
Reversing a refund entered by mistake sends nothing.

## notify-items-changed

It goes only if «Tell the buyer about the change» stays ticked when the lines are saved. The letter
shows what changed, the new total and «Message to the buyer», never the «Reason».

## notify-payment-requested

It never goes by itself: a manager sends it from «Send the buyer a letter» while the order owes money.
Without a channel that writes to the buyer, there is nothing to send.

## checkout-last-name

The same choice works for the recipient's last name. With «Hidden», orders carry the first name
only.

## checkout-phone

With «Hidden», a manager has no number to call on orders from the checkout. «Buy in one click» asks for
the phone whatever you choose here.

## checkout-email

If the site sends letters, they go to this address and carry the link to the page of the order. With
«Optional» or «Hidden», a buyer who leaves no e-mail gets neither.

## checkout-recipient

A buyer who ticks it has to give the recipient's first name and phone. They land in the card
«Recipient» of the order. With «Off», a manager can still fill that card in by hand.

## checkout-comment

With «Off», the step «Comment» leaves the checkout. The buyer sees the comment later on the page of
the order, and managers find it on the tab «Buyer». «Buy in one click» has no comment.

## checkout-terms

The same choice works in the dialog of «Buy in one click». With «Checkbox», the order cannot be placed
until it is ticked. With «None», the buyer sees nothing about the terms.

## checkout-terms-page

With neither this nor «Privacy policy», the agreement has no link and nothing about it reaches the
history of the order. For a page with published versions, the history keeps the number of the
version, and a new version makes a buyer who has not ordered yet tick the box again.

## checkout-privacy-page

Buyers see it beside the terms, and the history of the order gets a line about it too. A page that no
longer opens is left out by itself, without a warning.

## checkout-captcha

Every extra step costs some orders, and the checkout already has hidden protection against robots, so
turn it on when fake orders still come. The site refuses to save «On» until it has a captcha chosen.

## checkout-quick

On a site with a catalogue, the button appears on the product page by itself. The buyer's cart stays
as it was. In the list such orders are marked «One-click order», and «Placed through» finds them.

## checkout-quick-message

Write it for each language of the site. Promise only what the shop really does: if nobody calls back
in 15 minutes, change the words. The number and the sum of the order are added by themselves.
