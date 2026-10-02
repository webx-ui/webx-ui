<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Resources;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditResource;

/**
 * A picture that does not open — the visitor sees a broken icon or an empty box where the
 * product was.
 */
final class ImagesBroken extends ResourceCheck
{
    protected const ID = 'images.broken';

    protected const GROUP = 'images';

    protected const SEVERITY = Severity::ERROR;

    protected const SUMMARY = 'images-broken';

    protected function links(AuditContext $context): Builder
    {
        return $this->withResource(
            $context,
            [AuditLink::IMG, AuditLink::SRCSET, AuditLink::STYLE],
            static fn (Builder $resource) => self::broken($resource->where('kind', AuditResource::IMAGE)),
        );
    }
}
