---
title: Task notifications
guide: tasks#notifications
---

# about

Here you choose which Telegram chats hear about the work and about what. Notices only tell: stages
move and agents work the same with everything here switched off, and a person then finds what waits
for them under «Waiting for me».

# howto

## How to connect Telegram

1. In Telegram, get a bot from @BotFather and paste its token into «Bot token».
2. Write to the bot once, then put your chat id into «Chats». Add the bot to a group if the whole
   group should hear.
3. Press «Save».
4. Press «Send a test message». It works only with everything saved.

A message comes to every chat, or the panel shows what Telegram refused and why.

## How to find out why notices stopped

1. Press «Send a test message» and read the answer.
2. If the test comes through, open a recent task and look for the system's line in «Journal»: a
   notice that could not be delivered after three tries leaves one there.
3. If there is no such line, ask whoever looks after the site whether its background work is
   running: notices leave through it, and the test does not.

## How to stop all notices

1. Press «Forget the token». Anything else changed on this page is saved with it.
2. If the token may have leaked, replace it at @BotFather too: otherwise it keeps working for
   whoever has it.

Nothing is sent any more, and work goes on as before. To start again, paste a token and press
«Save».

## How to stop agents taking new work

1. Switch on «Pause the queue» under «Watcher».
2. Press «Save».

No stage is handed out from the next poll on, and a watcher asking for one is told the queue is
paused. Sessions already running finish their stages. Switch it off and save to go on.

# fields

## token

The field shows dots at most: nobody can read the token back here, you included. Whoever holds it
can write in your bot's name.

## chats

Every notice goes to every chat: there is no sending by person or by project. A chat Telegram
refuses is named in the journal of the task, and until it is removed the others may get the same
notice more than once.

## review-human

Goes to all chats in spite of the word «my». Without it, such a stage just stands under «Waiting for
me» until somebody looks. On by default.

## task-done

Also comes when the remaining stages of a task were cancelled and at least one was accepted. A task
cancelled as a whole sends nothing. On by default.

## blocked

The stage stands still until somebody presses «Answer» in the panel: a reply in Telegram reaches
nobody. On by default.

## rework

Comes for stages sent back by agents and by people alike, so a busy queue makes it noisy. Off by
default.

## review-auto

Tells of a report before any agent has checked it, and there is nothing for you to do yet. Off by
default.

## stale

Only tells: the stage stays with the agent until somebody presses «Release» on the task. The check
runs every 15 minutes, so the notice can come a little after the limit. On by default.

## stale-hours

Counted from when the agent took the stage, from 1 to 168, and 6 unless changed. Set it above the
time a long stage honestly takes, or the notice will come for nothing.

## runner-silent

The watcher sends a heartbeat every poll; five minutes without one is a silence, told once, and the
next heartbeat ends it. The screen «Agents» shows it with or without this switch. On by default.

## runner-paused

A pause holds back new stages only: a session that has its stage keeps working, reports and is
checked as usual. The watcher reads the pause at its next poll, within a minute or so.

## max-workers

The watcher has its own ceiling, set where it runs; a bigger number here is cut to it, and the
screen «Agents» shows both. 2 unless changed.

## per-project

Sessions of one project work in the same folder and can get in each other's way. 1 unless changed.

## daily-sessions

Counted from midnight where the watcher runs. 0 is no limit. 40 unless changed.

## max-attempts

Counts every time the stage was sent back. A stage past the limit stays in the queue for a person to
look at; the watcher starts no session for it. 3 unless changed.

## claude-enabled

With every runner off the watcher starts nothing, as if the queue were paused. On by default.

## claude-args

Added to every Claude Code session the watcher starts. Wrong arguments make each session fail at
once, which the screen «Agents» shows as sessions that end in seconds.

## agy-enabled

A second runner, for trying it beside Claude Code. Off by default.

## agy-model

The model agy is started with; empty leaves agy's own default.

## agy-args

Added to every agy session, the way «Claude Code arguments» are.
