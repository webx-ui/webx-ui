<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;

/**
 * A check that says what it was told to.
 *
 * What the command does with a finding — the exit code, the JSON, the closing line — is the same
 * whichever check found it, and a real check needs a real site to find anything on.
 */
final readonly class FixedCheck implements Check
{
    /** @param list<Diagnosis> $found */
    public function __construct(private array $found = []) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        return $this->found;
    }
}
