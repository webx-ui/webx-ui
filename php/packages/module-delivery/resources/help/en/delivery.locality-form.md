---
title: Locality
guide: delivery#a-shop-without-carriers
---

# about

One town, village or other place the shop can deliver to. A buyer finds it in the search, and points
and the courier's list refer to it. The same form opens in a shorter version when you add a town on
the spot from a point, a method or the popular towns.

# fields

## names

Write the bare name, with no word like «town» in front of it: the type has a field of its own. A buyer
finds the place by the start of its name in any of these languages. If a carrier is connected, a name
you changed stays as you wrote it.

## kind

The buyer does not see the type in the checkout. It sets where the place stands in the suggestions
until it has a weight of its own, and a place of another type does not count as a duplicate.

## country

It cannot be changed once the locality is saved, so check it before pressing «Create». A locality in
a country that is off reaches no buyer until the country is switched on in «Countries».

## region_id

Buyers see it after the name, as in «Town D, Region B», and that is how they tell places of one name
apart. It can stay empty. Places with one name in different regions are not taken for duplicates.

## lat

The buyer does not see it. If a carrier is connected, coordinates help tell apart places of one name,
and «Binding queue» shows the distance to them. Without a carrier it can stay empty.

## lng

Goes with «Latitude»: a distance needs both. Coordinates you set by hand are not written over by a
carrier's update.

## weight

The number comes from the type alone until a carrier adds one for each active point in the place: from
100 for the largest type to 10 for a village. A number you type stays whatever the points. Clearing it
later keeps the old number, so type a new one instead.

## active

A place that is off also leaves the popular towns, and a buyer cannot choose it, so nothing is offered
there. Points and the courier's list keep it, marked. Orders already placed do not change.
