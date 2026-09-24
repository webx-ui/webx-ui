<?php

declare(strict_types=1);

namespace WebxUi\Services\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Panel\ServiceForm;

/**
 * Throw away what is waiting and go back to what the site is showing.
 *
 * What is dropped is not lost: `saveDraft()` keeps a ring of autosaves, so the last few minutes of
 * writing are still in `entity_versions`, even though no screen lists them.
 */
final class ServiceDraftController
{
    public function __invoke(Request $request, Service $service, ServiceForm $form): JsonResponse
    {
        $service->discardDraft();

        $id = $request->user()?->getAuthIdentifier();

        return ApiResponse::data($form->describe(
            $service->refresh()->loadMissing(['routes', 'categories', 'cover']),
            is_int($id) ? $id : null,
        ));
    }
}
