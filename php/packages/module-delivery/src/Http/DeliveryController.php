<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\CartDelivery;
use WebxUi\Delivery\Checkout\StepView;
use WebxUi\Delivery\Countries;
use WebxUi\Delivery\Delivery;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Delivery\Points;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Delivery\Selections;
use WebxUi\Delivery\Streets;
use WebxUi\Geography\LocalitySearch;
use WebxUi\Geography\LocalitySuggestion;

/**
 * The doors of the delivery step (§12.2). The suggestions — countries, towns, popular towns,
 * points, streets — are everybody's: no cart, no session, five minutes in the browser's cache.
 * The methods and the choice are the visitor's: the cart of `CartHandle`, never an id from the
 * address; without a cart — 404.
 *
 * `PUT selection` from the script answers JSON, and with `X-Webx-Delivery-View: step` the step
 * drawn again as `html`; a form without JavaScript goes back to its page — the refusal by field in
 * the session, as Laravel answers any form.
 */
final class DeliveryController
{
    /** The header the script asks the redrawn step with. */
    public const string VIEW_HEADER = 'X-Webx-Delivery-View';

    private const string PUBLIC_CACHE = 'public, max-age=300';

    public function __construct(
        private readonly CartHandle $handle,
        private readonly Calculator $calculator,
        private readonly Methods $methods,
        private readonly MethodTypes $types,
        private readonly Delivery $delivery,
        private readonly CartDelivery $carts,
    ) {}

    public function countries(Countries $countries): JsonResponse
    {
        return $this->public(['countries' => $countries->active($this->handle->locale())]);
    }

    public function localities(Request $request, LocalitySearch $search): JsonResponse
    {
        $country = $this->country($request);
        $suggestions = $country === null ? [] : $search->suggest($country, (string) $request->query('q', ''), $this->handle->locale(), 10);

        return $this->public($this->towns($suggestions));
    }

    public function popular(Request $request, PopularLocalities $popular): JsonResponse
    {
        $country = $this->country($request);

        return $this->public($this->towns($country === null ? [] : $popular->suggestions($country, $this->handle->locale())));
    }

    public function points(Request $request, Points $points): JsonResponse
    {
        [$method, $locality] = $this->methodIn($request);
        $type = $this->types->has($method->type) ? $this->types->get($method->type) : null;

        if ($type === null || $type->asks() !== Asks::Point) {
            throw new NotFoundHttpException;
        }

        $kind = $method->pointKind ?? ($type->pointKinds()[0] ?? '');
        $page = $points->page($method->carrier(), $kind, $locality, (string) $request->query('q', ''), max(1, (int) $request->query('page', 1)), $this->handle->locale());

        return $this->public([
            // The list with a search reads `options`; the rest is for whoever draws its own list.
            'options' => array_map(StepView::pointOption(...), $page['points']),
            'points' => $page['points'],
            'total' => $page['total'],
            'page' => $page['page'],
            'more' => $page['more'],
        ]);
    }

    public function point(int $id, Points $points): JsonResponse
    {
        $card = $points->card($id, $this->handle->locale());

        if ($card === null) {
            throw new NotFoundHttpException;
        }

        return $this->public(['point' => $card]);
    }

    public function streets(Request $request, Streets $streets): JsonResponse
    {
        [$method, $locality] = $this->methodIn($request);
        $query = (string) $request->query('q', '');
        $locale = $this->handle->locale();
        $found = $method->carrier() === Carriers::STORE
            ? $streets->forStore($locality, $query, $locale)
            : $streets->forCarrier($method->carrier(), $locality, $query, $locale);

        return $this->public([
            // A carrier's street is chosen by its id; the shop's courier takes the name as text (§8.3).
            'options' => array_map(static fn ($street): array => ['value' => $street->streetId ?? $street->name, 'label' => $street->name], $found),
            'streets' => array_map(static fn ($street): array => $street->toArray(), $found),
        ]);
    }

    public function methods(Request $request): JsonResponse
    {
        [$cart, $state] = $this->cart();
        $locality = is_numeric($request->query('locality')) ? $this->carts->locality((int) $request->query('locality')) : null;

        if ($locality === null || ! $locality->active) {
            throw new NotFoundHttpException;
        }

        $view = app(StepView::class);
        $context = $this->delivery->context($state, $locality, Purpose::Checkout, $state->locale);

        return new JsonResponse([
            'locality' => ['id' => $locality->id, 'label' => $locality->label($state->locale)],
            'methods' => array_map(static fn ($option): array => $view->method($option, $state, $state->locale), $this->delivery->methods($context)),
        ]);
    }

