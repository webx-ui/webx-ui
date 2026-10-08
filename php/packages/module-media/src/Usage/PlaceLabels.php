<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Throwable;
use WebxUi\Media\Models\MediaFile;

/**
 * A row that uses a file, said the way an editor knows it: what it is, what it is called, and
 * where it is edited — «Страница: About us», rather than `pages #12`.
 *
 * Asked in order: a module's own {@see PlaceDescriber}; then `webx-media.usage.places` (a
 * kind, the column that names the row, an edit address with `{column}` filled from the row);
 * then the row itself — the first of a few usual columns it has. A localised value
 * (`{"en": …, "ru": …}`) is read in the panel's language, or else in the first one it has. A
 * table that cannot be read gives nothing, and the table and the id are said instead.
 */
final class PlaceLabels
{
    /** In order of preference, for a table nobody described. */
    private const COLUMNS = ['title', 'name', 'label', 'question', 'heading', 'key', 'slug', 'pattern', 'path', 'from'];

    private const LENGTH = 80;

    /** @var array<string, list<string>> table → its columns */
    private array $columns = [];

    /** @var list<PlaceDescriber>|null */
    private ?array $describers = null;

    public function __construct(
        private readonly ?Container $container = null,
        private readonly ?Config $config = null,
    ) {}

    /**
     * @return array{kind: string|null, label: string|null, edit_url: string|null}
     */
    public function of(string $table, string $column, int|string|null $id): array
    {
        foreach ($this->describers() as $describer) {
            try {
                $described = $describer->describe($table, $column, $id);
            } catch (Throwable $error) {
                report($error);

                continue;
            }

            if ($described !== null) {
                return [
                    'kind' => $this->kind($described['kind'] ?? null),
                    'label' => $described['label'] ?? null,
                    'edit_url' => $described['edit_url'] ?? null,
                ];
            }
        }

        /** @var array{kind?: string|null, label?: string|null, edit?: string|null}|null $place */
        $place = $this->config?->get("webx-media.usage.places.{$table}");
        $place = is_array($place) ? $place : null;

        $row = $id === null ? null : $this->row($table, $id);

        if ($row === null) {
            return ['kind' => $this->kind($place['kind'] ?? null), 'label' => null, 'edit_url' => null];
        }

        $named = $place['label'] ?? null;
        $value = is_string($named) && array_key_exists($named, $row) ? $row[$named] : null;

        if ($this->readable($value) === null) {
            foreach (self::COLUMNS as $candidate) {
                if ($this->readable($row[$candidate] ?? null) !== null) {
                    $value = $row[$candidate];

                    break;
                }
            }
        }

        return [
            'kind' => $this->kind($place['kind'] ?? null),
            'label' => $this->readable($value),
            'edit_url' => $this->address($place['edit'] ?? null, $row),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $table, int|string $id): ?array
    {
        try {
            $connection = (new MediaFile)->getConnection();

            if (! in_array('id', $this->columns($connection, $table), true)) {
                return null;
            }

            $row = $connection->table($table)->where('id', $id)->first();
        } catch (Throwable) {
            return null;
        }

        return $row === null ? null : (array) $row;
    }

    /**
     * @return list<string>
     */
    private function columns(Connection $connection, string $table): array
    {
        return $this->columns[$table] ??= $connection->getSchemaBuilder()->getColumnListing($table);
    }

    /**
     * `/press?outlet={outlet_id}` with the row's value; nothing when a value is missing.
     *
     * @param  array<string, mixed>  $row
     */
    private function address(mixed $pattern, array $row): ?string
    {
        if (! is_string($pattern) || $pattern === '') {
            return null;
        }

        $missing = false;

        $address = (string) preg_replace_callback('/\{([a-z0-9_]+)\}/', static function (array $match) use ($row, &$missing): string {
            $value = $row[$match[1]] ?? null;

            if (! is_scalar($value) || (string) $value === '') {
                $missing = true;

                return '';
            }

            return rawurlencode((string) $value);
        }, $pattern);

        return $missing ? null : $address;
    }

    /** A word of `webx-media::places`, in the panel's language; a word nobody wrote is kept. */
    private function kind(mixed $kind): ?string
    {
        if (! is_string($kind) || $kind === '') {
            return null;
        }

        $key = "webx-media::places.{$kind}";
        $word = __($key);

        return is_string($word) && $word !== $key ? $word : $kind;
    }

    /**
     * @return list<PlaceDescriber>
     */
    private function describers(): array
    {
        if ($this->describers !== null) {
            return $this->describers;
        }

        $found = [];

        foreach ($this->container?->tagged(MediaUsage::DESCRIBERS) ?? [] as $describer) {
            if ($describer instanceof PlaceDescriber) {
                $found[] = $describer;
            }
        }

        return $this->describers = $found;
    }

    private function readable(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = str_starts_with(ltrim($value), '{') ? json_decode($value, true) : null;

        if (is_array($decoded)) {
            $value = $decoded[app()->getLocale()] ?? null;

            if (! is_string($value) || $value === '') {
                $value = array_values(array_filter($decoded, static fn (mixed $one): bool => is_string($one) && $one !== ''))[0] ?? null;
            }

            if (! is_string($value)) {
                return null;
            }
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : mb_strimwidth($value, 0, self::LENGTH, '…');
    }
}
