---
title: Order statuses
guide: orders#statuses-and-milestones
---

# about

An order has two levels of status. Public statuses are what the buyer sees, such as «In progress»;
the stages of the shop inside them, such as «Being packed», are what managers work with. An order
always stands in a stage, and the public status above that stage is what its buyer reads.

# howto

## How to add a stage for managers

1. Press «Add a stage» in the public status it belongs to.
2. Fill in «Name» and pick a «Colour».
3. Save the stage. Managers can now choose it in «Change status».

Buyers notice nothing: they still see the public status.

## How to show buyers a new status

1. Press «New public status».
2. Fill in «Name» and «Description», the line buyers read on the page of their order.
3. Fill in «Code» and save.
4. Press «Add a stage» in the new group: orders stand only in stages, so without one nobody reaches
   this status.

## How to make a stage set a mark

1. In the menu of the stage, press «Edit».
2. In «Sets the mark», choose «Paid», «Received» or «Cancelled».
3. Save.

From then on, moving an order into this stage also sets the mark, once. A stage that sets «Cancelled»
asks the manager for a reason.

## How to move a stage to another public status

1. Drag the stage into the other group, or choose another «Public status» in its form.
2. Read how many buyers will see the new name.
3. Press «Move».

Its orders go along, their buyers see the new name at once, and no messages are sent.

## How to change the order of the statuses

1. Drag a public status or a stage to its new place.
2. The new order is saved by itself.
3. Open the page of an order on the site to check the scale.

Buyers see the public statuses as a scale, in the order of this list. A cancelling status is on the
scale only for an order that stands in it.

## How to retire a stage

1. To hide it from managers but keep it, open the stage and switch off «Offered when the status is
   changed».
2. To remove it, press «Delete» in its menu and read how many orders stand in it.
3. Press «Delete» in the dialog. The orders stay where they are and the list marks the status
   «deleted».
4. Changed your mind? Press «Bring back» under «Deleted» at the bottom of the page.

A stage new orders come into cannot be deleted or switched off; nor can the last active stage of a
public status that has orders.

## How to tell buyers when an order reaches a public status

1. If the site sends notifications, press «Edit» in the menu of the public status.
2. Choose channels in «Notify through».
3. Save.

The channels speak when an order enters this status from another one. A move between the stages of
one public status tells nobody.

# fields

## Sets the mark

A mark is a fact the rest of the site relies on, and it is not taken back when the order moves on.
If the order cannot take the mark, «Paid» on a cancelled order for example, the whole move is refused.

## Code

Change it with care: the templates of the site and the texts of messages can rely on it. Renaming
the status leaves it as it is, and buyers do not see it.

## Colour

Only managers see it, in the list of orders and on the order. Buyers see the name of the public
status without any colour of the stage.
