<?php

declare(strict_types=1);

namespace WebxUi\Media\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Every file belongs to a folder, so the library needs a floor it cannot lose.
 */
final class RootIsImmutable extends RuntimeException implements Responsable
{
    public function __construct()
    {
        parent::__construct('The library root cannot be moved or deleted.');
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse([
            'message' => __('webx-media::errors.root-immutable'),
            'code' => 'root_immutable',
        ], 422);
    }
}
