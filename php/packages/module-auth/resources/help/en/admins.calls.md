---
title: Agent calls
guide: agents#seeing-what-happened
---

# about

Every call any agent or program made in this panel, newest first: who it acted as, which tool it
used and how that went. Refusals and dry runs are here too. This is a record to read: nothing here
can be changed or deleted, and visitors to the site do not see it.

# howto

## How to see what an agent changed

1. Open «Filters» and choose the person in «Administrator».
2. In «Outcome», choose «Answered only» to drop refusals and dry runs.
3. Open a row and read «The agent's request»: what it passed to the tool.

The answer the agent got is not kept, so this shows what it asked for and not what it was shown.

## How to find out why an agent was refused

1. In «Outcome», choose «Refused only».
2. Open a row and read «Refused with».
3. Fix it where the cause is: a read-only connection is connected again without «Read only», and a
   missing permission is a matter of the person's roles.

An agent that keeps being refused for what it may not do is worth a look: end it on the
«Connections» or «Keys» tab if nobody knows why.

# fields

## Who

The administrator the agent acted as: what it did counts as theirs. «Administrator #7» is somebody
deleted since, and «Local server» is a call made on the server itself, with no person behind it.

## Tool

What the agent used, by the name it has for agents. The first word is the section: `pages_update`
changes a page.

## Outcome

«Answered» means the tool ran. «Dry run» means the agent only asked what would happen, and nothing
changed. «Refused» means nothing was done: the door turned it away or the tool failed.

## Client

The name of the program behind a connection, as the program gave it, so it proves nothing. A key has
no client, and its row shows a dash.

## The agent's request

Anything named like a password, a token or a key is blanked before it is written down. Calls are
kept for 90 days, unless the site is set up otherwise, and then they go.
