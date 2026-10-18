<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Manifest\ManifestBuilder;

final class ManifestController
{
    public function __invoke(Request $request, ManifestBuilder $manifest): JsonResponse
    {
        return ApiResponse::data($manifest->build($request->user()));
    }
}
