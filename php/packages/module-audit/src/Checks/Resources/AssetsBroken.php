<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Resources;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditResource;

/**
 * A stylesheet or a script that does not open: the page comes out unstyled, or a menu, a gallery
 * or a form stops working — and search engines render it that way too.
 */
final class AssetsBroken extends ResourceCheck
{
    protected const ID = 'assets.broken';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::ERROR;

    protected const SUMMARY = 'assets-broken';

    protected function links(AuditContext $context): Builder
    {
        return $this->withResource(
            $context,
            [AuditLink::SCRIPT, AuditLink::LINK],
            static fn (Builder $resource) => self::broken($resource->whereIn('kind', [AuditResource::CSS, AuditResource::JS])),
        );
    }
}
