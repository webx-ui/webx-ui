<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditResource;

/**
 * No icon linked from the page, or the one linked does not open (asked with the other things
 * the page loads, §3 stage 5).
 */
final class Favicon extends PageCheck
{
    protected const ID = 'html.favicon';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $icon = $page->fact('favicon');

        if (! is_string($icon)) {
            yield $this->on($page, 'favicon');

            return;
        }

        $resource = AuditResource::query()->where('run_id', $page->run_id)->where('url_hash', sha1($icon))->first();

        if ($resource !== null && $resource->broken()) {
            yield $this->on($page, 'favicon-broken', ['url' => $icon, 'status' => $resource->status ?? '—']);
        }
    }
}
