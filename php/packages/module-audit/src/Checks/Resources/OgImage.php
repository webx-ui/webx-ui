<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Resources;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Page\PageCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\Urls;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditResource;

/**
 * The Open Graph picture does not open, or is smaller than 1200×630 — the shared link shows no
 * picture, or a small square beside the text instead of a large card.
 */
final class OgImage extends PageCheck
{
    protected const ID = 'og.image_broken';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $value = trim((string) ($page->og['image'] ?? ''));
        $url = $value === '' ? null : Urls::resolve($page->url, $value);

        if ($url === null) {
            return;
        }

        $resource = AuditResource::query()->where('run_id', $page->run_id)->where('url_hash', sha1($url))->first();

        if ($resource === null || $resource->checked_at === null) {
            return;
        }

        if ($resource->broken()) {
            yield $this->on($page, 'og-image-broken', ['url' => $url, 'status' => $resource->status ?? '—'], key: 'broken');

            return;
        }

        $width = $context->threshold('og_width', 1200);
        $height = $context->threshold('og_height', 630);

        if ($resource->width !== null && $resource->height !== null && ($resource->width < $width || $resource->height < $height)) {
            yield $this->on($page, 'og-image-small', [
                'url' => $url,
                'size' => $resource->width.'×'.$resource->height,
                'min' => $width.'×'.$height,
            ], key: 'small');
        }
    }
}
