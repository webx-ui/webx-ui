<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Geography\GeographyException;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Normalizer;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;

/**
 * What the three sets of delivery tools share: the dry run as the write rolled back
 * ({@see Rehearsal}), refusals in words, translated fields, and a locality named by its id or by
 * its name.
 *
 * @property-read Container $container
 */
trait ToolSupport
{
    /** @param  array<string, mixed>  $arguments */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function rehearse(Closure $work): mixed
    {
        return Rehearsal::run($work, (new GeoLocality)->getConnectionName());
    }

    /**
     * Run a change and turn every refusal of the doors it goes through into words for the agent.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function attempt(Closure $work): mixed
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (DeliveryException|GeographyException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * A translated field as the forms take it: a map of languages. A plain string is the default
     * language — an agent's request has none it chose.
     *
     * @return array<string, string>
     */
    private function texts(mixed $value, string $field): array
    {
        $locales = $this->locales();

        if (is_string($value)) {
            return trim($value) === ''
                ? throw new ToolFailure("`{$field}` cannot be empty.")
                : [$locales->defaultCode() => trim($value)];
        }

        if (! is_array($value) || $value === []) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (! $locales->has((string) $locale)) {
                throw new ToolFailure("`{$field}` has a value in [{$locale}], which this site is not published in. It has: ".implode(', ', $locales->codes()).'.');
            }

            $texts[(string) $locale] = is_string($text) ? trim($text) : '';
        }

        return $texts;
    }

    /**
     * A locality by its id, or by a name — the one locality of the site's countries with that
     * name; two of one name are told apart by their ids, and the refusal lists them.
     */
    private function locality(mixed $reference, string $field = 'locality'): GeoLocality
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            return GeoLocality::query()->with('region')->find((int) $reference)
                ?? throw new ToolFailure("Locality {$reference} does not exist. delivery_localities_search finds one by its name.");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure("`{$field}` is a locality's id, or its name.");
        }

        $key = Normalizer::key($reference);
        $countries = GeoCountry::query()->where('active', true)->pluck('code')->all();
        $found = GeoLocality::query()
            ->with('region')
            ->whereIn('country_code', $countries)
            ->whereIn('id', static fn ($names) => $names->select('locality_id')->from('geo_locality_names')
                ->whereIn('country_code', $countries)
                ->where('name_key', $key))
            ->orderByDesc('active')
            ->orderByDesc('weight')
            ->limit(6)
            ->get();

        if ($found->count() === 1) {
            return $found->first();
        }

        if ($found->isEmpty()) {
            throw new ToolFailure("No locality is called \"{$reference}\". delivery_localities_search finds one by the start of its name; delivery_localities_save makes one.");
        }

        $locale = $this->locales()->content();

        throw new ToolFailure(sprintf(
            'More than one locality is called "%s": %s. Name it by its id.',
            $reference,
            $found->map(static fn (GeoLocality $one): string => "#{$one->id} {$one->label($locale)}")->implode('; '),
        ));
    }

    /** The id of a locality named by its id or its name — a filter needs no more of it. */
    private function localityId(mixed $reference): int
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            return GeoLocality::query()->whereKey((int) $reference)->exists()
                ? (int) $reference
                : throw new ToolFailure("Locality {$reference} does not exist. delivery_localities_search finds one by its name.");
        }

        return $this->locality($reference)->id;
    }

    /**
     * The permission check a screen asks for a field behind one — the same the panel asks.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user instanceof HasPermissions
            ? static fn (string $permission): bool => $user->hasPermission($permission)
            : null;
    }

    /** The language the answers are in: the site's content language. */
    private function locale(): string
    {
        return $this->locales()->content();
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }

    private function int(mixed $value, string $field, int $min = 0, int $max = PHP_INT_MAX): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new ToolFailure("`{$field}` is a whole number.");
        }

        return max($min, min($max, (int) $value));
    }
}
