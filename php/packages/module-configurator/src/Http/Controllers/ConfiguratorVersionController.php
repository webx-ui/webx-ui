<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorForm;

/**
 * The history: every publication, and one of them put back into the draft. Restoring never
 * changes the site — publishing the restored draft is the same separate step it always is.
 */
final class ConfiguratorVersionController
{
    public function index(Request $request, Configurator $configurator): JsonResponse
    {
        $versions = $configurator->publishedVersions()->withoutPayload()->get();
        $authors = Authors::names($request->user(), $versions->map(static fn (EntityVersion $version): ?int => $version->author_id));
        $live = $configurator->isPublished() ? $versions->first()?->number : null;

        return ApiResponse::data($versions->map(static fn (EntityVersion $version): array => [
            'number' => $version->number,
            'created_at' => $version->created_at?->toAtomString(),
            // Who, and failing that where from: a configurator is built by people and by agents.
            'author' => $version->author_id === null ? null : ($authors[$version->author_id] ?? null),
            'source' => $version->source,
            'comment' => $version->comment,
            'on_site' => $version->number === $live,
        ])->values()->all());
    }

    public function restore(Configurator $configurator, int $number, ConfiguratorForm $form): JsonResponse
    {
        $version = $configurator->publishedVersions()->where('number', $number)->first();

        if (! $version instanceof EntityVersion) {
            throw new NotFoundHttpException;
        }

        $configurator->restoreVersion($version);

        // The whole record: restoring gives a new revision, and a form that took only the values
        // would save over it one edit late.
        return ApiResponse::data($form->describe($configurator->refresh()));
    }
}
