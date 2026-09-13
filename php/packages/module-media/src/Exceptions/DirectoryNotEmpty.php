<?php

declare(strict_types=1);

namespace WebxUi\Media\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Answered rather than thrown at the user: the panel needs the counts to ask its question, and
 * the question is worth asking — deleting a folder takes pictures that articles already point at.
 */
final class DirectoryNotEmpty extends RuntimeException implements Responsable
{
    public function __construct(
        public readonly int $files,
        public readonly int $directories,
    ) {
        parent::__construct('The folder is not empty.');
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse([
            'message' => __('webx-media::errors.directory-not-empty'),
            'code' => 'directory_not_empty',
            'counts' => [
                'files' => $this->files,
                'directories' => $this->directories,
            ],
        ], 409);
    }
}
