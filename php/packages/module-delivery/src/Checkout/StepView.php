<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Checkout;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\CartDelivery;
use WebxUi\Delivery\Countries;
use WebxUi\Delivery\Delivery;
use WebxUi\Delivery\MethodOption;
use WebxUi\Delivery\Points;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Delivery\Quote;
use WebxUi\Delivery\QuoteKind;
use WebxUi\Delivery\Streets;
use WebxUi\Geography\LocalitySearch;
use WebxUi\Geography\LocalitySuggestion;

/**
 * What the components of the step print (§12.3), worked out once: the countries, the chosen
 * locality and the popular towns, the methods of the locality with their prices, and under the
 * chosen one its first page of points or the address with its streets. The step and the API's
 * redraw build it the same way, so a page and a step redrawn by the script are one markup.
 *
 * Reads the site's own tables only (decision 38): the methods and countries from the cache group
 * `delivery`, the first page of points from `delivery-points`.
 */
final class StepView
{
    public const string ID = 'webx-delivery';

    /** The streets a strict street field holds without JavaScript: more is the server's search. */
    public const int STREETS_LOADED = 50;

    public function __construct(
        private readonly Countries $countries,
        private readonly CartDelivery $carts,
        private readonly Delivery $delivery,
        private readonly Points $points,
        private readonly Streets $streets,
        private readonly PopularLocalities $popular,
        private readonly LocalitySearch $search,
        private readonly Carriers $carriers,
        private readonly Money $money,
        private readonly UrlGenerator $url,
    ) {}

    /**
     * @param  MessageBag|ViewErrorBag|array<string, string>|null  $errors  the refusals by field: a form without JavaScript gets them from the session
     * @param  string|null  $name  the prefix of the field names, when the step stands inside a form of its owner
     * @return array<string, mixed>
     */
    public function build(CartState $state, ?int $cart, string $locale, MessageBag|ViewErrorBag|array|null $errors = null, ?string $name = null): array
    {
        $selection = $cart !== null ? $this->carts->selection($cart) : null;
        $countries = $this->countries->active($locale);
        $country = $selection->country_code ?? ($countries[0]['code'] ?? null);
        $locality = $selection?->locality_id !== null ? $this->carts->locality($selection->locality_id) : null;
        $field = static fn (string $key): string => $name === null ? self::bracket($key) : $name.'['.str_replace('.', '][', $key).']';
        $old = static fn (string $key, mixed $default): mixed => request()->hasSession()
            ? request()->old($name === null ? $key : "{$name}.{$key}", $default)
            : $default;

        $methods = [];
        $chosen = null;
        $popular = $country !== null ? $this->popular->suggestions($country, $locale) : [];

        if ($locality !== null && $cart !== null) {
            $context = $this->delivery->context($state, $locality, Purpose::Checkout, $locale);

            foreach ($this->delivery->methods($context) as $option) {
                $row = $this->method($option, $state, $locale);
                $row['chosen'] = $selection?->method_id === $option->method->id && ! $option->disabled;
                $methods[] = $row;

                if ($row['chosen']) {
                    $chosen = ['row' => $row, 'option' => $option];
                }
            }
        }

        $step = [
            'id' => self::ID,
            'name' => $name,
            'field' => $field,
            'locale' => $locale,
            'urls' => [
                'selection' => $this->url->route('webx-delivery.selection.update'),
                'localities' => $country !== null ? $this->url->route('webx-delivery.localities', ['country' => $country]) : null,
                'popular' => $country !== null ? $this->url->route('webx-delivery.localities.popular', ['country' => $country]) : null,
            ],
            'countries' => $countries,
            'country' => $country,
            'showCountry' => count($countries) > 1,
            'locality' => $locality === null ? null : [
                'id' => $locality->id,
                'name' => $locality->name($locale),
                'label' => $locality->label($locale),
            ],
            'localityValue' => $old('locality_name', $locality?->name($locale) ?? ''),
            'popular' => array_map(static fn (LocalitySuggestion $s): array => $s->toArray(), $popular),
            // Without JavaScript: the popular towns, else the heaviest — a list to start typing from.
            'datalist' => array_map(
                static fn (LocalitySuggestion $s): array => $s->toArray(),
                $popular !== [] || $country === null ? $popular : $this->search->suggest($country, '', $locale, 10),
            ),
            'methods' => $methods,
            'chosen' => $chosen['row'] ?? null,
            'points' => null,
            'point' => null,
            'address' => null,
            'cost' => null,
            'errors' => self::errors($errors),
        ];

        if ($chosen !== null && $locality !== null) {
            /** @var MethodOption $option */
            $option = $chosen['option'];
            $asks = $option->type?->asks();
            $carrier = $option->method->carrier();

            if ($asks === Asks::Point) {
                $kind = $option->method->pointKind ?? ($option->type?->pointKinds()[0] ?? '');
                $page = $this->points->page($carrier, $kind, $locality->id, '', 1, $locale);
                $pointId = $old('point', $selection?->point_id);
                $step['points'] = [
                    'source' => $this->url->route('webx-delivery.points', ['method' => $option->method->key, 'locality' => $locality->id]),
                    'options' => array_map(self::pointOption(...), $page['points']),
                    'more' => $page['more'],
                    'total' => $page['total'],
                    'value' => $pointId === null ? '' : (string) $pointId,
                ];
                $step['point'] = $selection?->point_id !== null ? $this->points->card($selection->point_id, $locale) : null;

                if ($step['point'] !== null && $step['point']['locality_id'] !== $locality->id) {
                    $step['point'] = null;
                }

                // The chosen point may be on another page than the first: the list names it all the same.
                if ($step['point'] !== null && ! in_array((string) $step['point']['id'], array_column($step['points']['options'], 'value'), true)) {
                    array_unshift($step['points']['options'], self::pointOption($step['point']));
                }
            }

            if ($asks === Asks::Address) {
                $stored = $selection->address ?? [];
                $strict = $carrier !== Carriers::STORE;
                $streets = $strict
                    ? $this->streets->forCarrier($carrier, $locality->id, '', $locale, self::STREETS_LOADED)
                    : [];
                $step['address'] = [
                    'strict' => $strict,
                    'source' => $this->url->route('webx-delivery.streets', ['method' => $option->method->key, 'locality' => $locality->id]),
                    'streets' => array_map(static fn ($street): array => ['value' => $street->streetId ?? $street->name, 'label' => $street->name], $streets),
                    'street' => (string) $old('address.street', $stored['street'] ?? ''),
                    'street_id' => (string) $old('address.street_id', $stored['street_id'] ?? ''),
                    'house' => (string) $old('address.house', $stored['house'] ?? ''),
                    'apartment' => (string) $old('address.apartment', $stored['apartment'] ?? ''),
                    'note' => (string) $old('address.note', $stored['note'] ?? ''),
                ];

                // A street beyond the first ones loaded: the list names it all the same.
                $id = $step['address']['street_id'];
                if ($strict && $id !== '' && ! in_array($id, array_column($step['address']['streets'], 'value'), true)) {
                    array_unshift($step['address']['streets'], ['value' => $id, 'label' => $step['address']['street']]);
                }
            }

            $step['cost'] = $this->delivery->cost($state, (int) $cart, Purpose::Checkout);
        }

        return $step;
    }

