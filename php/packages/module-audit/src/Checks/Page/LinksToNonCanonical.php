<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;

/**
 * Internal links to a page whose canonical names another: the site votes for the copy and tells
 * search engines the original is elsewhere. Typical of a catalogue whose filters and sorting
 * leak into the menu. Link to the canonical address.
 */
final class LinksToNonCanonical extends LinkCheck
{
    protected const ID = 'links.to_non_canonical';

    protected const SEVERITY = Severity::WARNING;

    protected const SUMMARY = 'links-to-non-canonical';

    protected const TO = 'to:id,status,canonical';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)
            ->where('kind', AuditLink::A)
            ->whereHas('to', static fn (Builder $to) => $to->html()->whereNotNull('canonical')->whereColumn('canonical', '<>', 'url'));
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('canonical', 'url'), Finding::column('anchor')];
    }

    protected function row(AuditLink $link): array
    {
        return [...parent::row($link), 'canonical' => $link->to?->canonical];
    }
}
