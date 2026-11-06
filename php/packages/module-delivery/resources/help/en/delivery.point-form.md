---
title: Own point
guide: delivery#a-shop-without-carriers
---

# about

One place where buyers collect orders: a store, a pick-up point or a kind of your own. A point needs
a town and an address, and the buyer sees it after choosing a pick-up method of its kind.

# fields

## kind

With no pick-up method of this kind in «Methods», the point reaches no checkout. A kind of your own is
made in the form of a pick-up method, with «New kind of points». If you change the kind, a buyer who
had chosen this point has to choose again.

## titles

The buyer sees it in the list of points and on the card of the chosen point. Left empty, the point is
called by its kind, and two such points in one town read the same.

## locality_id

Only a buyer who picked this town is offered the point. The search finds towns that are on; if the
town is not there, pick the line «Add» with the name you typed and fill in the short form.

## address

Required. The buyer reads it in the list of points and on the card of the chosen point, so write it
the way you would explain it on the phone. In a language left empty, the buyer sees the address in
another language.

## lat

The checkout draws no map, so the buyer does not see this number. It is only for a site with a map of
its own; otherwise leave it empty.

## lng

Goes with «Latitude»: one without the other puts nothing on a map. The buyer does not see it in the
checkout.

## phone

Shown on the card of the chosen point, and a tap on it calls the number. Left empty, the card has no
phone. Write it the way it is dialled: +800 1234 5678.

## active

This is the way to close a point without deleting it: it stays here with all its settings. A buyer
who had chosen it has to choose again. If it was the last active point of its kind in the town, the
pick-up method leaves that town.

## schedule

The buyer sees only today, on the card of the chosen point: «Until 18:00», «Opens at 10:00» or
«Closed». A day left at «Not said» shows nothing. Under «Exceptions», a date is stronger than its day
of the week, and a date with no hours is closed.
