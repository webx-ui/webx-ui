<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Events\Http\Resources\EventResource;
use WebxUi\Events\Models\Event;

/**
 * Out of the bin — bound by hand, because a deleted event is invisible to the model binding
 * every other endpoint here uses.
 *
 * The address comes back by itself, or the restore is refused because somebody has taken the
 * spelling meanwhile: `OnConflict::Fail` makes that a 422 under the slug. An event quietly
 * restored to `spring-class-2` is worse than one that says the place is occupied.
 */
final class EventRestoreController
{
    public function __invoke(int $event): JsonResponse
    {
        $trashed = Event::withTrashed()->findOrFail($event);

        $trashed->restore();

        return ApiResponse::data(new EventResource(
            $trashed->refresh()->loadMissing(['routes', 'categories']),
        ));
    }
}
