<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;
use UnitEnum;

/**
 * The `changes` of a row: `[{ field, label?, from, to }]`, made comparable and kept short.
 *
 * Values are brought down to what JSON holds — an enum to its value, a moment to ISO 8601 — so
 * that `1` and `true`, or the same date in two shapes, are not written down as a change.
 *
 * A long value (a page of rich text, a repeater) is not copied into the journal twice a save:
 * the row says that it changed and how long it was before and after (§4). Whoever needs the text
 * itself has the versions.
 */
final readonly class Changes
{
    public function __construct(private int $long = 500) {}

    /**
     * Both shapes a caller may write: the list the table holds, or `field => [from, to]`.
     *
     * @param  array<array-key, mixed>  $changes
     * @return list<array<string, mixed>>
     */
    public function normalise(array $changes): array
    {
        $rows = [];

        foreach ($changes as $key => $change) {
            if (is_string($key) && is_array($change) && array_is_list($change) && count($change) === 2) {
                $row = $this->entry($key, $change[0], $change[1]);
            } elseif (is_array($change) && is_string($change['field'] ?? null)) {
                $label = $change['label'] ?? null;
                $row = $this->entry(
                    $change['field'],
                    $change['from'] ?? null,
                    $change['to'] ?? null,
                    is_string($label) ? $label : null,
                );
            } else {
                continue;
            }

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * One change, or null when there is none once both sides are plain values.
     *
     * @return array<string, mixed>|null
     */
    public function entry(string $field, mixed $from, mixed $to, ?string $label = null): ?array
    {
        $from = self::plain($from);
        $to = self::plain($to);

        if ($from === $to) {
            return null;
        }

        $row = ['field' => $field];

        if ($label !== null && $label !== '') {
            $row['label'] = $label;
        }

        $fromLength = $this->length($from);
        $toLength = $this->length($to);

        if ($fromLength > $this->long || $toLength > $this->long) {
            return $row + ['from' => null, 'to' => null, 'long' => true, 'from_length' => $fromLength, 'to_length' => $toLength];
        }

        return $row + ['from' => $from, 'to' => $to];
    }

    /**
     * A value as JSON would keep it. Numbers written as strings stay strings: a decimal column
     * reads `"100.00"`, and turning it into `100.0` would be the journal inventing a value.
     */
    public static function plain(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value), is_string($value) => $value,
            is_float($value) => is_finite($value) ? $value : (string) $value,
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof Arrayable => self::plain($value->toArray()),
            $value instanceof JsonSerializable => self::plain($value->jsonSerialize()),
            is_array($value) => array_map(self::plain(...), $value),
            $value instanceof Stringable => (string) $value,
            default => json_decode((string) json_encode($value), true),
        };
    }

    private function length(mixed $value): int
    {
        return match (true) {
            is_string($value) => mb_strlen($value),
            is_array($value) => mb_strlen((string) json_encode($value, JSON_UNESCAPED_UNICODE)),
            default => 0,
        };
    }
}
