<?php

declare(strict_types=1);

namespace WebxUi\Debug\Support;

use DateTimeInterface;
use Illuminate\Contracts\Config\Repository as Config;
use Stringable;

/**
 * What never goes into a report as it is (§10 of the debug bar spec).
 *
 * A report is shown only to the administrator who made it, and only outside production — but it
 * is kept for minutes in a store, copied into an agent's context and pasted into chats. So a
 * password, a token or a session payload is replaced while it is written down, not when it is
 * shown: there is no unmasked report anywhere to leak. The mask says nothing of the length.
 */
final class Masker
{
    public const MASK = '••••';

    /** Headers whose values are secrets, whatever the config adds. */
    private const HEADERS = ['authorization', 'proxy-authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token', 'x-webx-debug'];

    /** @var list<string>|null */
    private ?array $columns = null;

    /** @var list<string>|null */
    private ?array $tables = null;

    public function __construct(private readonly Config $config) {}

    /**
     * The bindings of a statement with the secret ones masked, and every value cut to length.
     *
     * Which column a placeholder belongs to is read off the SQL around it: `"password" = ?`,
     * `"token" in (?, ?)`, `set "remember_token" = ?`, or the column list of an insert. A
     * statement on a table kept whole in the list is masked whole. A shape not understood shows
     * its values: the list grows with the site, and a value hidden by mistake is a lost hour.
     *
     * @param  array<int|string, mixed>  $bindings
     * @return list<mixed>
     */
    public function bindings(string $sql, array $bindings): array
    {
        $values = array_values($bindings);

        if ($values === []) {
            return [];
        }

        if ($this->touchesMaskedTable($sql)) {
            return array_fill(0, count($values), self::MASK);
        }

        $columns = $this->placeholderColumns($sql, count($values));

        foreach ($values as $index => $value) {
            $column = $columns[$index] ?? null;
            $values[$index] = $column !== null && $this->isSecretColumn($column) ? self::MASK : $this->value($value);
        }

        return $values;
    }

