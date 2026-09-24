<?php

declare(strict_types=1);

namespace WebxUi\Services\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Services\Http\Resources\ServiceVersionResource;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Panel\ServiceForm;

/**
 * The history of a service: what was published, when, by whom and from where.
 *
 * Only the publications — the autosaves are insurance, not history. Restoring is not "put it back
 * on the site": the old version becomes the draft, and publishing it is the same separate step.
 */
final class ServiceVersionController
{
    public function index(Service $service): JsonResponse
    {
        $versions = $service->publishedVersions()->get();
        $authors = Authors::names($versions->map(static fn (EntityVersion $version): ?int => $version->author_id));

        return ApiResponse::data(
            $versions
                ->map(static fn (EntityVersion $version): ServiceVersionResource => new ServiceVersionResource($version, $authors))
                ->values()
                ->all(),
        );
    }

    public function restore(Request $request, Service $service, int $number, ServiceForm $form): JsonResponse
    {
        $version = $service->publishedVersions()->where('number', $number)->first();

        if (! $version instanceof EntityVersion) {
            throw new NotFoundHttpException;
        }

        $service->restoreVersion($version);

        $id = $request->user()?->getAuthIdentifier();

        // The whole record rather than the values alone: restoring gives the service a new
        // revision, and a form that took only the values would save over it one edit late.
        return ApiResponse::data($form->describe(
            $service->refresh()->loadMissing(['routes', 'categories', 'cover']),
            is_int($id) ? $id : null,
        ));
    }
}
