<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

/** What the base did with an event (spec §20.2); an outgoing request has none — the answer is its outcome. */
enum Outcome: string
{
    case Applied = 'applied';
    case Same = 'same';
    case Ignored = 'ignored';
    case Duplicate = 'duplicate';
    case Rejected = 'rejected';
    case Unmatched = 'unmatched';
    case Error = 'error';
}
