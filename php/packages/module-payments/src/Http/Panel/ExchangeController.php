<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http\Panel;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Exchange\Direction;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Panel\EventRows;
use WebxUi\Payments\Panel\PaymentsSection;
use WebxUi\Payments\Words;

/**
 * The exchange journal (spec §13.2, §20.7): every event newest first, a keyset page at a time,
 * filtered by gateway, method, attempt or order, kind, direction, bad signatures, errors and
 * period; one event with its trace; a body to download.
 *
 * Read only (decision 31): there is no route that changes or removes an event, for any right.
 * Headers and bodies are behind `payments.exchange` (decision 47); without it an event is the line.
 */
final class ExchangeController
{
    public const int PER_PAGE = 50;

    public function index(Request $request, Gateways $gateways): JsonResponse
    {
        $query = EventRows::query();

        if (! $this->filter($query, $request)) {
            return ApiResponse::data(['data' => [], 'next' => null, 'filters' => $this->filters($gateways)]);
        }

        $before = (int) $request->query('before', '0');
        $perPage = max(10, min(100, (int) $request->query('per_page', (string) self::PER_PAGE)));

        $rows = $query
            ->when($before > 0, static fn (Builder $q): Builder => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit($perPage + 1)
            ->get();

        $more = $rows->count() > $perPage;
        $page = $rows->take($perPage);

        return ApiResponse::data([
            'data' => $this->withNumbers($page->map(static fn (PaymentGatewayEvent $event): array => EventRows::line($event))->values()->all()),
            'next' => $more ? (int) $page->last()?->id : null,
            'filters' => $this->filters($gateways),
        ]);
    }

    /** One event and its trace — the neighbours of the same action (§13.2). */
    public function show(Request $request, int $event): JsonResponse
    {
        /** @var PaymentGatewayEvent $row */
        $row = PaymentGatewayEvent::query()->findOrFail($event);
        $trace = EventRows::query()->where('trace', $row->trace)->orderBy('id')->limit(100)->get();

        return ApiResponse::data([
            ...$this->withNumbers([EventRows::detail($row, $this->bodies($request))])[0],
            'trace_events' => $this->withNumbers($trace->map(static fn (PaymentGatewayEvent $one): array => EventRows::line($one))->values()->all()),
        ]);
    }

    /**
     * «Download» a body (§20.7): as stored, an attachment that never runs in the panel's page.
     */
    public function body(int $event, string $part): Response
    {
        abort_unless(in_array($part, ['request', 'response'], true), 404);

        /** @var PaymentGatewayEvent $row */
        $row = PaymentGatewayEvent::query()->findOrFail($event);
        $body = EventRows::body($row, $part);

        abort_if($body === null, 404);

        return new Response($body, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="exchange-'.$row->id.'-'.$part.'.bin"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * The filters of §20.7; false — the attempt or the order asked for does not exist, so nothing
     * can match.
     *
     * @param  Builder<PaymentGatewayEvent>  $query
     */
    private function filter(Builder $query, Request $request): bool
    {
        foreach (['gateway', 'method_key' => 'method', 'kind', 'direction'] as $column => $name) {
            $column = is_string($column) ? $column : $name;
            $value = $request->query($name);

            if (is_string($value) && $value !== '') {
                $query->where($column, $value);
            }
        }

        $payment = $request->query('payment');

        if (is_string($payment) && $payment !== '') {
            $id = Payment::query()->where('uuid', $payment)->value('id');

            if ($id === null) {
                return false;
            }

            $query->where('payment_id', $id);
        }

        // The tab of an order links here by its id; a person types the number.
        $order = $request->query('order');
        $orderId = $request->query('order_id');

        if (is_string($orderId) && ctype_digit($orderId)) {
            $query->where('order_id', (int) $orderId);
        } elseif (is_string($order) && $order !== '') {
            $id = Order::query()->where('number', $order)->value('id');

            if ($id === null) {
                return false;
            }

            $query->where('order_id', $id);
        }

        if ($request->boolean('signature')) {
            $query->where('signature', 'invalid');
        }

        if ($request->boolean('errors')) {
            $query->where(static function (Builder $q): void {
                $q->whereIn('outcome', EventRows::ERRORS)
                    ->orWhere('response_status', '>=', 400)
                    ->orWhereNotNull('transport_error')
                    ->orWhere('signature', 'invalid');
            });
        }

        foreach (['from' => '>=', 'to' => '<='] as $name => $operator) {
            $value = $request->query($name);

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $day = CarbonImmutable::parse($value);
                $query->where('created_at', $operator, $operator === '>=' ? $day->startOfDay() : $day->endOfDay());
            }
        }

        return true;
    }

    /**
     * The numbers of the orders the lines name, by one query.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function withNumbers(array $lines): array
    {
        $ids = array_values(array_unique(array_filter(array_column($lines, 'order_id'))));
        $numbers = $ids === [] ? [] : Order::query()->whereKey($ids)->pluck('number', 'id')->all();
        $payments = array_values(array_unique(array_filter(array_column($lines, 'payment_id'))));
        $uuids = $payments === [] ? [] : Payment::query()->whereKey($payments)->pluck('uuid', 'id')->all();

        return array_map(static fn (array $line): array => [
            ...$line,
            'order_number' => $numbers[$line['order_id']] ?? null,
            'payment_uuid' => $uuids[$line['payment_id']] ?? null,
        ], $lines);
    }

    /** @return array<string, mixed> what the filters offer */
    private function filters(Gateways $gateways): array
    {
        $locale = app()->getLocale();

        return [
            'gateways' => array_values(array_map(static fn ($gateway): array => ['value' => $gateway->key(), 'label' => $gateway->title($locale)], $gateways->all())),
            'methods' => PaymentMethod::query()->orderBy('position')->get(['key', 'title'])
                ->map(static fn (PaymentMethod $method): array => ['value' => $method->key, 'label' => Words::pick($method->title, $locale) ?? $method->key])
                ->values()
                ->all(),
            'kinds' => array_map(static fn (ExchangeKind $kind): string => $kind->value, ExchangeKind::cases()),
            'directions' => array_map(static fn (Direction $direction): string => $direction->value, Direction::cases()),
        ];
    }

    private function bodies(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof HasPermissions && $user->hasPermission(PaymentsSection::EXCHANGE);
    }
}
