<?php

declare(strict_types=1);

return [
    'agent-name' => 'Name the agent: the session name is what the stage and the journal record.',
    'project-archived' => 'The project :project is archived: no new tasks, and nothing of it is handed out.',
    'task-closed' => 'The task is :status and takes no more changes.',
    'task-started' => 'Work on the task has started; only its priority and its stages that nobody has taken may change. Not: :fields.',
    'task-not-draft' => 'Only a draft can be made ready; this task is :status.',
    'task-no-stages' => 'A task without stages has nothing to hand out. Add at least one stage.',
    'task-deleted' => 'The task is deleted.',
    'person-only' => 'Only a person can answer an agent\'s question.',
    'stage-elsewhere' => 'A stage named here belongs to another task, or is named twice.',
    'last-stage' => 'This is the last stage left. Cancel the task instead.',
    'stage-transition' => 'A stage cannot go from :from to :to.',
    'stage-not' => 'The stage has to be :status for this; it is :actual.',
    'not-holder' => 'The stage is held by :assignee: only that agent works on it.',
    'human-review' => 'This stage is checked by a person: an agent cannot accept it or send it back.',
    'own-work' => 'An agent does not review its own work.',
    'text-required' => 'The :what cannot be empty.',
    'stage-missing' => 'Every stage has to stay in the list once work has started; a stage nobody needs is cancelled. Missing: :stages.',
    'stage-new-at-end' => 'New stages go at the end.',
    'stage-started-order' => 'A stage that has started or was cancelled stays where it is.',
    'stage-started' => 'Stage :stage is :status and can no longer be changed.',
    'notify-not-configured' => 'Notifications are not set up: add the bot token and at least one chat, and save.',
];
