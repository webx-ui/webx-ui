<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Cart\Totals\CartState;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Geography\LocalitySearch;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Normalizer;

/**
 * The buyer's choice of a cart, written (§12.2, `PUT /api/delivery/selection`): the country, the
 * locality, the method, and the point or the address — each part only when the request names it,
 * each checked against the site's own tables before anything is written. A refusal writes
 * nothing and says why by field.
 *
 * - A new locality drops the point and the address and keeps the method only when the locality
 *   offers it too (decision 40: the methods and points are counted again).
 * - A new method drops the point; the house and the apartment stay — the same door for another
 *   courier — but a street of one carrier means nothing to another and is checked again.
 * - A carrier's courier without streets in the locality is refused with its hint (§8.2).
 */
final class Selections
{
    public function __construct(
        private readonly Delivery $delivery,
        private readonly Methods $methods,
        private readonly Countries $countries,
        private readonly LocalitySearch $search,
        private readonly CartDelivery $carts,
    ) {}

    /**
     * @param  array<string, mixed>  $input  `country`, `locality` (an id) or `locality_name` (typed
     *                                       without JavaScript), `method` (an id or a key), `point`, `address`
     */
    public function put(int $cart, CartState $state, array $input, string $locale): SelectionResult
    {
        $current = $this->carts->selection($cart);
        $errors = [];
        $word = static fn (string $key): string => (string) trans("webx-delivery::delivery.errors.{$key}", [], $locale);

        // The country: the one asked, else the chosen one, else the only one there is.
        $country = $this->text($input['country'] ?? null);
        $country = $country !== null ? strtoupper($country) : ($current->country_code ?? $this->countries->only());

        if ($country === null || ! $this->countries->has($country)) {
            return SelectionResult::refused(['country' => $word('choose_country')]);
        }

        // The locality: by its id, by a name typed without JavaScript, else the chosen one of this country.
        $locality = null;
        $localityAsked = array_key_exists('locality', $input) || $this->text($input['locality_name'] ?? null) !== null;

        if (is_numeric($input['locality'] ?? null)) {
            $locality = GeoLocality::query()->with('region')->whereKey((int) $input['locality'])
                ->where('country_code', $country)->where('active', true)->first();
        } elseif (($name = $this->text($input['locality_name'] ?? null)) !== null) {
            $locality = $this->byName($country, $name, $locale);
        } elseif (! $localityAsked && $current?->locality_id !== null && $current->country_code === $country) {
            $locality = $this->carts->locality($current->locality_id);
        }

        if ($localityAsked && $locality === null) {
            return SelectionResult::refused(['locality' => $word('choose_locality')]);
        }

        $sameLocality = $locality !== null && $current?->locality_id === $locality->id;
        // A form without JavaScript sends the radios and the list of the town it showed along
        // with a new town: they belong to the old one, and are dropped instead of refused.
        $moved = $locality !== null && ! $sameLocality && $current?->locality_id !== null;
        $values = [
            'country_code' => $country,
            'locality_id' => $locality?->id,
            'method_id' => $sameLocality ? $current->method_id : null,
            'point_id' => $sameLocality ? $current->point_id : null,
            'address' => $sameLocality ? $current->address : null,
        ];

        if ($locality === null) {
            return $this->write($cart, $current, ['method_id' => null, 'point_id' => null, 'address' => null] + $values, null);
        }

        $context = $this->delivery->context($state, $locality, locale: $locale);

        // The method: the one asked, else the chosen one when the new locality offers it as well.
        $methodAsked = array_key_exists('method', $input);
        $methodId = $methodAsked ? $this->methodId($input['method']) : ($current?->method_id);
        $option = null;

        if ($methodId !== null) {
            $option = $this->methods->option($context, $methodId);
            $usable = $option !== null && $option->offered() && ! $option->disabled;

            if (! $usable && $methodAsked && ! $moved) {
                $errors['method'] = $option?->disabled === true && $option->hint !== null ? $option->hint : $word('method_gone');
            }

            if (! $usable) {
                $option = null;
            }
        } elseif ($methodAsked && ! $moved && $this->text($input['method']) !== null) {
            $errors['method'] = $word('method_gone');
        }

        // A point of another method is not this one's: the list of the old one may come along.
        $sameMethod = $option !== null && $option->method->id === $values['method_id'];

        if (! $sameMethod) {
            $values['point_id'] = null;
        }

        $values['method_id'] = $option?->method->id;

        // What the method asks for: checked by its type, only when the request names it.
        if ($option !== null && $option->type !== null && $errors === [] && ! $moved) {
            $asks = $option->type->asks();
            $given = SelectionInput::fromArray($input);

            if ($asks === Asks::Point && array_key_exists('point', $input) && ($sameMethod || ! $this->sentAlong($input))) {
                $checked = $given->point === null
                    ? null
                    : $option->type->validate($option->method, $context, $given);

                if (is_array($checked)) {
                    $errors += $this->fields($checked);
                } else {
                    $values['point_id'] = $checked?->point?->id;
                }
            }

            if ($asks === Asks::Address && is_array($input['address'] ?? null) && ($sameMethod || ! $this->sentAlong($input))) {
                $checked = $option->type->validate($option->method, $context, $given);

                if (is_array($checked)) {
                    $errors += $this->fields($checked);
                } elseif ($checked->address !== null) {
                    $values['address'] = $checked->address->toArray();
                }
            }

            if ($asks !== Asks::Address && $option->method->id !== $current?->method_id) {
                $values['address'] = $current?->address === null ? null : $this->door($current->address);
            }
        }

        if ($errors !== []) {
            return SelectionResult::refused($errors);
        }

        return $this->write($cart, $current, $values, $locality);
    }

