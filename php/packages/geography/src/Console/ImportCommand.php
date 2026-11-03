<?php

declare(strict_types=1);

namespace WebxUi\Geography\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;
use WebxUi\Geography\Editor;
use WebxUi\Geography\LocalityKind;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Geography\Models\GeoRegionAlias;
use WebxUi\Geography\Names;
use WebxUi\Geography\Normalizer;
use WebxUi\Localization\Locales;

/**
 * Lays a country's directory over the shared geography (§4.4, variant B on top of A).
 *
 * The records have the shape of a carrier's `LocalityRecord`: `country`, `name`, `kind`, and
 * optionally `region`, `district`, `lat`, `lng`, `names`, `regionNames`. A record that matches
 * an existing locality exactly updates what nobody changed by hand; the rest become localities
 * with `created_by = import`. Running the same file twice changes nothing the second time.
 */
final class ImportCommand extends Command
{
    protected $signature = 'webx:geo:import
        {file : A JSON array of records, or JSON lines — one record per line}
        {--activate : Turn on the countries the file brings (they stay off otherwise)}
        {--dry-run : Count what would change and roll it back}';

    protected $description = 'Import localities into the shared geography from a file';

    /** @var array{created: int, updated: int, unchanged: int, regions: int, skipped: int} */
    private array $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'regions' => 0, 'skipped' => 0];

    /** @var list<string> */
    private array $problems = [];

    public function handle(Editor $editor, Locales $locales): int
    {
        // The console keeps one instance of a command: a second run in the same process would
        // add to the first one's counts.
        $this->counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'regions' => 0, 'skipped' => 0];
        $this->problems = [];

        $file = (string) $this->argument('file');

        try {
            $records = $this->read($file);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $default = $locales->defaultCode();

        DB::beginTransaction();

        try {
            foreach ($records as $line => $record) {
                $problem = $this->import($record, $editor, $default);

                if ($problem !== null) {
                    $this->counts['skipped']++;
                    $this->problems[] = "Record {$line}: {$problem}";
                }
            }

            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        foreach (array_slice($this->problems, 0, 20) as $problem) {
            $this->components->warn($problem);
        }

        if (count($this->problems) > 20) {
            $this->components->warn(sprintf('… and %d more.', count($this->problems) - 20));
        }

        $this->components->info(sprintf(
            '%s%d created, %d updated, %d unchanged, %d regions created, %d skipped.',
            $this->option('dry-run') ? 'Dry run, nothing saved: ' : '',
            $this->counts['created'],
            $this->counts['updated'],
            $this->counts['unchanged'],
            $this->counts['regions'],
            $this->counts['skipped'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, mixed> Records by their number in the file, from 1.
     */
    private function read(string $file): array
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new RuntimeException("No file to read at {$file}.");
        }

        $contents = trim((string) file_get_contents($file));

        if ($contents === '') {
            return [];
        }

        try {
            if (str_starts_with($contents, '[')) {
                $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($records) || $records === []) {
                    return [];
                }

                return array_combine(range(1, count($records)), array_values($records));
            }

            $records = [];

            foreach (preg_split('/\R/', $contents) ?: [] as $index => $line) {
                if (trim($line) !== '') {
                    $records[$index + 1] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                }
            }

            return $records;
        } catch (JsonException $exception) {
            throw new RuntimeException("The file is not JSON: {$exception->getMessage()}.");
        }
    }

    /** @return string|null Why the record was skipped. */
    private function import(mixed $record, Editor $editor, string $default): ?string
    {
        if (! is_array($record)) {
            return 'not an object';
        }

        $code = strtoupper(trim((string) ($record['country'] ?? '')));

        if (! GeoCountry::isValidCode($code)) {
            return "\"{$code}\" is not a country code";
        }

        $kind = LocalityKind::tryFrom((string) ($record['kind'] ?? ''));

        if ($kind === null) {
            return 'the kind is not one of '.implode(', ', LocalityKind::values());
        }

        $names = Names::clean(is_array($record['names'] ?? null) ? $record['names'] : []);
        $name = trim((string) ($record['name'] ?? ''));

        if ($name !== '' && ! in_array($name, $names, true)) {
            $names = [$default => $name, ...$names];
        }

        if ($names === [] || Normalizer::key((string) Names::main($names)) === '') {
            return 'no name';
        }

        $this->country($code);
        $region = $this->region($code, $record, $default);

        $similar = $editor->similar([
            'country' => $code,
            'names' => $names,
            'kind' => $kind->value,
            'region_id' => $region?->id,
        ]);

        if ($similar->count() > 1) {
            return 'matches more than one locality: '.$similar->pluck('id')->implode(', ');
        }

        $attributes = [
            'names' => $names,
            'district' => Names::clean(is_array($record['district'] ?? null) ? $record['district'] : (isset($record['district']) ? [$default => (string) $record['district']] : [])) ?: null,
            'lat' => is_numeric($record['lat'] ?? null) ? round((float) $record['lat'], 6) : null,
            'lng' => is_numeric($record['lng'] ?? null) ? round((float) $record['lng'], 6) : null,
        ];

        $locality = $similar->first();

        if ($locality instanceof GeoLocality) {
            $this->update($locality, $attributes);

            return null;
        }

        GeoLocality::query()->create([
            ...$attributes,
            'country_code' => $code,
            'region_id' => $region?->id,
            'kind' => $kind->value,
            'weight' => $kind->weight(),
            'created_by' => GeoLocality::IMPORT,
        ]);
        $this->counts['created']++;

        return null;
    }

    /**
     * What nobody changed by hand: names are added language by language and never replaced,
     * coordinates and district are filled when the file has them.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function update(GeoLocality $locality, array $attributes): void
    {
        if (! $locality->isManual('names')) {
            $locality->names = [...$attributes['names'], ...$locality->names];
        }

        foreach (['district', 'lat', 'lng'] as $field) {
            if ($attributes[$field] !== null && ! $locality->isManual($field)) {
                $locality->{$field} = $attributes[$field];
            }
        }

        if ($locality->isDirty()) {
            $locality->save();
            $this->counts['updated']++;

            return;
        }

        $this->counts['unchanged']++;
    }

    private function country(string $code): void
    {
        $country = GeoCountry::query()->firstOrCreate(['code' => $code], ['active' => false]);

        if ($this->option('activate') && ! $country->active) {
            $country->active = true;
            $country->save();
        }
    }

    /** @param  array<string, mixed>  $record */
    private function region(string $country, array $record, string $default): ?GeoRegion
    {
        $names = Names::clean(is_array($record['regionNames'] ?? null) ? $record['regionNames'] : []);
        $name = trim((string) ($record['region'] ?? ''));

        if ($name !== '' && ! in_array($name, $names, true)) {
            $names = [$default => $name, ...$names];
        }

        if ($names === []) {
            return null;
        }

        $keys = array_values(array_unique(array_map(static fn (string $name): string => Normalizer::key($name), $names)));

        $region = GeoRegion::query()
            ->where('country_code', $country)
            ->where(static function ($query) use ($keys): void {
                $query->whereIn('name_key', $keys)
                    ->orWhereIn('id', GeoRegionAlias::query()->select('region_id')->whereIn('name_key', $keys));
            })
            ->orderBy('id')
            ->first();

        if ($region instanceof GeoRegion) {
            return $region;
        }

        $this->counts['regions']++;

        return GeoRegion::query()->create(['country_code' => $country, 'names' => $names]);
    }
}
