<?php

declare(strict_types=1);

namespace WebxUi\Services\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Services\Http\Resources\ServiceResource;
use WebxUi\Services\Models\Service;

/**
 * On the site and off it. No date: a service is live or it is not, and there is no "scheduled"
 * (§4.6).
 */
final class ServicePublicationController
{
    public function publish(Request $request, Service $service): JsonResponse
    {
        $id = $request->user()?->getAuthIdentifier();

        $service->publish(
            is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null,
            EntityVersion::SOURCE_PANEL,
        );

        return ApiResponse::data(new ServiceResource($this->loaded($service->refresh())));
    }

    /**
     * Off the site, and nothing else touched: what was being prepared is still being prepared,
     * and the address stays held. Released, it would be taken by the next service called the same
     * thing, and putting this one back would be a move.
     */
    public function unpublish(Service $service): JsonResponse
    {
        $service->unpublish();

        return ApiResponse::data(new ServiceResource($this->loaded($service->refresh())));
    }

    private function loaded(Service $service): Service
    {
        return $service->loadMissing(['routes', 'categories', 'cover']);
    }
}
