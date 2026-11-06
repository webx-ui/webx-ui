---
title: Payment methods
guide: payments
---

# about

This is the list of ways a buyer can pay, in the order the checkout shows them. A site starts with
two methods, payment on receipt and bank transfer, both switched off; online methods appear once a
payment gateway is connected to the site. While no method can be offered, the checkout skips the
payment step and the order is placed without a method.

# howto

## How to start taking payments without a gateway

1. Open «Payment on receipt» or «Bank transfer» in the list.
2. For a transfer, fill in the card «Settings»: the recipient, the account and the bank.
3. Switch on «Offered in the checkout».
4. Press «Save».

The badge of the method changes from «Off» to «On», and the buyer sees it in the checkout.

## How to add an online method

1. Press «Add method».
2. In the dialog, choose a type under the name of its gateway and press «Add».
3. Fill in the form on the right, with the keys of the gateway, and press «Save».
4. Switch on «Offered in the checkout» and press «Save» again.

If the dialog shows only «Offline», no gateway is connected to the site yet, and only its developer
can connect one.

## How to change the order of methods in the checkout

1. Take a row by its handle.
2. Drag it up or down, or into another group.
3. Let go: the order is saved at once, with no button to press.

Groups are dragged the same way. Without the right to change methods there is no dragging and no
«Add method»: the list can only be read.

## How to rename a group or change its pictogram

1. Press «Edit» beside the group.
2. Change «Title», «Description» or «Pictogram».
3. Press «Save».

A group of one method shows the buyer that method, with the pictogram of the group, and a group with
nothing to offer is not shown at all. Groups are added and removed only by the developer of the site;
on a site without groups the list is flat and has no «Edit».

## How to stop offering a method

1. Open the method in the list.
2. Switch off «Offered in the checkout».
3. Press «Save».

A method cannot be deleted: it stays in the list as «Off», ready to be switched on again.

## How to find out why a method is not offered

1. Look at its badges: «Off» is switched off, «No keys» has no gateway keys, «Enter the keys again»
   has keys the site can no longer read.
2. A red badge says the type or the gateway is not installed: only the developer can bring it back.
3. Open the method and check the card «When it is offered»: the delivery or the amounts may rule
   this order out.

The badge «Similar to…» does not hide anything: it warns that two methods in one group look the
same to the buyer, and different titles solve it.
