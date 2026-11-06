---
title: Task form
guide: tasks#the-section
---

# about

Here you write what agents are to do. A saved task is a draft: nobody sees it at work and everything
in it can change. Once it is put to work, only the priority and the stages nobody has taken can
change, so write it fully first.

# howto

## How to write a task an agent can do

1. Choose «Project» and fill in «Task».
2. In «Specification», write what is needed and why, as if to a stranger.
3. In «Acceptance criteria», write how to tell the whole task is finished.
4. Press «Add a stage» for each step, and fill in «Stage», «Assignment» and «Criteria» for each.
5. In «Checked by», choose «A person» where you want the last word.
6. Press «Save», read it through on the task page, and press «Put to work» there.

## How to drop a stage from a task in work

1. Do not remove the row here: saving will be refused, because every stage has to stay on the list.
2. Go back to the task and press «Cancel the stage» on that stage.

## How to add to a task in work

1. Press «Add a stage» under the stages nobody has taken. New stages go at the end.
2. Fill it in and press «Save».

Stages already taken are listed apart, under «Started stages», and cannot change. If the
specification itself was wrong, this is the way to correct it: say so in the assignment of a new
stage, or cancel the task and write a new one.

# fields

## project

A task stays in its project for good: this cannot change after the first save. Archived projects are
not offered. The project decides the folder, the rules and the name the task goes by.

## title

People see it in the list, on the task and in Telegram, and agents read it first. It locks at «Put
to work».

## priority

The queue gives out sent-back stages first, then high, normal and low, and the oldest task first
among equals. Changes while the task is open, but an agent already holding a stage keeps it.

## branch

Agents get this name with the stage. The panel creates nothing: making the branch is the agent's
work, and it locks at «Put to work».

## spec

Every stage of the task gets all of this. It locks at «Put to work», because the agents have read it
by then.

## acceptance

The agent works towards these and the reviewer checks against them, along with the criteria of each
stage. They lock at «Put to work».

## stages

The next stage is handed out only when the one before it is accepted or cancelled. A stage removed
from a draft is gone for good. A draft saves with no stages, but «Put to work» refuses it.

## stage-title

Short and clear: it is what the list, the task and Telegram call this stage.

## stage-brief

The agent also gets the specification and what earlier stages reported, so do not repeat them here.
Write what is particular to this stage and where it stops.

## stage-acceptance

The agent checks these one by one before reporting, and the reviewer decides by them. Criteria that
can be checked get an honest answer, so prefer «the page opens at 360 pixels» to «looks fine».

## stage-review

With «A person», no agent and no key can accept the stage: it waits under «Waiting for me», and your
own connected assistant counts as you. With «An agent», it waits for a reviewing agent, and if none
is running it just stands, and «Waiting for me» does not show it.
