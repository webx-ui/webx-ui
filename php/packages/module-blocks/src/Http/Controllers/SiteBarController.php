<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Editing\LastChange;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\Panel\SiteBarBlocks;

/**
 * The two addresses of the «Blocks» tab of the panel on the site (§8–§9 of WEBX_UI_SITE_BAR.md).
 *
 * No `cms.can` on the routes: the right is the record's section's, which the record names
 * ({@see SiteBarBlocks::find()}), plus reading blocks.
 */
final class SiteBarController
{
    public function __construct(private readonly SiteBarBlocks $blocks) {}

    /** `GET /api/cms/blocks/content/{type}/{id}` — the record's blocks as the tab reads them. */
    public function show(Request $request, string $type, string $id): JsonResponse
    {
        $entity = $this->blocks->find($type, $id, $request->user());

        return ApiResponse::data($this->blocks->read($entity));
    }

    /**
     * `POST /api/cms/blocks/content/{type}/{id}/edit` — `{ revision, locale, ops }` into the
     * record's draft; the answer is the new revision and the edited block drawn from the draft,
     * for the page to put in place of the old one.
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
            'ops' => ['required', 'array', 'list', 'min:1', 'max:50'],
        ]);

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
            'html' => isset($keys[0]) ? $this->blocks->html($entity, $keys[0], $locale) : '',
        ]);
    }

    private function adminId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
