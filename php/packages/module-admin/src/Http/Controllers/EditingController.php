<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\Editing\LastChange;
use WebxUi\Admin\Editing\Presence;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Versions\EntityVersion;

/**
 * The heartbeat of an open editor.
 *
 * An editor on screen calls this every {@see Presence::HEARTBEAT} seconds. The call says "I am
 * here" — which is what an agent reads before writing — and the answer says what the editor
 * cannot see from where it is: the revision the record is at now, who changed it last and
 * through which door, and who else has it open. A revision that is not the one the editor holds
 * is somebody else's save, and the editor offers to pull it in before its own save is refused.
 */
final class EditingController
{
    public function __construct(
        private readonly EditedRecords $records,
        private readonly Presence $presence,
    ) {}

    public function ping(Request $request, string $entity, string $id): JsonResponse
    {
        $found = $this->found($request, $entity, $id);
        $user = $request->user();
        $adminId = $this->adminId($request);

        if ($adminId !== null) {
            $this->presence->touch($found['model'], $adminId, $this->name($user));
        }

        return ApiResponse::data([
            'revision' => $found['revision'],
            'changed' => LastChange::of($found['model'], $user),
            'editors' => $this->presence->of($found['model'], $adminId),
            'heartbeat' => Presence::HEARTBEAT,
        ]);
    }

    /** The editor was closed: it stops counting at once rather than a minute later. */
    public function leave(Request $request, string $entity, string $id): JsonResponse
    {
        $adminId = $this->adminId($request);

        $found = $this->records->has($entity) ? $this->records->find($entity, $id) : null;

        if ($found !== null && $adminId !== null) {
            $this->presence->leave($found['model'], $adminId);
        }

        return ApiResponse::noContent();
    }

    /**
     * The copies of the draft, newest first: the autosave ring and the drafts somebody else's
     * save wrote over. The history lists publications; this is what lies between them, so that
     * an agent's edit an editor saved over — or the other way round — can be found and put back.
     */
    public function drafts(Request $request, string $entity, string $id): JsonResponse
    {
        $model = $this->found($request, $entity, $id)['model'];

        if (! method_exists($model, 'draftVersions')) {
            return ApiResponse::data([]);
        }

        /** @var Collection<int, EntityVersion> $versions */
        $versions = $model->draftVersions()->get();
        $authors = Authors::names($request->user(), $versions->map(static fn (EntityVersion $version): ?int => $version->author_id));

        return ApiResponse::data($versions->map(static fn (EntityVersion $version): array => [
            'id' => $version->id,
            'kind' => $version->kind,
            'created_at' => $version->created_at?->toAtomString(),
            'author' => $version->author_id === null ? null : ($authors[$version->author_id] ?? null),
            'source' => $version->source,
            'fields' => array_keys($version->payload),
        ])->values()->all());
    }

    /**
     * Put a copy of the draft back as the draft. What is in the draft now is not lost by it: a
     * draft written by somebody else is kept aside on the way, the same as on any save.
     */
    public function restoreDraft(Request $request, string $entity, string $id, int $version): JsonResponse
    {
        $model = $this->found($request, $entity, $id, write: true)['model'];

        if (! method_exists($model, 'draftVersions') || ! method_exists($model, 'restoreVersion')) {
            throw new NotFoundHttpException;
        }

        $found = $model->draftVersions()->whereKey($version)->first();

        if (! $found instanceof EntityVersion) {
            throw new NotFoundHttpException;
        }

        $model->restoreVersion($found);

        $after = $this->records->find($entity, $id);

        return ApiResponse::data(['restored' => $found->id, 'revision' => $after['revision'] ?? null]);
    }

    /**
     * @return array{revision: string, model: Model}
     */
    private function found(Request $request, string $entity, string $id, bool $write = false): array
    {
        if (! $this->records->has($entity)) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();
        $allowed = false;

        foreach ($write ? $this->records->writePermissions($entity) : $this->records->permissions($entity) as $permission) {
            if ($user instanceof HasPermissions && $user->hasPermission($permission)) {
                $allowed = true;

                break;
            }
        }

        if (! $allowed) {
            throw new AccessDeniedHttpException;
        }

        return $this->records->find($entity, $id) ?? throw new NotFoundHttpException;
    }

    private function adminId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private function name(mixed $user): string
    {
        $name = is_object($user) && isset($user->name) ? $user->name : null;

        return is_string($name) ? $name : '';
    }
}
