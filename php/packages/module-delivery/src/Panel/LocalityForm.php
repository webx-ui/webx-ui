<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Geography\DuplicateLocality;
use WebxUi\Geography\Editor;
use WebxUi\Geography\GeographyException;
use WebxUi\Geography\LocalityReferences;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Localization\Locales;

/**
 * The screen `delivery.locality-form` on the server (§4.6, §14.2): a locality made by hand,
 * or any locality changed — the same form opens on the spot from a point and from the courier's
 * list, short of the fields the project hides with a patch.
 *
 * The screen checks the shape of the values; the rules of the geography — a name in some
 * language, a region of the country, the duplicate — are {@see Editor}'s. A duplicate is not a
 * 422: it is a question, so it answers 409 with the similar localities for the form to offer.
 */
final class LocalityForm
{
    public const string SCREEN = 'delivery.locality-form';

    /** @var list<string> */
    private const array OWN = ['names', 'kind', 'country', 'region_id', 'lat', 'lng', 'weight', 'active'];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Editor $editor,
        private readonly LocalityReferences $references,
        private readonly Locales $locales,
    ) {}

    /** @return array{locality: array<string, mixed>, values: array<string, mixed>} */
    public function describe(GeoLocality $locality): array
    {
        $locality->loadMissing('region');
        $locale = $this->locales->current();

        return [
            'locality' => [
                ...LocalityRows::brief($locality, $locale),
                'source' => LocalityRows::source($locality),
                'created_by' => $locality->created_by,
                // The fields an administrator changed on a carrier's record: a sync leaves them be.
                'manual_fields' => $locality->created_by === GeoLocality::MANUAL ? [] : array_values((array) ($locality->manual_fields ?? [])),
                // Who holds it: «Delete» then only switches it off (decision 42).
                'held_by' => $this->references->holding($locality),
            ],
            'values' => LocalityRows::values($locality),
        ];
    }

    /** @param  array<string, mixed>  $input */
    public function create(array $input, bool $force = false): GeoLocality
    {
        return $this->write(fn (): GeoLocality => $this->make($input, $force));
    }

    /**
     * The same save with the editor's refusals as they are — {@see DuplicateLocality} with the
     * similar ones, {@see GeographyException} — for a door that words them itself (MCP).
     *
     * @param  array<string, mixed>  $input
     */
    public function make(array $input, bool $force = false): GeoLocality
    {
        $values = $this->check($input);

        // No weight given — the type's (§4.1), and the recount after points goes on adding to it.
        if (($values['weight'] ?? null) === null) {
            unset($values['weight']);
        }

        return $this->editor->createLocality($values, $force);
    }

    /** @param  array<string, mixed>  $input */
    public function update(GeoLocality $locality, array $input, bool $force = false): GeoLocality
    {
        return $this->write(fn (): GeoLocality => $this->change($locality, $input, $force));
    }

    /**
     * {@see self::update()} with the editor's refusals as they are, as {@see self::make()}.
     *
     * @param  array<string, mixed>  $input
     */
    public function change(GeoLocality $locality, array $input, bool $force = false): GeoLocality
    {
        $values = $this->check($input);
        // The country of a locality is where its carriers bound it; it is not moved by hand.
        unset($values['country']);

        if (array_key_exists('weight', $values) && $values['weight'] === null) {
            unset($values['weight']);
        }

        return $this->editor->updateLocality($locality, $values, $force);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function check(array $input): array
    {
        $values = $this->record->split(self::SCREEN, $input, self::OWN)->own;

        if (array_key_exists('country', $values)) {
            $values['country'] = (string) $values['country'];
        }

        return $values;
    }

    /** @param  callable(): GeoLocality  $write */
    private function write(callable $write): GeoLocality
    {
        try {
            return $write();
        } catch (DuplicateLocality $duplicate) {
            $locale = $this->locales->current();

            throw new HttpResponseException(new JsonResponse([
                'message' => __('webx-delivery::panel.locality.duplicate'),
                'similar' => $duplicate->similar
                    ->map(static fn (GeoLocality $locality): array => [
                        ...LocalityRows::brief($locality, $locale),
                        'source' => LocalityRows::source($locality),
                    ])
                    ->values()
                    ->all(),
            ], 409));
        } catch (GeographyException $refusal) {
            $field = str_contains($refusal->getMessage(), 'region') ? 'region_id'
                : (str_contains($refusal->getMessage(), 'type') ? 'kind' : 'names');

            throw ValidationException::withMessages([$field => [$refusal->getMessage()]]);
        }
    }
}
