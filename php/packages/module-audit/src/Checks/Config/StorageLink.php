<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Config;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** No `public/storage`: every uploaded picture on the site answers 404. */
final class StorageLink extends Check
{
    protected const ID = 'config.storage_link';

    protected const GROUP = 'config';

    protected const SEVERITY = Severity::ERROR;

    protected const NEEDS = ['config'];

    public function run(AuditContext $context): iterable
    {
        /** @var array<string, string> $links */
        $links = (array) $context->config('filesystems.links', []);
        $public = public_path('storage');

        if (array_key_exists($public, $links) && ! file_exists($public)) {
            yield $this->found('storage-link', ['path' => 'public/storage']);
        }
    }
}
