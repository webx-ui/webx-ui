<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\PointKinds;
use WebxUi\Delivery\Sync\Schedule;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Names;
use WebxUi\Localization\Locales;

/**
 * The screen `delivery.point-form` on the server (§14.2): one of the shop's own points — a store,
 * a pick-up point, or a kind the shop named itself. The carriers' points are theirs and come by
 * the sync; this form never opens one.
 */
final class PointForm
{
    public const string SCREEN = 'delivery.point-form';

    /** @var list<string> */
    private const array OWN = ['kind', 'titles', 'address', 'locality_id', 'lat', 'lng', 'phone', 'active', 'schedule'];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly PointKinds $kinds,
        private readonly Locales $locales,
    ) {}

    /**
     * The kinds a point of the shop may be: the two built in for the shop and its own ones.
     *
     * @return list<array{value: string, label: string}>
     */
    public function kindOptions(?string $locale = null): array
    {
        $all = $this->kinds->all($locale);
        $options = [];

        foreach ([...PointKinds::STORE_KINDS, ...array_keys($this->kinds->own())] as $kind) {
            $options[] = ['value' => $kind, 'label' => $all[$kind] ?? $kind];
        }

        return $options;
    }

    /** @return array{point: array<string, mixed>, values: array<string, mixed>} */
    public function describe(DeliveryPoint $point): array
    {
        $point->loadMissing('locality.region');

        return [
            'point' => self::row($point, $this->locales->current(), $this->kinds),
            'values' => [
                'kind' => $point->kind,
                'titles' => $point->titles ?? [],
                'address' => $point->address,
                'locality_id' => $point->locality_id,
                'lat' => $point->lat,
                'lng' => $point->lng,
                'phone' => $point->phone,
                'active' => $point->active,
                'schedule' => $point->schedule,
            ],
        ];
    }

    /**
     * A line of the list. A page of them takes the names of the kinds once, as `$kinds->all()`:
     * the shop's own kinds are read from the database, and once a row is once too often.
     *
     * @param  PointKinds|array<string, string>  $kinds
     * @return array<string, mixed>
     */
    public static function row(DeliveryPoint $point, string $locale, PointKinds|array $kinds): array
    {
        $schedule = Schedule::fromArray($point->schedule);

        return [
            'id' => $point->id,
            'kind' => $point->kind,
            'kind_label' => is_array($kinds) ? ($kinds[$point->kind] ?? $point->kind) : $kinds->label($point->kind, $locale),
            'title' => Names::pick($point->titles, $locale) ?? '',
            'address' => Names::pick($point->address, $locale) ?? '',
            'locality' => $point->locality === null ? null : LocalityRows::brief($point->locality, $locale),
            'phone' => $point->phone,
            'active' => $point->active,
            'today' => $schedule?->todayLabel($locale),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     */
    public function save(DeliveryPoint $point, array $input, ?callable $can = null): DeliveryPoint
    {
        $values = $this->record->split(self::SCREEN, $input, self::OWN, can: $can)->own;
        $errors = [];

        if (! $point->exists && ($values['locality_id'] ?? null) === null) {
            $errors['locality_id'] = [__('webx-delivery::panel.errors.locality_required')];
        }

        if (array_key_exists('address', $values) && Names::clean(is_array($values['address']) ? $values['address'] : []) === []) {
            $errors['address'] = [__('webx-delivery::panel.errors.address_required')];
        }

        if (array_key_exists('locality_id', $values) && $values['locality_id'] !== null
            && ! GeoLocality::query()->whereKey($values['locality_id'])->where(static fn ($query) => $query->where('active', true)->orWhere('id', $point->locality_id ?? 0))->exists()) {
            $errors['locality_id'] = [__('webx-delivery::panel.errors.locality_inactive')];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($values as $field => $value) {
            $point->setAttribute($field, match ($field) {
                'titles', 'address' => Names::clean(is_array($value) ? $value : []) ?: null,
                'phone' => is_string($value) && trim($value) !== '' ? trim($value) : null,
                'active' => (bool) $value,
                default => $value,
            });
        }

        $point->carrier = Carriers::STORE;
        $point->save();

        return $point;
    }
}
