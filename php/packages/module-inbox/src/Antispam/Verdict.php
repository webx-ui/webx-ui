<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Antispam;

/**
 * What the guard decided about a submission.
 *
 * Two ways of saying no, on purpose. `Trap` is for the checks only a robot fails — it is
 * answered with a cheerful "thank you" and nothing is written, because telling a robot which of
 * its tricks was seen is the one thing that makes the next one harder to catch. `Reject` is for
 * the checks a person could plausibly trip, and it says so.
 */
enum Verdict
{
    case Pass;
    case Trap;
    case Reject;
}
