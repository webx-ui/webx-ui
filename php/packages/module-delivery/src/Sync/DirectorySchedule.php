<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

/**
 * A directory a carrier syncs and when (§7.7). The cron is the satellite's default; the site
 * overrides it with `webx-delivery.carriers.<key>.schedule.<directory>` — a cron, or `manual`.
 */
final readonly class DirectorySchedule
{
    public function __construct(
        public Directory $directory,
        public string $cron = '30 3 * * *',
        public int $pageSize = 500,
    ) {}
}
