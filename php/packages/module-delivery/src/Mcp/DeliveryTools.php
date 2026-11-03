<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use WebxUi\Cache\CacheGroups;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\Benefits;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Delivery;
use WebxUi\Delivery\MethodOption;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Delivery\Panel\LocalityRows;
use WebxUi\Delivery\Panel\MethodForm;
use WebxUi\Delivery\Panel\PointForm;
use WebxUi\Delivery\PointKinds;
use WebxUi\Geography\Normalizer;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * The methods, the trial quote and the points for an agent (§15) — by the panel's own doors:
 * a method is saved by {@see MethodForm} and a point by {@see PointForm}, so a field the screen
 * refuses is refused here too; the quote is {@see Methods::evaluate()}, the same judge the checkout
 * asks, which says of every method why it is not offered.
 */
final class DeliveryTools
{
    use ToolSupport;

    /** What a reason of {@see MethodOption} means, for the agent. */
    public const array REASONS = [
        MethodOption::TYPE_MISSING => 'The type of the method is not registered: the package that brought it was removed.',
        MethodOption::CARRIER_OFF => 'The carrier is switched off on the site, or not installed.',
        MethodOption::COUNTRY => 'The locality is in a country the method does not serve.',
        MethodOption::NO_POINTS => 'No active point of the method in the locality.',
        MethodOption::NOT_BOUND => 'The carrier has no binding for the locality, or does not deliver to doors there.',
        MethodOption::NOT_AVAILABLE => 'The type of the method does not serve this locality or this cart.',
        MethodOption::NO_STREETS => 'The carrier has no streets in the locality: the courier is shown greyed out and cannot be chosen.',
    ];

    public function __construct(private readonly Container $container) {}

    /** @return list<Tool> */
    public function all(): array
    {
        $method = ['type' => 'string', 'description' => 'The method by its key — "store-pickup", as delivery_methods_list gives it.'];
        $locality = ['type' => ['integer', 'string'], 'description' => 'A locality: its id, or its exact name when only one place has it.'];
        $text = ['type' => ['string', 'object'], 'description' => 'One language as a string (the default one), or every language as { "en": "…" }.'];

        return Arguments::strictAll([
            Tool::read(
                'methods_list',
                'The delivery methods in the order of the checkout: key, type, carrier, title in every language, price '
                .'(minor units and in words), countries, enabled, position, whether it honours the privilege '
                .'shipping.paid_by_store, the kind of its points and the localities of a courier of the shop.',
                fn (): array => $this->methods(),
            ),

            Tool::mutating(
                'methods_save',
                'Create a method of a type (`type`, as delivery://catalog lists them) or change one by its `key`. '
                .'`values`: titles and descriptions by language, price in major units (4.5 is 4.50; null — the '
                .'type prices it), countries (ISO codes; empty — every country of the type), point_kind, localities '
                .'(ids or names — where a courier of the shop goes), honours_paid_by_store, enabled. A field left out '
                .'keeps what it had. A new method is switched off until `enabled: true`.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->saveMethod($arguments, $user)),
                ['properties' => [
                    'key' => $method,
                    'type' => ['type' => 'string', 'description' => 'The type of a new method: "store.pickup", "store.courier" or a carrier\'s.'],
                    'values' => ['type' => 'object', 'description' => 'Field → value; see the description.'],
                ], 'required' => ['values']],
            ),

            Tool::mutating(
                'methods_reorder',
                'Put the methods in a new order — the order the checkout lists them in. Name them by key, first to '
                .'last; those left out follow in the order they had.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'methods' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Method keys, first to last.'],
                ], 'required' => ['methods']],
            ),

            Tool::read(
                'quote',
                'A trial of the checkout: every enabled method for a locality and a cart, with its price or the reason it '
                .'is not offered (no points in the locality, a country it does not serve, the carrier switched off, no '
                .'streets of the carrier, the carrier\'s own refusal). The cart is a real one (`cart_id`) or lines '
                .'(`items`: { type, ref, qty }) counted and then thrown away; `paid_by_store` counts it as if the buyer '
                .'had the privilege shipping.paid_by_store. Nothing is kept.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->quote($arguments)),
                ['properties' => [
                    'locality' => $locality,
                    'cart_id' => ['type' => 'integer', 'description' => 'A cart of the site, as cart_list gives it.'],
                    'items' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Lines instead of a cart: [{ "type": "catalog.product", "ref": 12, "qty": 1 }].'],
                    'paid_by_store' => ['type' => 'boolean', 'description' => 'Count the cart as if it had the privilege shipping.paid_by_store.'],
                ], 'required' => ['locality']],
            ),

