<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Resources;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditResource;

/**
 * The site's own JPEG or PNG, over 100 KB, sent to a browser that said it takes WebP and AVIF,
 * with no modern source beside it in a `<picture>` — the same picture would weigh a third.
 * Somebody else's pictures are not the site's to convert.
 */
final class ImagesFormat extends ResourceCheck
{
    protected const ID = 'images.format';

    protected const GROUP = 'images';

    protected const SEVERITY = Severity::NOTICE;

    protected const SUMMARY = 'images-format';

    protected function links(AuditContext $context): Builder
    {
        $bytes = $context->threshold('image_format_kb', 100) * 1024;

        return $this->withResource(
            $context,
            [AuditLink::IMG, AuditLink::STYLE],
            static fn (Builder $resource) => $resource
                ->where('kind', AuditResource::IMAGE)
                ->whereIn('host_class', [HostClassifier::OWN, HostClassifier::OWN_MIRROR])
                ->where('status', 200)
                ->where('bytes', '>', $bytes)
                ->where(static fn (Builder $type) => $type->where('content_type', 'like', '%jpeg%')->orWhere('content_type', 'like', '%png%')),
        )->where(static fn (Builder $link) => $link->whereNull('rel')->orWhere('rel', '<>', AuditLink::MODERN));
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('kb'), Finding::column('type')];
    }
}
