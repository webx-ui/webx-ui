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
 * An external link that answers with a redirect: it works, through a round trip, and often
 * means the page moved and the link was never updated.
 */
final class ExternalRedirect extends ResourceCheck
{
    protected const ID = 'hosts.external_redirect';

    protected const GROUP = 'hosts';

    protected const SEVERITY = Severity::NOTICE;

    protected const SUMMARY = 'external-redirect';

    protected function links(AuditContext $context): Builder
    {
        return $this->withResource(
            $context,
            [AuditLink::A],
            static fn (Builder $resource) => $resource->where('kind', AuditResource::PAGE)->whereNotNull('location'),
        );
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('status', 'status'), Finding::column('location', 'url')];
    }
}
