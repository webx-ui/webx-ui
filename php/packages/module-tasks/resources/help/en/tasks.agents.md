---
title: Agents
guide: tasks#the-watcher
---

# about

Here is the watcher — the program that starts agent sessions for the queue — as it last reported
itself: when, which sessions it runs, whether it is paused and why it waits. The page only shows;
the watcher's limits and the pause are on «Settings».

# howto

## How to see that the agents are working

1. Look at «Last heartbeat»: the watcher sends one every poll, about once a minute.
2. Look at «Sessions running»: each row is an agent, the stage it does and since when.

## What to do when the watcher is silent

1. Check that the computer the watcher runs on is on and online.
2. Look at its log there, or ask whoever looks after it.
3. When it is back, open the tasks under «In work»: a stage whose session stopped with the watcher
   stays with that agent until somebody presses «Release».

A person is told about the silence once, if «The watcher is silent» is on in the settings.

## How to connect a watcher

1. On the server of the site, run `php artisan webx:tasks:runner`. It makes the role «Task watcher»,
   the account «taskd» and prints a key.
2. Give the key to the watcher. Running the command again replaces the key: the old one stops
   working.

The watcher's own key takes stages and gives each session a key for that one stage; it cannot
report or accept anything itself.
