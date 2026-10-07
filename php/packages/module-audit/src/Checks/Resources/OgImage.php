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
 * The Open Graph picture does not open — the shared link shows no picture at all.
 *
 * A picture that opens and is merely small is {@see OgImageSmall}: a different problem with a
 * different fix, and one somebody may decide to live with while still wanting to hear about a
 * broken one.
 */
class OgImage extends PageCheck
{
    protected const ID = 'og.image_broken';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        [$url, $resource] = $this->picture($page);

        if ($resource !== null && $resource->broken()) {
            yield $this->on($page, 'og-image-broken', ['url' => $url, 'status' => $resource->status ?? '—'], key: 'broken');
        }
    }

    /**
     * The page's og:image and what the crawl found at it — null when there is none, or it was
     * not checked.
     *
     * @return array{0: string, 1: AuditResource|null}
     */
    protected function picture(AuditPage $page): array
    {
        $value = trim((string) ($page->og['image'] ?? ''));
        $url = $value === '' ? null : Urls::resolve($page->url, $value);

        if ($url === null) {
            return ['', null];
        }

        $resource = AuditResource::query()->where('run_id', $page->run_id)->where('url_hash', sha1($url))->first();

        return [$url, $resource !== null && $resource->checked_at !== null ? $resource : null];
    }
}
