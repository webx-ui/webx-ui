<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

/**
 * Who did something, as the journal records it.
 */
enum ActorType: string
{
    /** A person: in the panel, or through an assistant they connected and agreed to. */
    case Admin = 'admin';

    /** A program working under its own session name. */
    case Agent = 'agent';

    /** The module itself: the next stage opening after one is accepted. */
    case System = 'system';
}