    /**
     * A method as its row prints it: the title, the description, the price in words.
     *
     * @return array<string, mixed>
     */
    public function method(MethodOption $option, CartState $state, string $locale): array
    {
        $carrier = $option->method->carrier();

        return [
            'id' => $option->method->id,
            'key' => $option->method->key,
            'title' => $option->method->title($locale),
            'description' => $option->method->description($locale),
            'icon' => $carrier !== Carriers::STORE && $this->carriers->has($carrier) ? $this->carriers->get($carrier)->icon() : null,
            'asks' => $option->type?->asks()->value,
            'carrier' => $carrier,
            'points' => $option->points,
            'disabled' => $option->disabled,
            'hint' => $option->hint,
            'price' => $option->quote === null ? null : $this->price($option->quote, $state, $locale),
        ];
    }

    /**
     * The price in words: an amount, «Free» with the old one crossed out, «By carrier rates» with
     * an estimate.
     *
     * @return array{kind: string, text: string, was: string|null, estimate: string|null}
     */
    public function price(Quote $quote, CartState $state, string $locale): array
    {
        $format = fn (?int $minor): ?string => $minor === null
            ? null
            : ($state->currency === null ? (string) $minor : $this->money->format($minor, $state->currency));
        $word = static fn (string $key): string => (string) trans("webx-delivery::delivery.step.{$key}", [], $locale);

        return [
            'kind' => $quote->kind->value,
            'text' => match ($quote->kind) {
                QuoteKind::Fixed => $quote->amount === 0 || $quote->amount === null ? $word('free') : (string) $format($quote->amount),
                QuoteKind::CarrierRates => $word('carrier_rates'),
                default => $word('free'),
            },
            'was' => $format($quote->was),
            'estimate' => $quote->kind === QuoteKind::CarrierRates ? $format($quote->estimate) : null,
        ];
    }

    /**
     * A point as an option of the list: the title, and under it its line (the address), today's hours and who
     * alone may use it, if not everybody.
     *
     * @param  array<string, mixed>  $point  a row of `Points::page()`
     * @return array{value: string, label: string, hint: string|null}
     */
    public static function pointOption(array $point): array
    {
        // `line` — the address, or less when the carrier's title already says it (NamesPointLines).
        $where = array_key_exists('line', $point) ? $point['line'] : ($point['address'] ?? null);
        $hint = implode(' · ', array_filter([$where, $point['today'] ?? null, $point['restricted'] ?? null], static fn (mixed $part): bool => is_string($part) && $part !== ''));

        return [
            'value' => (string) $point['id'],
            'label' => (string) $point['title'],
            'hint' => $hint === '' ? null : $hint,
        ];
    }

    /** `address.street` → `address[street]`. */
    private static function bracket(string $key): string
    {
        $parts = explode('.', $key);

        return array_shift($parts).implode('', array_map(static fn (string $part): string => "[{$part}]", $parts));
    }

    /**
     * @param  MessageBag|ViewErrorBag|array<string, string>|null  $errors
     * @return array<string, string>
     */
    private static function errors(MessageBag|ViewErrorBag|array|null $errors): array
    {
        if (is_array($errors)) {
            return $errors;
        }

        $bag = $errors instanceof ViewErrorBag ? $errors->getBag('default') : $errors;
        $found = [];

        foreach ($bag?->toArray() ?? [] as $key => $messages) {
            $found[(string) $key] = is_array($messages) ? (string) ($messages[0] ?? '') : (string) $messages;
        }

        return $found;
    }
}
