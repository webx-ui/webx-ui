<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Inbox\Http\Requests\SaveStatusRequest;
use WebxUi\Inbox\Http\Requests\SortingRequest;
use WebxUi\Inbox\Http\Resources\StatusResource;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Panel\Sorting;

/**
 * The states a submission can be in (§2.11).
 *
 * Readable by anybody who may see the section — the list needs them to draw a badge and the
 * tabs over it — and writable only under `inbox.manage`, which is the permission for changing
 * what the section is rather than what is in it.
 */
final class StatusController
{
    public function index(): JsonResponse
    {
        $statuses = Status::query()
            ->withCount('submissions')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return ApiResponse::data(StatusResource::collection($statuses));
    }

    public function store(SaveStatusRequest $request): JsonResponse
    {
        $status = new Status($request->values());
        $status->position = (int) Status::query()->max('position') + 1;
        $status->save();

        return ApiResponse::data(new StatusResource($status), 201);
    }

    public function update(SaveStatusRequest $request, Status $status): JsonResponse
    {
        $status->update($request->values());

        return ApiResponse::data(new StatusResource($status->refresh()->loadCount('submissions')));
    }

    /**
     * A status nothing is in.
     *
     * One that submissions are still wearing refuses with the reason (§3): they would have
     * nothing to be in, and moving them somewhere the deleter did not choose is not a decision
     * a delete button should be allowed to make.
     */
    public function destroy(Status $status): JsonResponse
    {
        $status->delete();

        return ApiResponse::noContent();
    }

    public function sorting(SortingRequest $request): JsonResponse
    {
        Sorting::apply(Status::query(), $request->ids());

        return ApiResponse::noContent();
    }
}
