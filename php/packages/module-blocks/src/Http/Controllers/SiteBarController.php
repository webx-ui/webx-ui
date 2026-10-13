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
use WebxUi\Blocks\Rendering\Renderer;

/**
 * The two addresses of the «Blocks» tab of the panel on the site (§8–§9 of WEBX_UI_SITE_BAR.md),
 * for the record behind the page and for a region of the layout (`region/{name}`) alike.
 *
 * No `cms.can` on the routes: the right is the record's section's, which the record names
 * ({@see SiteBarBlocks::find()}), plus reading blocks — or, for a region, `blocks.regions`.
 */
final class SiteBarController
{
    public function __construct(
        private readonly SiteBarBlocks $blocks,
        private readonly Renderer $renderer,
    ) {}

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
     * `POST /api/cms/blocks/content/{type}/{id}/edit` — `{ revision, locale, url, bundles, ops }`
     * into the draft. `url` is the page the bar is on: a region's block is drawn as on that page.
     * `bundles` are the hashes of the sets of styles and scripts that page has.
     *
     * The answer is the new revision, the block to open (`key` — the one a `set` named, the one an
     * `add` or a `duplicate` made) and what the page does to show the draft (`steps`, each block
     * it puts in drawn from the draft with its markers), plus the styles and the script of the
     * types the page did not have (`assets`). `html` is the block `key` drawn, as before SB5.
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
            'bundles' => ['nullable', 'array', 'list', 'max:32'],
            'bundles.*' => ['string', 'max:64'],
            'ops' => ['required', 'array', 'list', 'min:1', 'max:50'],
        ]);

        $url = $this->path($validated['url'] ?? null);
        $entity = $this->blocks->find($type, $id, $request->user());
        $before = $this->blocks->editing($entity);
        $sent = $validated['revision'] ?? null;
        $now = $this->blocks->revision($entity, $before);

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

        $made = $this->blocks->edit($entity, $ops, $locale, $this->adminId($request));

        $entity->refresh();
        $after = $this->blocks->editing($entity);
        $first = is_array($ops[0] ?? null) && is_string($ops[0]['key'] ?? null) ? $ops[0]['key'] : null;
        $key = $made ?? $first;

        $moved = array_values(array_filter(array_map(
            static fn (mixed $op): ?string => is_array($op) && ($op['op'] ?? null) === 'move' && is_string($op['key'] ?? null) ? $op['key'] : null,
            $ops,
        )));

        // Every block the page puts in is drawn from the draft; the types they use are what the
        // page may lack the styles of.
        $steps = [];

        foreach (SiteBarBlocks::steps($before, $after, $moved) as $step) {
            if ($step['do'] === 'place' || $step['do'] === 'move') {
                $step['html'] = $this->blocks->html($entity, $step['key'], $locale, $url);
            }

            $steps[] = $step;
        }

        /** @var list<string>|null $have */
        $have = $validated['bundles'] ?? null;
        $html = '';

        foreach ($steps as $step) {
            if ($step['key'] === $key && isset($step['html'])) {
                $html = $step['html'];
            }
        }

        return ApiResponse::data([
            'revision' => $this->blocks->revision($entity, $after),
            'key' => $key,
            'html' => $html,
            'steps' => $steps,
            'assets' => $this->blocks->assets($this->renderer->usedTypes(), $have),
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
