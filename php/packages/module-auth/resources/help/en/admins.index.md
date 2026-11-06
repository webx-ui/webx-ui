---
title: Administrators
guide: admins#the-section
---

# about

Everybody who signs in to this panel is listed here, with their roles and the last time they signed
in. Here you add a person, give them a new password and switch an account off. Visitors to the site
see nothing of this section.

# howto

## How to let a new person into the panel

1. Press «Add».
2. Fill in «Name», «Email» and «Password».
3. In «Roles», choose what they may do. The menu shows them only the sections their roles open.
4. Press «Save».
5. Pass on the address of the panel and the password yourself: nobody sends them a letter.

## How to give somebody a new password

The sign-in screen has no link for a forgotten password, so somebody else has to do this. If the
only super administrator is locked out, ask whoever looks after the server.

1. Click the person's row.
2. Type the new password into «Password», at least 12 characters.
3. Press «Save» and pass the password on.

The person stays signed in wherever they already are, and their agents and keys go on working. If
the old password has leaked, follow the next scenario instead.

## How to shut out an account that may be in the wrong hands

1. Click the row, untick «Active» and press «Save». The panel, the agents and the keys of this
   account stop at once.
2. On the «Connections» and «Keys» tabs, end whatever you do not recognise.
3. On the «Agent calls» tab, look at what was done in this person's name.
4. Back in the row, type a new «Password», tick «Active» and press «Save».

A new password alone is not enough: it signs nobody out and ends no connection. Connections and keys
you did not end come back to life when «Active» is ticked, unless they have run out meanwhile.

## How to remove somebody for good

1. Press «Delete» in the «···» menu of the row.
2. Confirm. This cannot be undone.

Their access, agents and keys end at once. What they wrote stays on the site, and their connections
and keys stay in the lists as «Administrator #7», opening nothing. If the site has a blog, their
articles stay without an author and their author page goes. If they may come back, untick «Active»
instead.

# fields

## Photo

Shown in this list and in the corner of the panel. The field is here only if the site has a media
library, and without a photo the panel shows initials.

## Name

What the panel calls this person: in this list, in the menu and in the journals beside what they
changed.

## Email

This is what they sign in with. A change works at once and without a letter, so tell them first: the
old address stops letting them in. Two administrators cannot share an address.

## Password

Nobody can read it back, you included: a forgotten password can only be replaced. Left blank on an
existing account, it stays as it is.

## Roles

Several roles add up. A change works from the person's next click, for their agents and keys too.
You cannot change your own roles or give a role with a permission you do not hold, and the field is
here only if you may hand out roles.

## Panel language

Changes the panel for this person and nobody else, visitors included. «Follow the browser» takes the
language the person's browser asks for.

## Active

Unticked, the person is refused at once, even in a panel already open, and so are their agents and
keys. Nobody can switch off themselves, and you cannot edit an account that holds more than you.

## Super administrator

Passes every check whatever the roles say, in sections added later too. Only a super administrator
sees this tick or can touch such an account, and the panel always keeps one active. Do not connect
an agent under such an account: give it an account with a role.