    /** One value as a report keeps it: scalars as they are, strings cut, objects named. */
    public function value(mixed $value): mixed
    {
        $limit = max(16, (int) $this->config->get('webx-debug.limits.value', 500));

        return match (true) {
            $value === null, is_bool($value), is_int($value), is_float($value) => $value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_string($value) => ! mb_check_encoding($value, 'UTF-8')
                ? '0x'.bin2hex(substr($value, 0, 16)).(strlen($value) > 16 ? '…' : '')
                : (mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).'…' : $value),
            $value instanceof Stringable => $this->value((string) $value),
            is_object($value) => '['.$value::class.']',
            is_array($value) => '[array('.count($value).')]',
            default => '['.get_debug_type($value).']',
        };
    }

    /**
     * Headers by name, the secret ones masked; cookies keep their names and lose their values.
     *
     * @param  array<string, list<string|null>|string|null>  $headers
     * @return array<string, string>
     */
    public function headers(array $headers): array
    {
        $masked = array_map('strtolower', array_map('strval', (array) $this->config->get('webx-debug.mask.headers', [])));
        $out = [];

        foreach ($headers as $name => $values) {
            $name = strtolower((string) $name);
            $value = implode(', ', array_map('strval', array_filter((array) $values, static fn (mixed $one): bool => $one !== null)));

            if ($name === 'cookie') {
                $out[$name] = implode('; ', array_map(
                    static fn (string $pair): string => trim(explode('=', $pair, 2)[0]).'='.self::MASK,
                    array_filter(explode(';', $value), static fn (string $pair): bool => trim($pair) !== ''),
                ));

                continue;
            }

            if ($name === 'set-cookie') {
                $out[$name] = implode(', ', array_map(
                    static fn (mixed $one): string => trim(explode('=', (string) $one, 2)[0]).'='.self::MASK,
                    array_filter((array) $values, static fn (mixed $one): bool => $one !== null),
                ));

                continue;
            }

            $out[$name] = in_array($name, self::HEADERS, true) || in_array($name, $masked, true) ? self::MASK : (string) $this->value($value);
        }

        ksort($out);

        return $out;
    }

    /**
     * An array by its keys — the context of a log line, the query of an outgoing request.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function keyed(array $values, int $depth = 0): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSecretColumn($key)) {
                $out[$key] = self::MASK;
            } elseif (is_array($value) && $depth < 3) {
                $out[$key] = $this->keyed($value, $depth + 1);
            } else {
                $out[$key] = $this->value($value);
            }
        }

        return $out;
    }

    /** An address with the secret query parameters masked. */
    public function url(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return $url;
        }

        parse_str($query, $parameters);
        $masked = $this->keyed($parameters);
        $rebuilt = http_build_query($masked, '', '&', PHP_QUERY_RFC3986);

        return str_replace('?'.$query, '?'.str_replace(rawurlencode(self::MASK), self::MASK, $rebuilt), $url);
    }

    /** An e-mail address with the person taken out of it. */
    public function email(string $address): string
    {
        $at = strrpos($address, '@');

        return $at === false ? self::MASK : self::MASK.substr($address, $at);
    }

    public function isSecretColumn(string $column): bool
    {
        $column = strtolower(trim($column, '`"[] '));

        if (str_contains($column, '.')) {
            $column = substr($column, (int) strrpos($column, '.') + 1);
        }

        foreach ($this->columns() as $pattern) {
            if (fnmatch($pattern, $column)) {
                return true;
            }
        }

        return false;
    }

    private function touchesMaskedTable(string $sql): bool
    {
        if (preg_match_all('/\b(?:from|into|update|join)\s+[`"\[]?([a-z0-9_]+)[`"\]]?/i', $sql, $matches) === false) {
            return false;
        }

        $prefix = strtolower((string) $this->config->get('database.connections.'.$this->config->get('database.default').'.prefix', ''));

        foreach ($matches[1] as $table) {
            $table = strtolower($table);

            if ($prefix !== '' && str_starts_with($table, $prefix)) {
                $table = substr($table, strlen($prefix));
            }

            foreach ($this->tables() as $pattern) {
                if (fnmatch($pattern, $table)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The column each placeholder is bound to, as far as the SQL says it.
     *
     * @return array<int, string|null>
     */
    private function placeholderColumns(string $sql, int $count): array
    {
        $columns = [];

        // insert into "t" ("a", "b") values (?, ?), (?, ?)
        if (preg_match('/^\s*(?:insert|replace)(?:\s+ignore)?\s+into\s+\S+\s*\(([^)]*)\)\s*values/i', $sql, $insert) === 1) {
            $names = array_map(static fn (string $name): string => trim($name, " `\"[]\t\n"), explode(',', $insert[1]));

            for ($i = 0; $i < $count; $i++) {
                $columns[$i] = $names[$i % max(1, count($names))] ?? null;
            }

            return $columns;
        }

        $offset = 0;

        for ($i = 0; $i < $count; $i++) {
            $at = strpos($sql, '?', $offset);

            if ($at === false) {
                break;
            }

            $before = substr($sql, max(0, $at - 200), min(200, $at));
            $columns[$i] = self::columnBefore($before);
            $offset = $at + 1;
        }

        return $columns;
    }

    /** The column a placeholder at the end of `$before` is compared with or assigned to. */
    private static function columnBefore(string $before): ?string
    {
        $name = '((?:[`"\[]?[a-z0-9_]+[`"\]]?\.)?[`"\[]?[a-z0-9_]+[`"\]]?)';

        if (preg_match('/'.$name.'\s*(?:=|!=|<>|<=|>=|<|>|\s+(?:not\s+)?like)\s*$/i', $before, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/'.$name.'\s+(?:not\s+)?in\s*\((?:\s*\?\s*,)*\s*$/i', $before, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /** @return list<string> */
    private function columns(): array
    {
        return $this->columns ??= array_values(array_map(static fn (mixed $one): string => strtolower((string) $one), (array) $this->config->get('webx-debug.mask.columns', [])));
    }

    /** @return list<string> */
    private function tables(): array
    {
        return $this->tables ??= array_values(array_map(static fn (mixed $one): string => strtolower((string) $one), (array) $this->config->get('webx-debug.mask.tables', [])));
    }
}
