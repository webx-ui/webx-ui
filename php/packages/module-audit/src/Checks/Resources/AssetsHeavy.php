<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Resources;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditResource;

/**
 * A stylesheet or a script heavier than 1 MB as sent — the page waits for it before it can draw
 * or respond. The size is what the server says (`Content-Length`), compressed if it compresses.
 */
final class AssetsHeavy extends ResourceCheck
{
    protected const ID = 'assets.heavy';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::WARNING;

    protected const SUMMARY = 'assets-heavy';

    protected function links(AuditContext $context): Builder
    {
        $bytes = $context->threshold('asset_kb', 1024) * 1024;

        return $this->withResource(
            $context,
            [AuditLink::SCRIPT, AuditLink::LINK],
            static fn (Builder $resource) => $resource->whereIn('kind', [AuditResource::CSS, AuditResource::JS])->where('status', 200)->where('bytes', '>', $bytes),
        );
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('kb'), Finding::column('type')];
    }
}
