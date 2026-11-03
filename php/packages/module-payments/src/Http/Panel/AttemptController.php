<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http\Panel;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
    /** The tabs of the list: which statuses each shows. */
    public const array TABS = [
        'pending' => [PaymentStatus::Created, PaymentStatus::Pending, PaymentStatus::Authorized],
        'failed' => [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired],
    ];

    public function __construct(
        private readonly AttemptRows $rows,
        private readonly EventNames $names,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with('order:id,number');
        $tab = $request->query('tab');

        if (is_string($tab) && isset(self::TABS[$tab])) {
            $query->whereIn('status', array_map(static fn (PaymentStatus $status): string => $status->value, self::TABS[$tab]));
        } elseif ($tab === 'problems') {
            $query->whereNotNull('problem');
        }

        $method = $request->query('method');

        if (is_string($method) && $method !== '') {
            $query->where('method_key', $method);
        }

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(static function (Builder $q) use ($search): void {
                $q->where('uuid', $search)
                    ->orWhere('gateway_ref', $search)
                    ->orWhereHas('order', static fn (Builder $order): Builder => $order->where('number', $search));
            });
        }

        foreach (['from' => '>=', 'to' => '<='] as $name => $operator) {
            $value = $request->query($name);

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $day = CarbonImmutable::parse($value);
                $query->where('created_at', $operator, $operator === '>=' ? $day->startOfDay() : $day->endOfDay());
            }
        }

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
