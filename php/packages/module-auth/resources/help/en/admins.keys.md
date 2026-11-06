---
title: Keys
guide: agents#a-key-for-a-program
---

# about

A key lets a program, such as a nightly import, work in this panel without a browser. It acts as the
administrator you choose and can never do more than they may. Here you issue and revoke everybody's
keys, and visitors to the site see nothing of this.

# howto

## How to issue a key

1. Press «Issue».
2. Fill in «Name» and choose the person in «Acts as».
3. In «What is allowed», choose «Only the scopes chosen below» unless the program really needs
   everything, and pick them under «Scopes».
4. Choose «Lives for» and press «Issue».
5. Press «Copy» and put the key into the program with the «Server address». Close the window only
   then.

The key is shown this once and never again. Lost means revoked and issued anew.

## How to replace a key that is running out

1. Look at «Expires» in the list. Nobody is warned: on the day, the program just starts being
   refused.
2. Issue a new key the same way and put it into the program.
3. Wait until «Last used» of the new key shows it working.
4. Press «Revoke» on the old one.

## How to stop a key

1. Find the key in the list by «Name» and «Acts as».
2. Press «Revoke» and confirm.

The program is refused from its next call, and a revoked key cannot be switched back on: to carry
on, issue a new one. What the program already did stays. The row stays in the list, greyed.

# fields

## Name

Say what the program is, so that whoever reads this list in a year knows which key can go.

## Acts as

Whatever the key does is signed with this person's name. You cannot issue a key for an account
switched off, for a super administrator unless you are one, or for somebody who holds more than you.
A role taken away narrows the key at once, and an account switched off or deleted stops it.

## What is allowed

«Everything the administrator may do» grows with their roles and with every section added later.
Chosen scopes are the safer choice. Either way a key is a program and not a person: what the panel
keeps for a person, it cannot do.

## Scopes

The list offers only what this person's permissions reach, so it changes with «Acts as». A scope
shown here is the part of the panel a program may touch, `pages:write` for example.

## Lives for

The longer it lives, the longer a key copied somewhere stays good. Take the shortest life the
program can bear, and write the date down where somebody will see it.

## Last used

Accurate to the minute. «Never» on an old key usually means nobody needs it, and it can be revoked.
