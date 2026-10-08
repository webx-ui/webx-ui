<?php

declare(strict_types=1);

namespace WebxUi\Media\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A delete refused because the site still uses some of the files — the same rule
 * `media_delete_files` keeps for an agent. Answered with where, so the panel can ask
 * «delete anyway» with the places in front of the person; `force` is that answer.
 */
final class FilesInUse extends RuntimeException implements Responsable
{
    /**
     * @param  list<array{id: int, name: string, used_in: list<array<string, mixed>>}>  $inUse
     */
    public function __construct(public readonly array $inUse)
    {
        parent::__construct('Some of the files are still in use.');
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse([
            'message' => __('webx-media::errors.files-in-use'),
            'code' => 'files_in_use',
            'in_use' => $this->inUse,
        ], 409);
    }
}
