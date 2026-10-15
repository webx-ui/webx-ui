<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

/**
 * Who checks a reported stage.
 */
enum ReviewMode: string
{
    /** An agent with the review scope — the dispatcher — compares the report with the criteria. */
    case Auto = 'auto';

    /** Only a person: an agent's acceptance is refused however it is authorised. */
    case Human = 'human';
}
