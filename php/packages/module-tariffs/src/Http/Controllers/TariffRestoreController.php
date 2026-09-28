<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Tariffs\Http\Resources\TariffResource;
use WebxUi\Tariffs\Models\Tariff;

/**
 * Out of the bin — bound by hand, because a deleted tariff is invisible to the model binding
 * every other endpoint here uses. It comes back to its places in the list and in its groups,
 * which it kept while it was away. The answer is the row of the list, as it will be drawn.
 */
final class TariffRestoreController
{
    public function __invoke(int $tariff): JsonResponse
    {
        $trashed = Tariff::withTrashed()->findOrFail($tariff);

        $trashed->restore();

        return ApiResponse::data(new TariffResource($trashed->refresh()->loadMissing('categories')));
    }
}
