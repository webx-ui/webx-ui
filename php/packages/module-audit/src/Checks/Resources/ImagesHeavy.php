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
 * A picture heavier than 300 KB — on a phone that is seconds of a blank page. The size is what
 * the server says (`Content-Length`); a picture it does not say it of is not judged.
 */
final class ImagesHeavy extends ResourceCheck
{
    protected const ID = 'images.heavy';

    protected const GROUP = 'images';

    protected const SEVERITY = Severity::WARNING;

    protected const SUMMARY = 'images-heavy';

    protected function links(AuditContext $context): Builder
    {
        $bytes = $context->threshold('image_kb', 300) * 1024;

        return $this->withResource(
            $context,
            [AuditLink::IMG, AuditLink::SRCSET, AuditLink::STYLE],
            static fn (Builder $resource) => $resource->where('kind', AuditResource::IMAGE)->where('status', 200)->where('bytes', '>', $bytes),
        );
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('kb'), Finding::column('type')];
    }
}
