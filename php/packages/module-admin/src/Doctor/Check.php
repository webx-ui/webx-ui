<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor;

/**
 * One thing `webx:doctor` looks at.
 *
 * Checks are resolved from the container, so a check may ask for whatever it needs to look at
 * — the filesystem, the database, the registry of installed packages. None of them change
 * anything: a command that is run on a deploy has to be safe to run on a deploy that is going
 * badly.
 *
 * A check that finds nothing to say returns an empty list; a check that has several things to
 * say returns several, because "languages" is one subject and three sentences.
 */
interface Check
{
    /** @return list<Diagnosis> */
    public function run(): array;
}
