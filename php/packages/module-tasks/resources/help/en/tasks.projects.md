---
title: Projects
guide: tasks#the-section
---

# about

A project says where agents work: the folder, the repository, the branch and the rules they are
handed with every stage. Every task belongs to one project, so add a project first. Agents read all
of this, and visitors to the site see none of it.

# howto

## How to add a project

1. Press «New project».
2. Fill in «Name» and «Slug».
3. Fill in «Working folder», «Repository» and «Default branch», so that an agent knows where to
   start.
4. In «Rules for agents», write what must always hold: which checks to run, what never to touch.
5. Press «Save».

## How to change the rules of a project

1. Press «Edit» in its row.
2. Change «Rules for agents» and press «Save».

The next stage taken gets the new rules, in tasks already in work too. An agent already holding a
stage goes on with what it read when it took it.

## How to stop a project

1. Press «Edit» in its row.
2. Turn on «Archived» and press «Save».

Nothing of it is handed out from then on and no new tasks can be written in it. A stage already in
an agent's hands can still be reported and checked. The tasks stay in the list, and turning the
switch off brings the project back. Projects are never deleted.

# fields

## Name

What people see in the list of projects and in the form of a task. It appears in Telegram notices
too.

## Slug

Tasks are called by it, so a change renames every task of the project at once. Links and notes that
say `shop#12` stop matching, and so does anything set up to work on this project by that name.

## Working folder

A folder on the machine where the agent runs, not on this site. Agents are told it as written, and
nobody checks it exists.

## Repository

The address agents are given with the stage. The panel itself does not reach out to it.

## Default branch

Used by every task with an empty «Branch». It is read when a stage is taken, so a change here moves
the remaining stages of such tasks too, in the middle of the work.

## Rules for agents

The project's rules win over an agent's habits. Keep them short and true for every task: what
belongs to one task goes into that task.

## Archived

Shown once the project is saved. Archiving ends nothing at once: see how to stop a project above.
