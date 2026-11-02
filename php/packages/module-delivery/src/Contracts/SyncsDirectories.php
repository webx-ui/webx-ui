<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\DirectorySchedule;
use WebxUi\Delivery\Sync\Page;

/**
 * A carrier that keeps its directories on the site (§7.1). Implemented by the satellite only:
 * it returns records, the base writes them — one `upsert` a page — so that idempotency, the
 * removal of what vanished and the journal are one for every carrier.
 */
interface SyncsDirectories
{
    /**
     * The directories it can pull and how often: localities first, then points.
     *
     * @return list<DirectorySchedule>
     */
    public function directories(): array;

    /**
     * One page of a directory. Pages are pulled until `next === null`. Must be repeatable: the
     * same cursor gives the same records, or a newer state of them.
     *
     * @throws CarrierUnavailable the carrier's API failed; the run stops, nothing is removed
     */
    public function pull(Directory $directory, ?string $cursor): Page;
}
