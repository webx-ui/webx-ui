<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Money;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Countries;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\PointKinds;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Geography\Names;
use WebxUi\Localization\Locales;

/**
 * The screen `delivery.method-form` on the server (§14.2): what a method is called, costs and
 * where it goes. Which of the fields a method has depends on its type — the kind of points for a
 * method that asks for a point, the list of localities for the shop's courier — so the answer
 * names the fields the form leaves out, and a save ignores them.
 *
 * The price is kept in minor units (§9.1) and edited in the currency's units: the form is read by
 * a person, «7.50», and not by the table.
 */
final class MethodForm
{
    public const string SCREEN = 'delivery.method-form';

    /** @var list<string> */
    private const array OWN = ['titles', 'descriptions', 'price', 'point_kind', 'countries', 'honours_paid_by_store', 'enabled', 'localities'];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly MethodTypes $types,
        private readonly Carriers $carriers,
        private readonly PointKinds $kinds,
        private readonly Countries $countries,
        private readonly Locales $locales,
        private readonly Money $money,
        private readonly CurrencySource $currencies,
    ) {}

    /**
     * The types a new method can be of: every registered one, each with the carrier it is of.
     *
     * @return list<array{key: string, title: string, carrier: string, carrier_title: string, asks: string}>
     */
    public function types(): array
    {
        $locale = $this->locales->current();
        $types = [];

        foreach ($this->types->all() as $type) {
            $types[] = [
                'key' => $type->key(),
                'title' => $this->typeTitle($type->key(), $locale),
                'carrier' => $type->carrier(),
                'carrier_title' => $this->carrierTitle($type->carrier(), $locale),
                'asks' => $type->asks()->value,
            ];
        }

        return $types;
    }

    /** @return array<string, mixed> a line of the list */
    public function row(DeliveryMethod $method, string $locale): array
    {
        $known = $this->types->has($method->type);

        return [
            'id' => $method->id,
            'key' => $method->key,
            'type' => $method->type,
            'type_title' => $this->typeTitle($method->type, $locale),
            'carrier' => $known ? $this->types->get($method->type)->carrier() : null,
            'carrier_title' => $known ? $this->carrierTitle($this->types->get($method->type)->carrier(), $locale) : null,
            'missing' => ! $known,
            'title' => Names::pick($method->titles, $locale) ?? $method->key,
            'price' => $method->price,
            'price_text' => $this->priceText($method->price),
            'countries' => $method->countries ?? [],
            'enabled' => $method->enabled,
            'position' => $method->position,
        ];
    }

    /** @return array{method: array<string, mixed>, values: array<string, mixed>, hidden: list<string>, point_kinds: list<array{value: string, label: string}>} */
    public function describe(DeliveryMethod $method): array
    {
        $method->loadMissing('localities');
        $locale = $this->locales->current();

        return [
            'method' => $this->row($method, $locale),
            'values' => [
                'titles' => $method->titles,
                'descriptions' => $method->descriptions ?? [],
                'price' => $method->price === null ? null : $this->major($method->price),
                'point_kind' => $method->point_kind,
                'countries' => $method->countries ?? [],
                'honours_paid_by_store' => $method->honours_paid_by_store,
                'enabled' => $method->enabled,
                'localities' => $method->localities->pluck('id')->map(intval(...))->values()->all(),
            ],
            'hidden' => $this->hidden($method->type),
            'point_kinds' => $this->pointKindOptions($method->type, $locale),
        ];
    }

    /**
     * The countries a method can be limited to: those the site delivers to.
     *
     * @return list<array{value: string, label: string}>
     */
    public function countryOptions(): array
    {
        return array_map(
            static fn (array $country): array => ['value' => $country['code'], 'label' => $country['name']],
            $this->countries->active($this->locales->current()),
        );
    }

    /**
     * The kinds a method's points may be, for its type: a carrier's own kinds, or for the shop's
     * pick-up its built-in two and the kinds it named itself (decision 9).
     *
     * @return list<array{value: string, label: string}>
     */
    public function pointKindOptions(?string $type = null, ?string $locale = null): array
    {
        $all = $this->kinds->all($locale);
        $kinds = $type !== null && $this->types->has($type) ? $this->types->get($type)->pointKinds() : array_keys($all);

        if ($type === null || $type === StorePickup::KEY) {
            $kinds = [...$kinds, ...array_keys($this->kinds->own())];
        }

        return array_map(
            static fn (string $kind): array => ['value' => $kind, 'label' => $all[$kind] ?? $kind],
            array_values(array_unique($kinds)),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     */
    public function save(DeliveryMethod $method, array $input, ?callable $can = null): DeliveryMethod
    {
        foreach ($this->hidden($method->type) as $field) {
            unset($input[$field]);
        }

        $values = $this->record->split(self::SCREEN, $input, self::OWN, can: $can)->own;

        if (array_key_exists('titles', $values) && Names::clean(is_array($values['titles']) ? $values['titles'] : []) === []) {
            throw ValidationException::withMessages(['titles' => [__('webx-delivery::panel.errors.title_required')]]);
        }

        if (array_key_exists('point_kind', $values) && $values['point_kind'] !== null
            && ! in_array($values['point_kind'], array_column($this->pointKindOptions($method->type), 'value'), true)) {
            throw ValidationException::withMessages(['point_kind' => [__('validation.in', ['attribute' => 'point_kind'])]]);
        }

        if (! $method->exists && $this->asks($method->type) === Asks::Point && ($values['point_kind'] ?? null) === null) {
            $values['point_kind'] = $this->pointKindOptions($method->type)[0]['value'] ?? null;
        }

        $localities = $values['localities'] ?? null;
        unset($values['localities']);

        foreach ($values as $field => $value) {
            $method->setAttribute($field, match ($field) {
                'titles' => Names::clean(is_array($value) ? $value : []),
                'descriptions' => Names::clean(is_array($value) ? $value : []) ?: null,
                'price' => $value === null ? null : $this->minor($value),
                'countries' => is_array($value) && $value !== [] ? array_values($value) : null,
                'honours_paid_by_store', 'enabled' => (bool) $value,
                default => $value,
            });
        }

        return DB::transaction(function () use ($method, $localities): DeliveryMethod {
            if (! $method->exists) {
                $method->key = $this->key($method->type);
                $method->position = (int) DeliveryMethod::query()->max('position') + 1;
            }

            $method->save();

            if (is_array($localities)) {
                $method->syncLocalities($localities);
            }

            return $method;
        });
    }

    /**
     * The form's fields this type has no use for.
     *
     * @return list<string>
     */
    public function hidden(string $type): array
    {
        $hidden = [];

        if ($this->asks($type) !== Asks::Point) {
            $hidden[] = 'point_kind';
        }

        if ($type !== StoreCourier::KEY) {
            $hidden[] = 'localities';
        }

        return $hidden;
    }

    public function typeTitle(string $type, string $locale): string
    {
        $key = 'webx-delivery::panel.types.'.str_replace('.', '-', $type);
        $line = trans($key, [], $locale);

        return is_string($line) && $line !== $key ? $line : $type;
    }

    private function carrierTitle(string $carrier, string $locale): string
    {
        return $this->carriers->has($carrier) ? $this->carriers->get($carrier)->title($locale) : $carrier;
    }

    private function asks(string $type): ?Asks
    {
        return $this->types->has($type) ? $this->types->get($type)->asks() : null;
    }

    /** `store-pickup`, then `store-pickup-2`: the key the API and the snapshot know a method by. */
    private function key(string $type): string
    {
        $base = substr(str_replace('.', '-', $type), 0, 56);
        $taken = DeliveryMethod::query()->where('key', 'like', $base.'%')->pluck('key')->all();
        $key = $base;

        for ($n = 2; in_array($key, $taken, true); $n++) {
            $key = $base.'-'.$n;
        }

        return $key;
    }

    private function currency(): string
    {
        return $this->currencies->currency() ?? 'EUR';
    }

    private function priceText(?int $minor): ?string
    {
        return $minor === null ? null : $this->money->format($minor, $this->currency());
    }

    private function major(int $minor): float
    {
        return $minor / 10 ** $this->money->exponent($this->currency());
    }

    private function minor(mixed $major): int
    {
        return (int) round((float) $major * 10 ** $this->money->exponent($this->currency()));
    }
}
