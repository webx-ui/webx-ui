<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Resources;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditResource;

/**
 * An external link to 4xx, 5xx or a host that does not answer — a partner that closed, a page
 * that moved. 429 is not counted: that is a server asking a robot to slow down, not a dead page.
 */
final class ExternalBroken extends ResourceCheck
{
    protected const ID = 'links.external_broken';

    protected const GROUP = 'links';

    protected const SEVERITY = Severity::WARNING;

    protected const SUMMARY = 'links-external-broken';

    protected function links(AuditContext $context): Builder
    {
        return $this->withResource(
            $context,
            [AuditLink::A],
            static fn (Builder $resource) => self::broken($resource->where('kind', AuditResource::PAGE))
                ->where(static fn (Builder $status) => $status->whereNull('status')->orWhere('status', '<>', 429)),
        );
    }
}
