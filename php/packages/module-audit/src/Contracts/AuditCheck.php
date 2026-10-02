<?php

declare(strict_types=1);

namespace WebxUi\Audit\Contracts;

use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;

/**
 * One check of the audit (§6). A module registers its own from its provider into
 * {@see AuditChecks}; the run asks each at the stage its `needs()` names.
 *
 * A check never crawls the site itself: by the time a `crawl` check runs, the snapshot is
 * collected, and it reads that. What it yields is findings — nothing found is a passed check.
 */
interface AuditCheck
{
    /** `title.duplicate` — also the key of its three texts in the dictionary. */
    public function id(): string;

    /** `config`, `host`, `hosts`, `page`… — the filter in the findings screen. */
    public function group(): string;

    /**
     * The worst it reports: `error`, `warning` or `notice`. A finding may be milder (a mirror
     * whose second name does not resolve is a notice of an error check); the health weighs the
     * check by this.
     */
    public function severity(): string;

    /**
     * What has to be collected before it can run: `config`, `probes`, `database`, `crawl`.
     *
     * @return list<string>
     */
    public function needs(): array;

    /**
     * @return iterable<Finding>
     */
    public function run(AuditContext $context): iterable;
}
