<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;

/**
 * Internal links to an address that redirects: every click is a round trip more, and the page
 * passes its weight through a redirect rather than straight on. Link to where it leads.
 */
final class LinksToRedirect extends LinkCheck
{
    protected const ID = 'links.to_redirect';

    protected const SEVERITY = Severity::WARNING;

    protected const SUMMARY = 'links-to-redirect';

    protected const TO = 'to:id,status,redirect_to';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)
            ->where('kind', AuditLink::A)
            ->whereHas('to', static fn (Builder $to) => $to->whereNotNull('redirect_to'));
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('status', 'status'), Finding::column('location', 'url'), Finding::column('anchor')];
    }

    protected function row(AuditLink $link): array
    {
        return [...parent::row($link), 'location' => $link->to?->redirect_to];
    }
}
