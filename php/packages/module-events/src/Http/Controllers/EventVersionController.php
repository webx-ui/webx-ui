<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Events\Http\Resources\EventVersionResource;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Panel\EventForm;

/**
 * The history of an event: what was published, when, by whom and from where.
 *
 * Only the publications — the autosaves are insurance, not history. Restoring is not "put it back
 * on the site": the old version becomes the draft, and publishing it is the same separate step.
 */
final class EventVersionController
{
    public function index(Request $request, Event $event): JsonResponse
    {
        $versions = $event->publishedVersions()->get();
        $authors = Authors::names($request->user(), $versions->map(static fn (EntityVersion $version): ?int => $version->author_id));

        return ApiResponse::data(
            $versions
                ->map(static fn (EntityVersion $version): EventVersionResource => new EventVersionResource($version, $authors))
                ->values()
                ->all(),
        );
    }

    public function restore(Request $request, Event $event, int $number, EventForm $form): JsonResponse
    {
        $version = $event->publishedVersions()->where('number', $number)->first();

        if (! $version instanceof EntityVersion) {
            throw new NotFoundHttpException;
        }

        $event->restoreVersion($version);

        $id = $request->user()?->getAuthIdentifier();

        // The whole record rather than the values alone: restoring gives the event a new
        // revision, and a form that took only the values would save over it one edit late.
        return ApiResponse::data($form->describe(
            $event->refresh()->loadMissing(['routes', 'categories']),
            is_int($id) ? $id : null,
        ));
    }
}
