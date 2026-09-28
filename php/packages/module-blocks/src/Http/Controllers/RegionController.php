<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Exceptions\RegionRefused;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Blocks\Panel\RegionForm;
use WebxUi\Blocks\Panel\RegionWriter;
use WebxUi\Blocks\Regions;

/**
 * "Site regions" in the panel (§7 of the regions spec): the declared regions, and the editor of
 * one — a draft, publication, taking it off, the history, moving the fallback into a block.
 *
 * Addressed by name, the key the layout's tag uses: a region nobody has saved has no id yet,
 * and is edited all the same. A name the config does not declare is a 404 — the panel shows only
 * what the layout prints.
 */
final class RegionController
{
    public function __construct(
        private readonly Regions $regions,
        private readonly RegionForm $form,
        private readonly RegionWriter $writer,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data(array_map(
            fn (string $name): array => $this->form->row($name),
            array_keys($this->regions->declared()),
        ));
    }

    public function show(Request $request, string $name): JsonResponse
    {
        $this->declared($name);

        return $this->detail($request, $name);
    }

    public function update(Request $request, string $name): JsonResponse
    {
        $this->declared($name);

        $request->validate([
            'blocks' => ['present', 'array', 'list'],
            'revision' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var list<mixed> $blocks */
        $blocks = $request->input('blocks', []);
        $revision = $request->input('revision');

        return $this->attempt(fn (): mixed => $this->writer->save($name, $blocks, is_string($revision) ? $revision : null, $this->author($request)), $request, $name);
    }

    public function publish(Request $request, string $name): JsonResponse
    {
        $this->declared($name);

        return $this->attempt(fn (): mixed => $this->writer->publish($name, $this->author($request)), $request, $name);
    }

    public function unpublish(Request $request, string $name): JsonResponse
    {
        $this->declared($name);
        $this->writer->unpublish($name);

        return $this->detail($request, $name);
    }

    public function discard(Request $request, string $name): JsonResponse
    {
        $this->declared($name);
        $this->writer->discard($name);

        return $this->detail($request, $name);
    }

    /**
     * The publications, newest first — without their payloads: the list is read to pick one, and
     * restoring is the server's business.
     */
    public function versions(string $name): JsonResponse
    {
        $this->declared($name);

        $region = $this->regions->find($name);

        if ($region === null) {
            return ApiResponse::data([]);
        }

        $versions = $region->publishedVersions()->get();
        $authors = Authors::names($versions->map(static fn (EntityVersion $version): ?int => $version->author_id));

        return ApiResponse::data($versions->map(static fn (EntityVersion $version): array => [
            'number' => $version->number,
            'created_at' => $version->created_at?->toAtomString(),
            'author' => $version->author_id !== null && isset($authors[$version->author_id])
                ? ['id' => $version->author_id, 'name' => $authors[$version->author_id]]
                : null,
            'source' => $version->source,
            'comment' => $version->comment,
            'is_pinned' => $version->is_pinned,
        ])->values()->all());
    }

    public function restore(Request $request, string $name, int $number): JsonResponse
    {
        $this->declared($name);

        return $this->attempt(fn (): mixed => $this->writer->restore($name, $number), $request, $name);
    }

    /**
     * "Move the markup into a block": writing a block type, so the rights of whoever writes Blade
     * on top of the region's own — the route lets in `blocks.regions`, this asks for the rest.
     */
    public function adopt(Request $request, string $name, Config $config): JsonResponse
    {
        $this->declared($name);

        $user = $request->user();

        if (! $user instanceof HasPermissions || ! $user->hasPermission('blocks.manage')) {
            return ApiResponse::message((string) __('webx-blocks::regions.adopt-forbidden'), 403);
        }

        if (! (bool) $config->get('webx-blocks.editing', true)) {
            return ApiResponse::message((string) __('webx-blocks::page.editing-off'), 403);
        }

        try {
            [, $block] = $this->writer->adopt($name, $this->author($request));
        } catch (RegionRefused $refused) {
            return $this->refused($refused);
        }

        return new JsonResponse([
            'data' => $this->form->describe($name, $request->user()),
            'block' => ['id' => $block->id, 'slug' => $block->slug],
        ], 201);
    }

    /**
     * @param  callable(): mixed  $write
     */
    private function attempt(callable $write, Request $request, string $name): JsonResponse
    {
        try {
            $write();
        } catch (RegionRefused $refused) {
            return $this->refused($refused);
        }

        return $this->detail($request, $name);
    }

    private function refused(RegionRefused $refused): JsonResponse
    {
        $body = ['message' => $refused->getMessage()];

        if ($refused->errors !== []) {
            $body['errors'] = ['blocks' => $refused->errors];
        }

        if ($refused->revision !== null) {
            $body['revision'] = $refused->revision;
        }

        return new JsonResponse($body, $refused->status);
    }

    private function detail(Request $request, string $name): JsonResponse
    {
        return ApiResponse::data($this->form->describe($name, $request->user()));
    }

    private function declared(string $name): void
    {
        if (! $this->regions->has($name)) {
            throw new NotFoundHttpException;
        }
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
