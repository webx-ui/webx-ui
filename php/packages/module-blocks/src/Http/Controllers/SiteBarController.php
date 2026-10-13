<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Editing\LastChange;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Panel\SiteBarBlocks;

/**
 * The two addresses of the «Blocks» tab of the panel on the site (§8–§9 of WEBX_UI_SITE_BAR.md),
 * for the record behind the page and for a region of the layout (`region/{name}`) alike.
 *
 * No `cms.can` on the routes: the right is the record's section's, which the record names
 * ({@see SiteBarBlocks::find()}), plus reading blocks — or, for a region, `blocks.regions`.
 */
final class SiteBarController
{
    public function __construct(private readonly SiteBarBlocks $blocks) {}

    /** `GET /api/cms/blocks/content/{type}/{id}` — the blocks as the tab reads them. */
    public function show(Request $request, string $type, string $id): JsonResponse
    {
        $entity = $this->blocks->find($type, $id, $request->user());
        $read = $this->blocks->read($entity);

        if ($entity instanceof Region) {
            $read['region'] = $this->blocks->regionState($entity, $request->user());
        }

        return ApiResponse::data($read);
    }

    /**
     * `POST /api/cms/blocks/content/{type}/{id}/edit` — `{ revision, locale, url, ops }` into the
     * draft; the answer is the new revision and the edited block drawn from the draft, for the
     * page to put in place of the old one. `url` is the page the bar is on: a region's block is
     * drawn as on that page.
     *
     * A revision that is not the record's any more is somebody else's edit since the form read
     * it: 409 with who made it, and the form reads the block again. No merge by field, unlike
     * the page's editor — the form is small, and reading again is cheaper than explaining three
     * values.
     */
    public function edit(Request $request, string $type, string $id): JsonResponse
    {
        $validated = $request->validate([
            'revision' => ['nullable', 'string', 'max:128'],
            'locale' => ['nullable', 'string', 'max:16'],
            'url' => ['nullable', 'string', 'max:2048'],
            'ops' => ['required', 'array', 'list', 'min:1', 'max:50'],
        ]);

        $url = $this->path($validated['url'] ?? null);
        $entity = $this->blocks->find($type, $id, $request->user());
        $sent = $validated['revision'] ?? null;
        $now = $this->blocks->revision($entity, $this->blocks->editing($entity));

        if (is_string($sent) && $sent !== '' && $sent !== $now) {
            return new JsonResponse([
                'message' => (string) __('webx-blocks::site-bar.conflict'),
                'revision' => $now,
                'changed' => LastChange::of($entity, $request->user()),
            ], 409);
        }

        $locale = is_string($validated['locale'] ?? null) && $validated['locale'] !== '' ? $validated['locale'] : null;
        /** @var list<mixed> $ops */
        $ops = $validated['ops'];

        $this->blocks->edit($entity, $ops, $locale, $this->adminId($request));

        $entity->refresh();
        $keys = array_values(array_unique(array_filter(array_map(
            static fn (mixed $op): ?string => is_array($op) && is_string($op['key'] ?? null) ? $op['key'] : null,
            $ops,
        ))));

        return ApiResponse::data([
            'revision' => $this->blocks->revision($entity, $this->blocks->editing($entity)),
            'key' => $keys[0] ?? null,
            'html' => isset($keys[0]) ? $this->blocks->html($entity, $keys[0], $locale, $url) : '',
        ]);
    }

    /**
     * A path of this site, or nothing. A host, a scheme or `//` is refused rather than stripped:
     * the block is drawn as on that page, and somebody else's page is not one.
     */
    private function path(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $foreign = ! str_starts_with($url, '/')
            || str_starts_with($url, '//')
            || str_starts_with($url, '/\\')
            || parse_url($url, PHP_URL_SCHEME) !== null
            || parse_url($url, PHP_URL_HOST) !== null;

        if ($foreign) {
            throw ValidationException::withMessages(['url' => (string) __('webx-admin::site-bar.foreign')]);
        }

        return (string) (parse_url($url, PHP_URL_PATH) ?: '/');
    }

    private function adminId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
