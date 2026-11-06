<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Cache\CacheGroups;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Panel\GroupForm;
use WebxUi\Payments\Panel\MethodForm;

/**
 * The methods (spec §13.2, §13.3): the list in the checkout's order — dragged here — one method as
 * its form opens it, a new one of a chosen type, its keys and «Test the connection».
 *
 * A method is switched off, not deleted: an order keeps the key of the method it was paid with,
 * and the journal names it.
 */
final class MethodController
{
    public function __construct(private readonly MethodForm $form) {}

    public function index(): JsonResponse
    {
        $similar = $this->form->similar();
        $rows = PaymentMethod::query()->orderBy('position')->orderBy('id')->get()
            ->map(fn (PaymentMethod $method): array => $this->form->row($method, $similar))
            ->values()
            ->all();

        return ApiResponse::data([
            'methods' => $rows,
            'types' => $this->form->types(),
            'marks' => (object) $this->form->marks($rows),
        ]);
    }

    public function show(PaymentMethod $method): JsonResponse
    {
        return ApiResponse::data($this->form->describe($method));
    }

    /** What a new method of this type opens with: its fields and keys, nothing set. */
    public function blank(Request $request, MethodTypes $types): JsonResponse
    {
        return ApiResponse::data($this->form->blank($this->type($request->query('type'), $types)));
    }

    /** The first save of a new method — `type` beside the form's values. */
    public function store(Request $request, MethodTypes $types): JsonResponse
    {
        $method = new PaymentMethod(['type' => $this->type($request->input('type'), $types)]);

        return ApiResponse::data($this->form->describe($this->save($request, $method)), 201);
    }

    public function update(Request $request, PaymentMethod $method): JsonResponse
    {
        return ApiResponse::data($this->form->describe($this->save($request, $method)));
    }

    /**
     * The order of the checkout, as dragged: every id of the list once — by group where the site
     * has groups (`GroupForm::order()`), as one list where it has none.
     */
    public function order(Request $request, CacheGroups $groups, GroupForm $form): JsonResponse
    {
        if ($request->has('groups')) {
            $form->order($request->input('groups'));

            return ApiResponse::noContent();
        }

        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        $known = PaymentMethod::query()->pluck('id')->map(intval(...))->sort()->values()->all();
        $sorted = $ids;
        sort($sorted);

        if ($sorted !== $known) {
            throw ValidationException::withMessages(['ids' => [__('webx-payments::panel.errors.order')]]);
        }

        DB::transaction(static function () use ($ids): void {
            foreach ($ids as $position => $id) {
                PaymentMethod::query()->whereKey($id)->update(['position' => $position]);
            }
        });

        // A mass update fires no model events: the step's list is cached (§18).
        $groups->bump(Methods::GROUP);

        return ApiResponse::noContent();
    }

    /** «Test the connection» with the stored keys and any typed over them, not saved (§13.3). */
    public function test(Request $request, PaymentMethod $method): JsonResponse
    {
        $result = $this->form->test($method, (array) $request->input('credentials', []));

        return ApiResponse::data(['ok' => $result->ok, 'message' => $result->message]);
    }

    private function save(Request $request, PaymentMethod $method): PaymentMethod
    {
        $settings = $request->input('settings');
        $credentials = $request->input('credentials');
        $clear = $request->input('clear');
        $user = $request->user();

        return $this->form->save(
            $method,
            (array) $request->input('values', []),
            is_array($settings) ? $settings : null,
            is_array($credentials) ? $credentials : null,
            is_array($clear) ? array_values(array_filter($clear, 'is_string')) : null,
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
        );
    }

    private function type(mixed $key, MethodTypes $types): string
    {
        if (! is_string($key) || ! $types->has($key)) {
            throw ValidationException::withMessages(['type' => [__('webx-payments::panel.errors.unknown_type')]]);
        }

        return $key;
    }
}
