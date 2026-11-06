---
title: Task
guide: tasks#a-task-s-life
---

# about

One task: what is to be done, its stages in order and everything that has happened to it. The
buttons are the moves allowed right now, and only those your permissions open. The status of the
task follows from its stages, so you change a stage and not the task.

# howto

## How to check a stage an agent has reported

1. Open the stage marked «In review» and read «Report» and «Result».
2. Compare them with «Criteria» of the stage and «Acceptance criteria» of the task.
3. Press «Accept» and confirm, and the next stage can be taken. An accepted stage cannot be sent
   back, so check first.
4. Or press «Send back» and write in «What has to change».

A stage sent back is taken before any ready one, its attempt counts up, and the agent that takes it
gets your remarks.

## How to answer an agent's question

1. Open the stage marked «Asked a question» and read «Question».
2. Press «Answer», write in «Your answer» and press «Send».

The stage stands still until somebody answers here. Then it goes back to the queue, and the answer
goes to whichever agent takes it next: the one that asked has ended its session. Write so that a
stranger understands.

## How to take a stage back from an agent that hung

1. Open the stage marked «In progress» and look at the time of its last line in «Journal».
2. Press «Release» and confirm.

The stage goes back to the queue, in the place it had. The agent loses it, and its report is refused
if it does turn up. What it already did in the branch stays there for the next agent to find. The
panel never does this by itself: a hung stage stays in progress until somebody releases it.

## How to skip a stage

1. Open the stage and press «Cancel the stage».
2. Confirm. This cannot be undone.

The next stage can start. If an agent held the stage, it loses it. The last stage left, with all the
others cancelled, cannot be cancelled this way: cancel the task.

## How to stop a whole task

1. Press «Cancel the task» in the menu at the top.
2. In «Note», say why if that will help later.
3. Confirm. This cannot be undone.

Every stage not done is cancelled, an agent at work can no longer report, and nothing of the task is
handed out again. Accepted stages stay done. Nothing is undone in the branches.

## How to change a task already in work

1. Press «Edit».
2. Change «Priority», or the stages nobody has taken yet.
3. Press «Save».

The specification and everything else about the task stay as they are, because the agents have
already read them.

# fields

## Report

What the agent says it did, in its own words. It does not prove anything by itself: «Result» says
where to look.

## Result

The branch, the commit or the address of the change, as the agent gave it.

## Attempt

Counts the times a stage went out, and grows with each «Send back». A high number often means the
assignment is unclear rather than the agent weak.

## Journal

Everything that happened, by whom and when. Lines cannot be changed or removed. A notice that could
not be delivered to Telegram leaves a line here.

## Comment

Stays in the journal for whoever opens it: an agent is not handed comments with its stage. To tell
an agent something, use «Send back» or «Answer».
