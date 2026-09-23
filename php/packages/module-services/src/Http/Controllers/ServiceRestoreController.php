<?php

declare(strict_types=1);

namespace WebxUi\Services\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Services\Http\Resources\ServiceResource;
use WebxUi\Services\Models\Service;

/**
 * Out of the bin — bound by hand, because a deleted service is invisible to the model binding
 * every other endpoint here uses.
 *
 * The address comes back by itself, or the restore is refused because somebody has taken the
 * spelling meanwhile: `OnConflict::Fail` makes that a 422 under the slug. A service quietly
 * restored to `implants-2` is worse than one that says the place is occupied.
 */
final class ServiceRestoreController
{
    public function __invoke(int $service): JsonResponse
    {
        $trashed = Service::withTrashed()->findOrFail($service);

        $trashed->restore();

        return ApiResponse::data(new ServiceResource(
            $trashed->refresh()->loadMissing(['routes', 'categories', 'cover']),
        ));
    }
}
