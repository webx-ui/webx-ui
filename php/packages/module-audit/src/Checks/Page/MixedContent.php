<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;

/**
 * An `http://` resource on an `https` page — the browser blocks it or warns.
 */
final class MixedContent extends LinkCheck
{
    protected const ID = 'mixed_content';

    protected const SEVERITY = Severity::ERROR;

    protected const SUMMARY = 'mixed-content';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)
            ->whereIn('kind', AuditLink::RESOURCES)
            ->where('to_url', 'like', 'http://%')
            ->where(static fn (Builder $rel) => $rel->whereNull('rel')->orWhereNotIn('rel', ['canonical', 'alternate', 'next', 'prev']))
            ->whereHas('from', static fn (Builder $page) => $page->where('url', 'like', 'https://%'));
    }
}
