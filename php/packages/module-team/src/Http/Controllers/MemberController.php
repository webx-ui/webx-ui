<?php

declare(strict_types=1);

namespace WebxUi\Team\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Team\Http\Resources\MemberResource;
use WebxUi\Team\Models\Member;
use WebxUi\Team\Panel\MemberForm;
use WebxUi\Team\Panel\MemberList;

/**
 * The section's list, one person as the editor opens them, and the order (§5.7).
 *
 * The record is thin on purpose: the form is a described screen, so what a person's values are is
 * decided by the description and checked by `ScreenValues`.
 */
final class MemberController
{
    public function __construct(
        private readonly MemberList $list,
        private readonly MemberForm $form,
    ) {}

    /** Everybody at once — no meta, no pages, no filters: the team has no categories. */
    public function index(Request $request, MediaFiles $files): JsonResponse
    {
        $members = $this->list->build($request)->get();

        // The thumbnails of the whole list in one query of the library.
        $files->load(array_values(array_filter($members->map(
            static fn (Member $member): ?string => $member->photoPath(),
        )->all())));

        return new JsonResponse([
            'data' => $members
                ->map(static fn (Member $member): array => (new MemberResource($member))->resolve($request))
                ->values()
                ->all(),
        ]);
    }

    public function show(Member $member): JsonResponse
    {
        return ApiResponse::data($this->form->describe($member));
    }

    /**
     * A new person, from the values of the same screen that edits them: the list opens an empty
     * form rather than a dialog, and the first save is the one that creates the row.
     */
    public function store(Request $request): JsonResponse
    {
        $member = $this->form->save(new Member, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($member), 201);
    }

    /** Save — 422 under the name of the field that refused. */
    public function update(Request $request, Member $member): JsonResponse
    {
        $member = $this->form->save($member, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($member));
    }

    /** Into the bin: they come back to their place in the list and to their services. */
    public function destroy(Member $member): JsonResponse
    {
        $member->delete();

        return ApiResponse::noContent();
    }

    /**
     * The order, dragged the way the editor sees it: the one list there is (decision 5). A
     * `category` sent along is ignored rather than obeyed — there are none to order inside.
     */
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->input('ids');

        if (! is_array($ids) || array_filter($ids, static fn (mixed $id): bool => ! is_numeric($id)) !== []) {
            throw ValidationException::withMessages(['ids' => (string) __('webx-team::errors.ids')]);
        }

        Ordering::move(Member::class, array_values(array_map(intval(...), $ids)));

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
