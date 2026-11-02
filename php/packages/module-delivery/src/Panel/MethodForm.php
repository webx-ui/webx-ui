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
use WebxUi\Delivery\Contracts\LabelledMethodType;
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
     * @return list<array{key: string, title: string, description: string, carrier: string, carrier_title: string, asks: string}>
     */
    public function types(): array
    {
        $locale = $this->locales->current();
        $types = [];

        foreach ($this->types->all() as $type) {
            $types[] = [
                'key' => $type->key(),
                'title' => $this->typeTitle($type->key(), $locale),
                'description' => $this->typeDescription($type->key(), $locale),
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
        $carrier = $known ? $this->types->get($method->type)->carrier() : null;

        return [
            'id' => $method->id,
            'key' => $method->key,
            'type' => $method->type,
            'type_title' => $this->typeTitle($method->type, $locale),
            'carrier' => $carrier,
            'carrier_title' => $carrier !== null ? $this->carrierTitle($carrier, $locale) : null,
            'missing' => ! $known,
            'title' => Names::pick($method->titles, $locale) ?? $method->key,
            'price' => $method->price,
            // No price of its own: the shop's method is free, a carrier's is priced by its type —
            // the list says what the checkout will (§9.1), not «Free» for both.
            'price_text' => $method->price === null && $carrier !== null && $carrier !== Carriers::STORE
                ? (string) __('webx-delivery::delivery.step.carrier_rates', [], $locale)
                : $this->priceText($method->price),
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

        // A carrier's type with one kind keeps that kind whatever was stored before: the form no
        // longer asks, and a kind of the shop's own on a carrier's method finds no point at all.
        if ($this->asks($method->type) === Asks::Point
            && (! $this->choosesKind($method->type) || (! $method->exists && ($values['point_kind'] ?? null) === null))) {
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

        if (! $this->choosesKind($type)) {
            $hidden[] = 'point_kind';
        }

        if ($type !== StoreCourier::KEY) {
            $hidden[] = 'localities';
        }

        return $hidden;
    }

    /**
     * The type in words, never its key (`carrier-a.branch`): what the type says of itself, the
     * dictionary's line for the shop's two, or a name made of the kind after the dot or of what
     * the type asks, with the carrier's title in it — «Carrier A branch», «Delivery by Carrier A».
     * The key itself only for a type that is not installed any more, and so has no carrier to name.
     */
    public function typeTitle(string $type, string $locale): string
    {
        $known = $this->types->has($type) ? $this->types->get($type) : null;

        if ($known instanceof LabelledMethodType) {
            return $known->title($locale);
        }

        $line = $this->line('types.'.str_replace('.', '-', $type), [], $locale);

        if ($line !== null || $known === null) {
            return $line ?? $type;
        }

        $carrier = ['carrier' => $this->carrierTitle($known->carrier(), $locale)];
        $kind = explode('.', $type, 2)[1] ?? '';

        return $this->line('types.kinds.'.$kind, $carrier, $locale)
            ?? $this->line('types.asks.'.$known->asks()->value, $carrier, $locale)
            ?? $carrier['carrier'];
    }

    /** One line of what the type is, for «What kind of method?». */
    public function typeDescription(string $type, string $locale): string
    {
        $known = $this->types->has($type) ? $this->types->get($type) : null;
        $own = $known instanceof LabelledMethodType ? $known->description($locale) : null;

        return $own
            ?? $this->line('types.help.'.str_replace('.', '-', $type), [], $locale)
            ?? ($known === null ? '' : ($this->line('types.help.'.$known->asks()->value, [], $locale) ?? ''));
    }

    /**
     * Whether the form asks for the kind of points. The shop's pick-up does: its points are the
     * shop's, of a kind it may name itself (decision 9). A carrier's type names its kind itself —
     * a branch is a branch — and is asked only when it offers more than one.
     */
    private function choosesKind(string $type): bool
    {
        if ($this->asks($type) !== Asks::Point) {
            return false;
        }

        return $type === StorePickup::KEY || count($this->types->get($type)->pointKinds()) > 1;
    }

    /**
     * A line of the panel's dictionary, or null when it has none.
     *
     * @param  array<string, string>  $replace
     */
    private function line(string $key, array $replace, string $locale): ?string
    {
        $full = 'webx-delivery::panel.'.$key;
        $line = trans($full, $replace, $locale);

        return is_string($line) && $line !== $full ? $line : null;
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