            Tool::read(
                'points_search',
                'Points of delivery — the shop\'s own and the carriers\' — twenty-five a page: by locality, carrier '
                .'("store" — the shop\'s own), kind, a piece of the name, number or address, and state (active by '
                .'default; inactive, removed by the sync, or all).',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->points($arguments)),
                ['properties' => [
                    'locality' => $locality,
                    'carrier' => ['type' => 'string', 'description' => 'A carrier\'s key, or "store".'],
                    'kind' => ['type' => 'string', 'description' => 'branch, locker, store, pickup or a kind of the shop\'s own.'],
                    'q' => ['type' => 'string', 'description' => 'A piece of the name, the number or the address.'],
                    'state' => ['type' => 'string', 'enum' => ['active', 'inactive', 'removed', 'all'], 'description' => 'active by default.'],
                    'page' => ['type' => 'integer', 'description' => '1 by default.'],
                ]],
            ),

            Tool::mutating(
                'points_save',
                'A point of the shop\'s own: create it (no `point`) or change one. `values`: kind (store, pickup or a kind '
                .'of the shop\'s own), titles and address by language, locality (id or name — delivery_localities_save '
                .'makes a missing one), lat, lng, phone, active, schedule ({ "mon": [["09:00","18:00"]], … }). A field '
                .'left out keeps what it had. The carriers\' points are theirs: the sync keeps them.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->savePoint($arguments, $user)),
                ['properties' => [
                    'point' => ['type' => 'integer', 'description' => 'The point to change; a new one when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'Field → value; see the description.'],
                ], 'required' => ['values']],
            ),

            Tool::mutating(
                'points_deactivate',
                'Switch a point of the shop\'s own off: it leaves the checkout and is kept — delivery_points_save with '
                .'active: true brings it back. A buyer who chose it chooses again. A dry run says how many carts hold it.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->deactivate($arguments)),
                ['properties' => ['point' => ['type' => 'integer', 'description' => 'The point\'s id.']], 'required' => ['point']],
            ),
        ], 'delivery_');
    }

    // Methods

    /** @return array<string, mixed> */
    private function methods(): array
    {
        $locale = $this->locale();
        $form = $this->form();
        $methods = DeliveryMethod::query()->with('localities:id')->orderBy('position')->orderBy('id')->get();

        return [
            'methods' => $methods->map(fn (DeliveryMethod $method): array => $this->method($method, $form, $locale))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function method(DeliveryMethod $method, MethodForm $form, string $locale): array
    {
        return [
            ...$form->row($method, $locale),
            'titles' => $method->titles,
            'descriptions' => $method->descriptions ?? [],
            'point_kind' => $method->point_kind,
            'honours_paid_by_store' => $method->honours_paid_by_store,
            'localities' => $method->localities->pluck('id')->map(intval(...))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function saveMethod(array $arguments, ?Authenticatable $user): array
    {
        $values = $arguments['values'] ?? null;

        if (! is_array($values)) {
            throw new ToolFailure('`values` is an object of field → value.');
        }

        Arguments::refuseUnknown($values, ['titles', 'descriptions', 'price', 'point_kind', 'countries', 'honours_paid_by_store', 'enabled', 'localities'], 'delivery_methods_save');

        $key = $arguments['key'] ?? null;
        $form = $this->form();

        if (is_string($key) && $key !== '') {
            $method = DeliveryMethod::query()->where('key', $key)->first()
                ?? throw new ToolFailure("Method {$key} does not exist. delivery_methods_list has them all.");
            $current = $form->describe($method)['values'];
        } else {
            $type = (string) ($arguments['type'] ?? '');
            $types = $this->container->make(MethodTypes::class);

            if (! $types->has($type)) {
                throw new ToolFailure(sprintf('Unknown method type "%s". Known: %s.', $type, implode(', ', $types->keys())));
            }

            $method = new DeliveryMethod(['type' => $type]);
            $current = ['titles' => [], 'descriptions' => [], 'enabled' => false];
        }

        foreach (['titles', 'descriptions'] as $field) {
            if (array_key_exists($field, $values)) {
                // One language changed is one language: the others keep their words.
                $values[$field] = [...(array) ($current[$field] ?? []), ...$this->texts($values[$field], $field)];
            }
        }

        if (array_key_exists('localities', $values)) {
            if (! is_array($values['localities'])) {
                throw new ToolFailure('`localities` is a list of locality ids or names.');
            }

            $values['localities'] = array_values(array_unique(array_map(fn (mixed $one): int => $this->locality($one)->id, $values['localities'])));
        }

        $input = [...$current, ...$values];
        $created = ! $method->exists;
        $work = function () use ($method, $input, $user, $form): array {
            $saved = $form->save($method, $input, $this->can($user));

            return $this->method($saved->refresh()->load('localities:id'), $form, $this->locale());
        };

        if ($this->dryRun($arguments)) {
            $would = $this->rehearse($work);

            if ($created) {
                $would['id'] = null;
            }

            return ['dry_run' => true, 'would' => $created ? 'create' : 'update', 'method' => $would];
        }

        return [($created ? 'created' : 'updated') => true, 'method' => $work()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['methods'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`methods` is the list of method keys in their new order.');
        }

        $all = DeliveryMethod::query()->orderBy('position')->orderBy('id')->pluck('id', 'key')->map(static fn (mixed $id): int => (int) $id)->all();
        $keys = array_values(array_unique(array_map('strval', $given)));
        $unknown = array_values(array_diff($keys, array_keys($all)));

        if ($unknown !== []) {
            throw new ToolFailure('No such method: '.implode(', ', $unknown).'. delivery_methods_list has them all.');
        }

        $order = [...$keys, ...array_values(array_diff(array_keys($all), $keys))];

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'before' => array_keys($all), 'after' => $order];
        }

        DB::transaction(static function () use ($order, $all): void {
            foreach ($order as $position => $key) {
                DeliveryMethod::query()->whereKey($all[$key])->update(['position' => $position]);
            }
        });

        // A mass update fires no model events: the checkout's list is cached (§17).
        $this->container->make(CacheGroups::class)->bump(Methods::GROUP);

        return ['reordered' => true, 'order' => $order];
    }

    // The quote

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function quote(array $arguments): array
    {
        $locality = $this->locality($arguments['locality'] ?? null);
        $locale = $this->locale();
        $items = $arguments['items'] ?? null;
        $cartId = $arguments['cart_id'] ?? null;

        if ($items !== null && $cartId !== null) {
            throw new ToolFailure('Give `cart_id` or `items`, not both.');
        }

        if ($items !== null && (! is_array($items) || ! array_is_list($items) || $items === [])) {
            throw new ToolFailure('`items` is a list of lines: [{ "type": "catalog.product", "ref": 12, "qty": 1 }].');
        }

        // Lines are put in a cart and counted inside a transaction rolled back: nothing is kept,
        // and a satellite's quote is asked exactly as the checkout would ask it.
        return $this->rehearse(function () use ($locality, $locale, $items, $cartId, $arguments): array {
            $calculator = $this->container->make(Calculator::class);

            if (is_array($items)) {
                $cart = $this->container->make(CartService::class)->add(null, $items, $locale)->cart;
                $state = $calculator->calculate($cart, $locale, Purpose::Checkout);
            } elseif ($cartId !== null) {
                $cart = Cart::query()->find($this->int($cartId, 'cart_id', 1))
                    ?? throw new ToolFailure("Cart {$cartId} does not exist. cart_list has the carts.");
                $state = $calculator->calculate($cart, $locale, Purpose::Checkout);
            } else {
                $state = $calculator->empty($locale);
            }

            if (($arguments['paid_by_store'] ?? false) === true && ! $state->hasBenefit(Benefits::PAID_BY_STORE)) {
                $state = $this->withBenefit($state);
            }

            $delivery = $this->container->make(Delivery::class);
            $context = $delivery->context($state, $locality, Purpose::Checkout, $locale);
            $options = $this->container->make(Methods::class)->evaluate($context);

            return [
                'locality' => LocalityRows::brief($locality, $locale),
                'needed' => Delivery::needed($state),
                'needed_note' => Delivery::needed($state) ? null : 'No line of the cart travels: the checkout skips the delivery step.',
                'paid_by_store' => $state->hasBenefit(Benefits::PAID_BY_STORE),
                'currency' => $state->currency,
                'methods' => array_map(fn (MethodOption $option): array => $this->option($option, $locale), $options),
            ];
        });
    }

    /** @return array<string, mixed> */
    private function option(MethodOption $option, string $locale): array
    {
        $reason = $option->reason();
        $offered = $option->offered() && ! $option->disabled;

        return [
            'key' => $option->method->key,
            'type' => $option->method->type,
            'title' => $option->method->title($locale),
            'offered' => $offered,
            'reason' => $offered ? null : $reason,
            'explanation' => $offered ? null : (self::REASONS[$reason ?? ''] ?? ($option->unavailable === MethodOption::NO_QUOTE
                ? 'The carrier did not price it: '.($reason ?? 'no reason given').'.'
                : $reason)),
            'hint' => $option->hint,
            'points' => $option->points,
            'quote' => $option->quote?->toArray(),
        ];
    }

    /** The counted cart with the privilege, as a rule of the cart would have given it. */
    private function withBenefit(CartState $state): CartState
    {
        return new CartState(
            $state->currency,
            $state->locale,
            $state->lines,
            $state->lineAdjustments,
            $state->subtotal,
            $state->adjustments,
            $state->total,
            $state->tenders,
            $state->payable,
            [...$state->benefits, new Benefit(Benefits::PAID_BY_STORE, 'Delivery paid by the store', 'mcp:delivery_quote')],
            $state->hints,
            $state->warnings,
            $this->container->make(Money::class),
        );
    }

    // Points

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function points(array $arguments): array
    {
        $locale = $this->locale();
        $kinds = $this->container->make(PointKinds::class)->all($locale);
        $query = DeliveryPoint::query()->with('locality.region');

        if (isset($arguments['locality'])) {
            $query->where('locality_id', $this->localityId($arguments['locality']));
        }

        if (is_string($arguments['carrier'] ?? null) && $arguments['carrier'] !== '') {
            $query->where('carrier', $arguments['carrier']);
        }

        if (is_string($arguments['kind'] ?? null) && $arguments['kind'] !== '') {
            $query->where('kind', $arguments['kind']);
        }

        match ((string) ($arguments['state'] ?? 'active')) {
            'active' => $query->where('active', true)->whereNull('removed_at'),
            'inactive' => $query->where('active', false)->whereNull('removed_at'),
            'removed' => $query->whereNotNull('removed_at'),
            'all' => null,
            default => throw new ToolFailure('`state` is active, inactive, removed or all.'),
        };

        $key = Normalizer::key((string) ($arguments['q'] ?? ''));

        if ($key !== '') {
            $query->where('search', 'like', '%'.addcslashes($key, '%_\\').'%');
        }

        $page = $query->orderBy('id')->paginate(25, ['*'], 'page', $this->int($arguments['page'] ?? 1, 'page', 1));

        return [
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'total' => $page->total(),
            'points' => $page->getCollection()->map(static fn (DeliveryPoint $point): array => [
                ...PointForm::row($point, $locale, $kinds),
                'carrier' => $point->carrier,
                'number' => $point->number,
                'external_id' => $point->external_id,
                'lat' => $point->lat,
                'lng' => $point->lng,
                'removed_at' => $point->removed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function savePoint(array $arguments, ?Authenticatable $user): array
    {
        $values = $arguments['values'] ?? null;

        if (! is_array($values)) {
            throw new ToolFailure('`values` is an object of field → value.');
        }

        Arguments::refuseUnknown($values, ['kind', 'titles', 'address', 'locality', 'lat', 'lng', 'phone', 'active', 'schedule'], 'delivery_points_save');

        $form = $this->container->make(PointForm::class);
        $point = isset($arguments['point']) ? $this->own($arguments['point']) : new DeliveryPoint;
        $current = $point->exists ? $form->describe($point)['values'] : ['kind' => 'store', 'active' => true];

        foreach (['titles', 'address'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = [...(array) ($current[$field] ?? []), ...$this->texts($values[$field], $field)];
            }
        }

        if (array_key_exists('locality', $values)) {
            $values['locality_id'] = $this->locality($values['locality'])->id;
            unset($values['locality']);
        }

        $input = [...$current, ...$values];
        $created = ! $point->exists;
        // The screen's own check of the fields behind a permission, as the panel's save makes it.
        $can = $this->can($user);
        $work = static fn (): array => $form->describe($form->save($point, $input, $can)->refresh());

        if ($this->dryRun($arguments)) {
            $would = $this->rehearse($work);

            if ($created) {
                $would['point']['id'] = null;
            }

            return ['dry_run' => true, 'would' => $created ? 'create' : 'update', ...$would];
        }

        return [($created ? 'created' : 'updated') => true, ...$work()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deactivate(array $arguments): array
    {
        $point = $this->own($arguments['point'] ?? null);
        $held = DeliverySelection::query()->where('point_id', $point->id)->count();

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'point' => $point->id, 'active' => $point->active, 'carts_holding' => $held];
        }

        if ($point->active) {
            $point->active = false;
            $point->save();
        }

        return ['deactivated' => true, 'point' => $point->id, 'carts_holding' => $held];
    }

    /** A point of the shop's own by its id: a carrier's point is the sync's, not the agent's. */
    private function own(mixed $id): DeliveryPoint
    {
        $point = DeliveryPoint::query()->find($this->int($id, 'point', 1))
            ?? throw new ToolFailure("Point {$id} does not exist. delivery_points_search finds points.");

        if ($point->carrier !== Carriers::STORE) {
            throw new ToolFailure("Point {$point->id} is {$point->carrier}'s: the sync of the carrier keeps it, not the panel.");
        }

        return $point;
    }

    private function form(): MethodForm
    {
        return $this->container->make(MethodForm::class);
    }
}
