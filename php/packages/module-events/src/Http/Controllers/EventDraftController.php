<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Panel\EventForm;

/**
 * Throw away what is waiting and go back to what the site is showing.
 *
 * What is dropped is not lost: `saveDraft()` keeps a ring of autosaves, so the last few minutes of
 * writing are still in `entity_versions`, even though no screen lists them.
 */
final class EventDraftController
{
    public function __invoke(Request $request, Event $event, EventForm $form): JsonResponse
    {
        $event->discardDraft();

        $id = $request->user()?->getAuthIdentifier();

        return ApiResponse::data($form->describe(
            $event->refresh()->loadMissing(['routes', 'categories']),
            is_int($id) ? $id : null,
        ));
    }
}
