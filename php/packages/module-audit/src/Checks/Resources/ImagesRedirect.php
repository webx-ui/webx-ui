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
 * A picture behind a redirect: every visitor makes the round trip before the picture starts to
 * load, and image search indexes the address it ends at, not the one in the page.
 */
final class ImagesRedirect extends ResourceCheck
{
    protected const ID = 'images.redirect';

    protected const GROUP = 'images';

    protected const SEVERITY = Severity::WARNING;

    protected const SUMMARY = 'images-redirect';

    protected function links(AuditContext $context): Builder
    {
        return $this->withResource(
            $context,
            [AuditLink::IMG, AuditLink::SRCSET, AuditLink::STYLE],
            static fn (Builder $resource) => $resource->where('kind', AuditResource::IMAGE)->whereBetween('status', [300, 399]),
        );
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('status', 'status'), Finding::column('location', 'url')];
    }
}
