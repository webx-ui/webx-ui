<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Cache\CacheGroups;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Panel\MethodForm;
use WebxUi\Delivery\PointKinds;
use WebxUi\Localization\Locales;

/**
 * The methods (§14.2): the whole list in the order of the checkout — dragged here — and one
 * method as its form opens it. A new method is a type first: the list asks which, the form
 * follows.
 */
final class MethodController
{
    public function __construct(
        private readonly MethodForm $form,
        private readonly Locales $locales,
    ) {}

    public function index(): JsonResponse
    {
        $locale = $this->locales->current();

        return ApiResponse::data([
            'methods' => DeliveryMethod::query()->orderBy('position')->orderBy('id')->get()
                ->map(fn (DeliveryMethod $method): array => $this->form->row($method, $locale))
                ->values()
                ->all(),
            'types' => $this->form->types(),
        ]);
    }

    public function show(DeliveryMethod $method): JsonResponse
    {
        return ApiResponse::data($this->form->describe($method));
    }

    /** The first save of a new method — `type` beside the form's values. */
    public function store(Request $request, MethodTypes $types): JsonResponse
    {
        $type = (string) $request->input('type');

        if (! $types->has($type)) {
            throw ValidationException::withMessages(['type' => [__('webx-delivery::panel.errors.unknown_type')]]);
        }

        $method = new DeliveryMethod(['type' => $type]);
        $method = $this->form->save($method, (array) $request->input('values', []), $this->can($request));

        return ApiResponse::data($this->form->describe($method), 201);
    }

    /** What a new method of this type opens with: its fields, the kinds of its points. */
    public function blank(Request $request, MethodTypes $types): JsonResponse
    {
        $type = (string) $request->query('type');

        if (! $types->has($type)) {
            throw ValidationException::withMessages(['type' => [__('webx-delivery::panel.errors.unknown_type')]]);
        }

        return ApiResponse::data([
            'hidden' => $this->form->hidden($type),
            'point_kinds' => $this->form->pointKindOptions($type, $this->locales->current()),
            'type_title' => $this->form->typeTitle($type, $this->locales->current()),
        ]);
    }

    public function update(Request $request, DeliveryMethod $method): JsonResponse
    {
        $method = $this->form->save($method, (array) $request->input('values', []), $this->can($request));

        return ApiResponse::data($this->form->describe($method));
    }

    /** Gone from the checkout at once; an order made with it keeps its snapshot (§11.1). */
    public function destroy(DeliveryMethod $method): JsonResponse
    {
        $method->delete();

        return ApiResponse::noContent();
    }

    /** The order of the checkout, as dragged: every id of the list once. */
    public function order(Request $request, CacheGroups $groups): JsonResponse
    {
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        $known = DeliveryMethod::query()->pluck('id')->map(intval(...))->sort()->values()->all();
        $sorted = $ids;
        sort($sorted);

        if ($sorted !== $known) {
            throw ValidationException::withMessages(['ids' => [__('webx-delivery::panel.errors.order')]]);
        }

        DB::transaction(static function () use ($ids): void {
            foreach ($ids as $position => $id) {
                DeliveryMethod::query()->whereKey($id)->update(['position' => $position]);
            }
        });

        // A mass update fires no model events: the checkout's list is cached (§17).
        $groups->bump(Methods::GROUP);

        return ApiResponse::noContent();
    }

    /** A kind of the shop's own points, named by hand (decision 9): the «new kind» of the form. */
    public function defineKind(Request $request, PointKinds $kinds): JsonResponse
    {
        $kind = (string) $request->input('kind');

        try {
            $kinds->define($kind, (array) $request->input('names', []));
        } catch (DeliveryException|\InvalidArgumentException $refusal) {
            throw ValidationException::withMessages(['kind' => [$refusal->getMessage()]]);
        }

        return ApiResponse::data($this->form->pointKindOptions(null, $this->locales->current()), 201);
    }

    /** @return callable(string): bool */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
