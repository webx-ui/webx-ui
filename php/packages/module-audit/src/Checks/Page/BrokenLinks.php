<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;

/**
 * Links to the site’s own pages that answer 4xx, 5xx or nothing.
 */
final class BrokenLinks extends LinkCheck
{
    protected const ID = 'links.broken';

    protected const SEVERITY = Severity::ERROR;

    protected const SUMMARY = 'links-broken';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)
            ->where('kind', AuditLink::A)
            ->whereHas('to', static fn (Builder $to) => $to->whereNotNull('fetched_at')->where(
                static fn (Builder $bad) => $bad->where('status', '>=', 400)->orWhereNull('status'),
            ));
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('status', 'status'), Finding::column('anchor')];
    }
}
