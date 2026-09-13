<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Manifest\ManifestBuilder;

final class ManifestController
{
    public function __invoke(ManifestBuilder $manifest): JsonResponse
    {
        return ApiResponse::data($manifest->build());
    }
}
