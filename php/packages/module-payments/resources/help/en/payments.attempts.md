---
title: Payment attempts
guide: payments#online-payment
---

# about

An attempt is one try to pay an order: an online payment, or a payment a manager marked by hand.
Here you see what went through, what is stuck and where the money needs a person. On a site without
a payment gateway the list holds only the manual marks, and an order awaiting a transfer has no
attempt until then.

# howto

## How to mark a bank transfer as paid

1. Open the order: this list only shows attempts, the mark is made on the order.
2. Press «Mark as paid».
3. Fill in the dialog: the document, the day the money came, a note.
4. Confirm.

The mark cannot be taken back. The attempt appears here as «Paid», with the document and who marked
it.

## How to find the payment of an order

1. In the search, type the order number, the attempt id or the gateway reference, in full: a part
   finds nothing.
2. If there are many attempts, open the filters and choose a method or a period.
3. Click the row to open the attempt.
4. To work with the order, click its number: its tab «Payment» opens on this attempt.

## How to check an attempt that seems stuck

1. Open «In progress»: it holds attempts that are «Created», «In progress» and «Held».
2. Click the row.
3. Press «Check with the gateway». The button is there for «In progress» and «Held» attempts, if you
   may work with orders.

The site asks the gateway by itself as well, less and less often. If no answer ever comes, the attempt
ends as «Expired».

## How to see why a payment failed

1. Open «Failed»: it holds attempts that «Failed», were «Cancelled» or «Expired».
2. Click the row and read «Why».
3. Click an event under «Exchange events» to see what the gateway answered.

A payment that did not go through leaves the order awaiting payment, and the buyer can try again.

## How to settle a problem

1. Open «Problems»: «Paid twice», «Wrong amount», «Order cancelled» (money came for a cancelled
   order), «Hold expires», «Capture failed».
2. Click the order's number in the row.
3. Settle the money with the gateway: give it back or put the payment right by hand.
4. On the tab «Payment», open the attempt, press «Problem resolved» and confirm.

The problem stays in this list in grey and still counts in the number on «Problems». «Hold expires»
and «Capture failed» have no such button: they end with «Capture» or «Release the hold».
