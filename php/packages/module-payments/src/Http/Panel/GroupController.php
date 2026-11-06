<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Payments\MethodGroups;
use WebxUi\Payments\Panel\GroupForm;

/**
 * The groups of the screen of methods (groups spec §7): read and edited, never created or removed —
 * a key the config does not list is not found (decision 12). Their order goes with the methods, by
 * `PUT methods/order`.
 */
final class GroupController
{
    public function __construct(private readonly GroupForm $form) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data($this->form->describe());
    }

    public function update(Request $request, MethodGroups $groups, string $key): JsonResponse
    {
        abort_unless($groups->has($key), 404);

        return ApiResponse::data($this->form->save($key, $request->only(['titles', 'descriptions', 'pictogram'])));
    }
}
