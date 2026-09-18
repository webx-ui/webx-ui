<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Http\Resources\NoteResource;
use WebxUi\Admin\Notes\Notable;
use WebxUi\Admin\Notes\Note;
use WebxUi\Admin\Notes\NoteTypes;

/**
 * The notes of any record, through one address (§2.17 of the inbox spec).
 *
 * One controller rather than one per module, because a note is the same thing wherever it is
 * written and every section would otherwise grow its own copy of it. What makes that safe is
 * the two rules underneath:
 *
 * - the type in the address is an alias of the morph map and has to have been registered, so
 *   this cannot be pointed at a model that never asked for notes;
 * - the model names the permission its notes are behind, so a reader with a right to one
 *   section's records does not thereby get another section's notes.
 *
 * Editing and deleting are the author's own: a note is what one person wrote, and a feed
 * anybody can rewrite is not a record of anything.
 */
final class NoteController
{
    public function __construct(private readonly NoteTypes $types) {}

    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $entity = $this->entity($request, $type, $id);

        return ApiResponse::data(NoteResource::feed($entity->noteFeed(), $request->user()));
    }

    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $entity = $this->entity($request, $type, $id);

        $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $note = $entity->addNote(
            trim((string) $request->input('body')),
            $this->adminId($request),
        );

        return ApiResponse::data(new NoteResource($note, $this->name($request)), 201);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        $this->mine($request, $note);

        $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $note->forceFill(['body' => trim((string) $request->input('body'))])->save();

        return ApiResponse::data(new NoteResource($note, $this->name($request)));
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $this->mine($request, $note);

        $note->delete();

        return ApiResponse::noContent();
    }

    /** The record the notes belong to, once it is established that this reader may see them. */
    private function entity(Request $request, string $type, int $id): Notable
    {
        $class = $this->types->find($type);

        if ($class === null || ! is_a($class, Notable::class, true)) {
            throw new NotFoundHttpException((string) __('webx-admin::notes.no-type'));
        }

        $entity = $class::query()->find($id);

        if (! $entity instanceof Notable) {
            throw new NotFoundHttpException((string) __('webx-admin::notes.missing'));
        }

        $this->allowed($request, $entity);

        return $entity;
    }

    /** The permission is the record's own answer, never this controller's guess. */
    private function allowed(Request $request, Notable $entity): void
    {
        $user = $request->user();

        if ($user instanceof HasPermissions && $user->hasPermission($entity->notesPermission())) {
            return;
        }

        throw new AccessDeniedHttpException((string) __('webx-admin::notes.forbidden'));
    }

    /**
     * A note is edited by whoever wrote it, and the permission of the record it is on still
     * has to hold — an administrator who has lost the right to a section does not keep a door
     * into it through the notes they left there.
     */
    private function mine(Request $request, Note $note): void
    {
        $entity = $note->entity;

        if (! $entity instanceof Notable || ! $entity instanceof Model) {
            throw new NotFoundHttpException((string) __('webx-admin::notes.missing'));
        }

        $this->allowed($request, $entity);

        if (! $note->writtenBy($request->user())) {
            throw new AccessDeniedHttpException((string) __('webx-admin::notes.not-yours'));
        }
    }

    private function adminId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * The one name a just-written note needs: the writer's own.
     *
     * @return array<int, string>
     */
    private function name(Request $request): array
    {
        $id = $this->adminId($request);
        $user = $request->user();
        $name = is_object($user) && isset($user->name) ? (string) $user->name : '';

        return $id === null || $name === '' ? [] : [$id => $name];
    }
}
