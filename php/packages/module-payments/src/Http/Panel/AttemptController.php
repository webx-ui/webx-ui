<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Panel\AttemptQuery;
use WebxUi\Payments\Panel\AttemptRows;
use WebxUi\Payments\Panel\EventNames;
use WebxUi\Payments\Panel\EventRows;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Words;

/**
 * The journal of attempts (spec §13.2): Laravel's `->paginate()` newest first, by the tabs «All»,
 * «In progress», «Failed» and «Problems», a method and a period; one attempt with its events of the
 * exchange by time and «Check with the gateway» — an action on the order, so `orders.manage`.
 */
final class AttemptController
{
    public function __construct(
        private readonly AttemptRows $rows,
        private readonly EventNames $names,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = AttemptQuery::filter(Payment::query()->with('order:id,number'), $request->query());

        $page = $query->orderByDesc('id')->paginate(max(10, min(100, (int) $request->query('per_page', '25'))));
        $lines = $this->rows->lines($page->getCollection());
        $locale = app()->getLocale();

        return new JsonResponse([
            ...$page->toArray(),
            'data' => $lines,
            'filters' => [
                'methods' => PaymentMethod::query()->orderBy('position')->get(['key', 'title'])
                    ->map(static fn (PaymentMethod $one): array => ['value' => $one->key, 'label' => Words::pick($one->title, $locale) ?? $one->key])
                    ->values()
                    ->all(),
                'problems' => Payment::query()->whereNotNull('problem')->count(),
            ],
        ]);
    }

    public function show(Request $request, int $payment): JsonResponse
    {
        /** @var Payment $row */
        $row = Payment::query()->with('order:id,number')->findOrFail($payment);

        return ApiResponse::data($this->card($request, $row));
    }

    /** «Check with the gateway» (§13.2): asks and settles; nothing new — the attempt as it was. */
    public function check(Request $request, int $payment, Payments $payments): JsonResponse
    {
        /** @var Payment $row */
        $row = Payment::query()->with('order:id,number')->findOrFail($payment);

        try {
            $payments->check($row, 'panel', wait: 5);
        } catch (GatewayUnavailable $failure) {
            throw ValidationException::withMessages(['payment' => [__('webx-payments::panel.errors.gateway_unavailable')]]);
        }

        return ApiResponse::data($this->card($request, $row->refresh()->load('order:id,number')));
    }

    /** @return array<string, mixed> */
    private function card(Request $request, Payment $payment): array
    {
        $user = $request->user();

        return [
            ...$this->rows->named([$this->rows->line($payment)], $user)[0],
            'events' => $this->names->apply(EventRows::query()->where('payment_id', $payment->id)->orderBy('id')->limit(200)->get()
                ->map(static fn (PaymentGatewayEvent $event): array => EventRows::line($event))
                ->values()
                ->all(), $user),
            'can_check' => $user instanceof HasPermissions && $user->hasPermission(OrdersModule::MANAGE)
                && in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Authorized], true),
        ];
    }
}