    /** @param  array<string, mixed>  $values */
    private function write(int $cart, ?DeliverySelection $current, array $values, ?GeoLocality $locality): SelectionResult
    {
        $selection = $current ?? new DeliverySelection(['cart_id' => $cart]);
        $selection->fill($values);
        $selection->cart_id = $cart;

        if (! $selection->exists || $selection->isDirty()) {
            $selection->save();
        }

        $this->carts->remember($cart, $selection, $locality);

        return SelectionResult::written($selection);
    }

    /**
     * Keys of a type's check as the fields of the request: the parts of an address under `address.`.
     *
     * @param  array<string, string>  $errors
     * @return array<string, string>
     */
    private function fields(array $errors): array
    {
        $fields = [];

        foreach ($errors as $key => $message) {
            $fields[in_array($key, ['point', 'method', 'country', 'locality'], true) ? $key : "address.{$key}"] = $message;
        }

        return $fields;
    }

    /**
     * The house and the apartment kept for another courier; the street — its own carrier's — not.
     *
     * @param  array<string, string|null>  $address
     * @return array<string, string|null>|null
     */
    private function door(array $address): ?array
    {
        return array_filter(['house' => $address['house'] ?? null, 'apartment' => $address['apartment'] ?? null], static fn (?string $v): bool => $v !== null) ?: null;
    }

    /** A typed name: the suggestion whose name is just that, or the only one there is. */
    private function byName(string $country, string $name, string $locale): ?GeoLocality
    {
        $suggestions = $this->search->suggest($country, $name, $locale, 5);
        $key = Normalizer::key($name);
        $id = null;

        foreach ($suggestions as $suggestion) {
            if (Normalizer::key($suggestion->name) === $key || Normalizer::key($suggestion->label) === $key) {
                $id = $suggestion->id;

                break;
            }
        }

        $id ??= count($suggestions) === 1 ? $suggestions[0]->id : null;

        return $id === null ? null : $this->carts->locality($id);
    }

    /**
     * Whether the point came with a form that showed another method: the form without JavaScript
     * sends every field it printed, and it printed the list of the method chosen before.
     *
     * @param  array<string, mixed>  $input
     */
    private function sentAlong(array $input): bool
    {
        return ($input['_form'] ?? null) === 'step';
    }

    private function methodId(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $key = $this->text($value);

        if ($key === null) {
            return null;
        }

        foreach ($this->methods->catalogue()['methods'] as $row) {
            if (($row['key'] ?? null) === $key) {
                return (int) $row['id'];
            }
        }

        return 0;
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
