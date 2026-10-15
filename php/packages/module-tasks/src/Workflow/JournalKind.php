<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

/**
 * What a line of the journal is.
 *
 * §4 names four; `question` and `answer` are the two halves of a block, kept apart from
 * comments because the next attempt at the stage is handed both of them (§5,
 * `tasks_stage_unblock`) and has to find them without reading every comment. `edit` is a change
 * to the task or its stages after it was written: an agent that read the brief an hour ago has
 * to be able to see that it is not the brief any more.
 */
enum JournalKind: string
{
    /** A status changed; `meta` has `from` and `to`. */
    case Status = 'status';

    /** An agent reported a stage; the body is the report. */
    case Report = 'report';

    /** A reviewer sent a stage back; the body is the remarks. */
    case Rework = 'rework';

    /** An agent stopped to ask; the body is the question. */
    case Question = 'question';

    /** A person answered the question; the body is the answer. */
    case Answer = 'answer';

    /** The task or its stages were changed; `meta.fields` and `meta.stages` say what. */
    case Edit = 'edit';

    case Comment = 'comment';
}
