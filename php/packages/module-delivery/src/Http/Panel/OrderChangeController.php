<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Cart\Money;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Checkout\StepView;
use WebxUi\Delivery\Countries;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Points;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Delivery\Shipping\OrderDeliveryChange;
use WebxUi\Delivery\Streets;
use WebxUi\Geography\LocalitySearch;
use WebxUi\Geography\LocalitySuggestion;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Localization\Locales;
use WebxUi\Orders\Blocks\BlockChangeResult;
use WebxUi\Orders\Models\Order;

/**
 * «Change delivery» of the tab «Delivery» (decision of 02.11.2026): the same search of a town,
 * the same methods, points and streets as the checkout's step (§12.2), but for the order's lines
 * and behind `orders.manage` — whoever edits the order. The save answers the tab again with
 * «was → is» of the sum; a dry run only the second.
 */
final class OrderChangeController
{
    public function __construct(
        private readonly OrderDeliveryChange $change,
        private readonly Locales $locales,
        private readonly Money $money,
    ) {}

    /** Towns by a prefix of a name, and the site's popular towns for an empty field. */
    public function localities(Request $request, int $order, LocalitySearch $search, PopularLocalities $popular): JsonResponse
    {
        $model = $this->order($order);
        $locale = $this->locales->current();
        $country = $this->country($request, $model);
        $q = trim((string) $request->query('q', ''));

        return ApiResponse::data([
            'localities' => $country === null || $q === '' ? [] : $this->towns($search->suggest($country, $q, $locale, 10)),
            'popular' => $country === null ? [] : $this->towns($popular->suggestions($country, $locale)),
        ]);
    }

    /** The methods of a town, priced for the order's lines — the step's rows. */
    public function methods(Request $request, int $order): JsonResponse
    {
        $model = $this->order($order);
        $locality = $this->locality($request);

        return ApiResponse::data([
            'locality' => ['id' => $locality->id, 'label' => $locality->label($this->locales->current())],
            'methods' => $this->change->methods($model, $locality, $this->locales->current()),
        ]);
    }

    /** The points of a method in a town, a page at a time, by a number or a piece of the address. */
    public function points(Request $request, int $order, Points $points, Methods $methods, MethodTypes $types): JsonResponse
    {
        $this->order($order);
        $locality = $this->locality($request);
        $method = $this->method($request, $methods);
        $type = $types->has($method->type) ? $types->get($method->type) : null;

        if ($type === null || $type->asks() !== Asks::Point) {
            throw new NotFoundHttpException;
        }

        $kind = $method->pointKind ?? ($type->pointKinds()[0] ?? '');
        $page = $points->page($method->carrier(), $kind, $locality->id, (string) $request->query('q', ''), max(1, (int) $request->query('page', 1)), $this->locales->current());

        return ApiResponse::data([
            'options' => array_map(StepView::pointOption(...), $page['points']),
            'total' => $page['total'],
            'page' => $page['page'],
            'more' => $page['more'],
        ]);
    }

    /** The streets of a town: a carrier's courier picks one of its directory, the shop's takes text. */
    public function streets(Request $request, int $order, Streets $streets, Methods $methods): JsonResponse
    {
        $this->order($order);
        $locality = $this->locality($request);
        $method = $this->method($request, $methods);
        $query = (string) $request->query('q', '');
        $locale = $this->locales->current();
        $found = $method->carrier() === Carriers::STORE
            ? $streets->forStore($locality->id, $query, $locale)
            : $streets->forCarrier($method->carrier(), $locality->id, $query, $locale);

        return ApiResponse::data([
            'options' => array_map(static fn ($street): array => ['value' => $street->streetId ?? $street->name, 'label' => $street->name], $found),
        ]);
    }

    /** The change itself; with `dry_run` — «was → will be» and nothing written. */
    public function update(Request $request, int $order, OrderDeliveryController $tab): JsonResponse
    {
        $model = $this->order($order);
        /** @var array<string, mixed> $input */
        $input = $request->validate([
            'locality' => ['required', 'integer'],
            'method' => ['required'],
            'point' => ['sometimes', 'nullable', 'integer'],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.street' => ['sometimes', 'nullable', 'string', 'max:190'],
            'address.street_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'address.house' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address.apartment' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address.postcode' => ['sometimes', 'nullable', 'string', 'max:16'],
            'address.note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);
        $dryRun = $request->boolean('dry_run');
        $reason = is_string($input['reason'] ?? null) ? $input['reason'] : null;
        unset($input['reason']);

        $done = $this->change->change($model, $input, 'panel', $reason, $dryRun);
        $answer = ['change' => $this->result($done['result'], $model->currency)];

        if ($dryRun) {
            return ApiResponse::data($answer);
        }

        return ApiResponse::data([...$answer, 'delivery' => $tab->card($request, $model->refresh())]);
    }

    /**
     * «Was → will be» in the order's money: the delivery's price and the total, the balance after.
     *
     * @return array<string, mixed>
     */
    public function result(BlockChangeResult $result, string $currency): array
    {
        $money = fn (int $minor): string => $this->money->format($minor, $currency);
        $kind = $result->balance->kind();

        return [
            'summary_from' => $result->summaryFrom,
            'summary_to' => $result->summaryTo,
            'amount_from' => $money($result->amountFrom),
            'amount_to' => $money($result->amountTo),
            'total_from' => $money($result->before['total']),
            'total_to' => $money($result->after['total']),
            'total_changes' => $result->totalChanges(),
            'balance' => in_array($kind, ['topup', 'over'], true) ? [
                'kind' => $kind,
                'amount' => $money($kind === 'over' ? $result->balance->over : $result->balance->due),
            ] : null,
            'dry_run' => $result->dryRun,
        ];
    }

    private function order(int $id): Order
    {
        return Order::query()->findOrFail($id);
    }

    private function locality(Request $request): GeoLocality
    {
        $id = $request->query('locality');
        $locality = is_numeric($id) ? GeoLocality::query()->with('region')->whereKey((int) $id)->where('active', true)->first() : null;

        if ($locality === null) {
            throw new NotFoundHttpException;
        }

        return $locality;
    }

    private function method(Request $request, Methods $methods): Method
    {
        $id = $request->query('method');

        foreach ($methods->catalogue()['methods'] as $row) {
            if (is_numeric($id) && (int) $row['id'] === (int) $id) {
                return Method::fromArray($row);
            }
        }

        throw new NotFoundHttpException;
    }

    /** The country asked, else the one the order's delivery is in, else the site's only one. */
    private function country(Request $request, Order $order): ?string
    {
        $asked = $request->query('country');

        if (is_string($asked) && preg_match('/^[A-Za-z]{2}$/', $asked) === 1) {
            return strtoupper($asked);
        }

        $country = is_array($order->delivery) ? ($order->delivery['country'] ?? null) : null;

        return is_string($country) && $country !== '' ? $country : app(Countries::class)->only();
    }

    /**
     * @param  list<LocalitySuggestion>  $towns
     * @return list<array<string, mixed>>
     */
    private function towns(array $towns): array
    {
        return array_map(static fn (LocalitySuggestion $town): array => ['id' => $town->id, 'name' => $town->name, 'label' => $town->label, 'region' => $town->region], $towns);
    }
}
