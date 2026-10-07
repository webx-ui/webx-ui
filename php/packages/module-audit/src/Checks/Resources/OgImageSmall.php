<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Resources;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Runs\AuditPage;

/**
 * The Open Graph picture opens and is smaller than 1200×630 — the shared link shows a small
 * square beside the text instead of a large card.
 *
 * Until this check had an id of its own the same finding came out as `og.image_broken`, so a
 * hiding rule written then is copied to this id by a migration of this package.
 */
final class OgImageSmall extends OgImage
{
    protected const ID = 'og.image_small';

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        [$url, $resource] = $this->picture($page);

        if ($resource === null || $resource->broken() || $resource->width === null || $resource->height === null) {
            return;
        }

        $width = $context->threshold('og_width', 1200);
        $height = $context->threshold('og_height', 630);

        if ($resource->width < $width || $resource->height < $height) {
            yield $this->on($page, 'og-image-small', [
                'url' => $url,
                'size' => $resource->width.'×'.$resource->height,
                'min' => $width.'×'.$height,
            ], key: 'small');
        }
    }
}
