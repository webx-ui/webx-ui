<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Resources;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Page\LinkCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;

/**
 * An `alt` longer than 100 characters: a screen reader reads all of it, and a paragraph stuffed
 * with words reads as stuffing to search engines too. A picture is described in a sentence.
 */
final class ImagesAltLong extends LinkCheck
{
    protected const ID = 'images.alt_long';

    protected const GROUP = 'images';

    protected const SEVERITY = Severity::NOTICE;

    protected const SUMMARY = 'images-alt-long';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)->where('kind', AuditLink::IMG)->whereNotNull('anchor');
    }

    protected function keep(AuditLink $link, AuditContext $context): bool
    {
        return mb_strlen((string) $link->anchor) > $context->threshold('alt_max', 100);
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('anchor')];
    }
}
