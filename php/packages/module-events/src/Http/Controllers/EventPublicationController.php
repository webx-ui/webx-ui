<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Events\Http\Resources\EventResource;
use WebxUi\Events\Models\Event;

/**
 * On the site and off it. No date: an event is on the site or it is not — its own date is what it
 * is about, not when it goes live (§4.10).
 */
final class EventPublicationController
{
    public function publish(Request $request, Event $event): JsonResponse
    {
        $id = $request->user()?->getAuthIdentifier();

        $event->publish(
            is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null,
            EntityVersion::SOURCE_PANEL,
        );

        return ApiResponse::data(new EventResource($this->loaded($event->refresh())));
    }

    /**
     * Off the site, and nothing else touched: what was being prepared is still being prepared,
     * and the address stays held. Released, it would be taken by the next event called the same
     * thing, and putting this one back would be a move.
     */
    public function unpublish(Event $event): JsonResponse
    {
        $event->unpublish();

        return ApiResponse::data(new EventResource($this->loaded($event->refresh())));
    }

    private function loaded(Event $event): Event
    {
        return $event->loadMissing(['routes', 'categories']);
    }
}