    public function selection(Request $request): JsonResponse
    {
        [$cart, $state] = $this->cart();

        return $this->answer($request, $cart, $state, $this->carts->selection($cart->id));
    }

    public function update(Request $request, Selections $selections): JsonResponse|RedirectResponse
    {
        $prefix = $request->input('_prefix');
        $input = is_string($prefix) && $prefix !== '' && is_array($request->input($prefix)) ? $request->input($prefix) : $request->all();
        [$cart, $state] = $this->cart();
        $result = $selections->put($cart->id, $state, $input, $state->locale);

        if (! $request->expectsJson()) {
            $back = Redirect::to(url()->previous().'#'.StepView::ID);

            return $result->ok() ? $back : $back->withErrors($result->errors)->withInput();
        }

        if (! $result->ok()) {
            return new JsonResponse(['message' => array_values($result->errors)[0], 'errors' => array_map(static fn (string $m): array => [$m], $result->errors)], 422);
        }

        // The cart is not counted again: the choice changes the delivery's own line and nothing the
        // methods are priced from (§17: the write is 6 queries over the cart's). The checkout redraws
        // its column of the totals itself on the event of the step.
        return $this->answer($request, $cart, $state, $result->selection);
    }

    /** The choice with its price, and the step drawn again when the script asks for it. */
    private function answer(Request $request, Cart $cart, CartState $state, ?DeliverySelection $selection): JsonResponse
    {
        $cost = $this->delivery->cost($state, $cart->id);
        $body = [
            'selection' => $selection === null ? null : [
                'country' => $selection->country_code,
                'locality' => $selection->locality_id,
                'method' => $selection->method_id,
                'point' => $selection->point_id,
                'address' => $selection->address,
            ],
            'cost' => $cost?->toArray(),
        ];

        if ($request->headers->get(self::VIEW_HEADER) === 'step') {
            $body['html'] = view('webx-delivery::step', [
                'step' => app(StepView::class)->build($state, $cart->id, $state->locale),
                'event' => $request->headers->get('X-Webx-Delivery-Event') ?: null,
            ])->render();
        }

        return new JsonResponse($body);
    }

    /**
     * The visitor's cart counted for the checkout; none — 404, there is nothing to deliver.
     *
     * @return array{0: Cart, 1: CartState}
     */
    private function cart(): array
    {
        $cart = $this->handle->cart();

        if ($cart === null) {
            throw new NotFoundHttpException;
        }

        return [$cart, $this->calculator->calculate($cart, $this->handle->locale(), Purpose::Checkout)];
    }

    /**
     * The method by its key and the locality of the request: an unknown method — 404.
     *
     * @return array{0: Method, 1: int}
     */
    private function methodIn(Request $request): array
    {
        $key = (string) $request->query('method', '');
        $locality = $request->query('locality');

        foreach ($this->methods->catalogue()['methods'] as $row) {
            if (($row['key'] ?? null) === $key && is_numeric($locality)) {
                return [Method::fromArray($row), (int) $locality];
            }
        }

        throw new NotFoundHttpException;
    }

    private function country(Request $request): ?string
    {
        $country = $request->query('country');

        return is_string($country) && preg_match('/^[A-Za-z]{2}$/', $country) === 1 ? strtoupper($country) : app(Countries::class)->only();
    }

    /**
     * @param  list<LocalitySuggestion>  $towns
     * @return array<string, mixed>
     */
    private function towns(array $towns): array
    {
        return [
            'options' => array_map(static fn (LocalitySuggestion $s): array => ['value' => (string) $s->id, 'label' => $s->name, 'hint' => $s->region], $towns),
            'localities' => array_map(static fn (LocalitySuggestion $s): array => $s->toArray(), $towns),
        ];
    }

    /** @param  array<string, mixed>  $body */
    private function public(array $body): JsonResponse
    {
        return (new JsonResponse($body))->header('Cache-Control', self::PUBLIC_CACHE);
    }
}
