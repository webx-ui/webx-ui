---
title: Payment method
guide: payments#a-shop-without-a-gateway
---

# about

A method is one line the buyer picks in the checkout: here are its words, when it is offered and
what it needs to work. The card «Settings» depends on the type: the bank details for a transfer, two
texts for payment on receipt, the options of the gateway for an online method. An online method also
gets the cards «Gateway keys» and «For the gateway account».

# howto

## How to set up a bank transfer

1. In the card «Settings», fill in «Recipient», «Account and bank» and «Payment term» in every
   language of the site.
2. In the purpose of payment, keep `{number}`: the buyer reads the order's number in its place.
   Empty, the field gives «Payment for order {number}».
3. Switch on «Offered in the checkout».
4. Press «Save».

The buyer sees the details after placing the order and on the order's page while it awaits payment,
and in the letter if the site sends letters about orders. Nothing makes you fill them in: a method
with empty details is offered all the same. An order keeps the details it was placed with, so a
change here reaches only new orders.

## How to set up payment on receipt

1. In «Delivery», choose «When the money is taken on receipt» if the method should stand only beside
   cash on delivery and pick-up at the shop's point.
2. In the card «Settings», change the title at the pick-up point if «Pay at the counter» does not
   suit you.
3. Change the note on the cash-on-delivery fee if the words of the site do not suit you.
4. Switch on «Offered in the checkout» and press «Save».

Such an order does not await payment: when the money is in, a manager opens the order and presses
«Mark as paid».

## How to connect an online method

1. In the card «Gateway keys», type the keys from your account with the gateway and press «Save».
2. Press «Test the connection». The button appears after the first save, and the test saves nothing.
3. If the gateway asks for it, copy «Notifications address» from the card «For the gateway account»
   into your account there.
4. Switch on «Offered in the checkout» and press «Save».

Where the gateway has a test mode, the badge in the list says «Test mode» or «Live»: check it before
taking real money.

## How to replace or remove a key

1. In the card «Gateway keys», press «Replace» beside the key, or «Remove key».
2. Type the new key, or confirm the removal.
3. Press «Save»: nothing changes before that.

Without its keys the method leaves the checkout. A key set in the environment of the site has neither
button, and only the developer can change it.

## How to hold the money and take it later

1. In the card «Settings», switch on «Hold the money, take it later» and press «Save». The switch is
   there only if the gateway can hold money.
2. When the buyer has paid, open the order and its tab «Payment».
3. Press «Capture» to charge the money, or «Release the hold» to give it back.

Until then the order shows «Money held». About a day before the hold ends, the attempt gets the
problem «Hold expires»; a hold nobody decided on ends as a payment that did not go through.

# fields

## titles

Give two similar methods different titles, or the buyer will not tell them apart. An order keeps the
title it was placed with, so a new title does not change old orders.

## descriptions

Use it for what helps to choose: a commission, a term, how the money is taken. Empty, the buyer sees
only the title. In payment on receipt with cash on delivery, the note on the fee stands here instead.

## enabled

A new method starts switched off, so saving it is not enough for the buyer to see it. Switching off
does not break a payment already under way: the gateway's answer about it still reaches the site.

## group_key

The buyer opens a group and picks a method inside it. «Default» keeps the method in the group of its
kind. Moved to another group, the method goes to the end of it, and dragging puts it in its place.

## delivery_mode

«When the money is taken on receipt» means cash on delivery or pick-up at the shop's point. With any
choice but «Always» the method stays hidden until the buyer has chosen a delivery, and the checkout
asks for that first. An order with no delivery at all passes «When the money is taken on receipt»
and «Except…», but not «Only…».

## delivery_methods

Nothing ticked under «Only…» means the method is never offered. A delivery method added later is not
ticked by itself, so look through this list after adding one.

## delivery_keys

This field stands in for the list of delivery methods on a site without a section for them: ask its
developer for the keys. A key with a typo matches nothing, so «Only…» hides the method and «Except…»
leaves it everywhere.

## min_amount

It is compared with what the buyer has to pay for the whole order, and the limit itself is included.
Below it the buyer simply does not see the method, and nothing says why.

## max_amount

Handy for keeping large orders away from cash, for example. The limit itself is included, empty means
no limit, and a value below «From the amount» is not saved.
