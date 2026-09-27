<?php

declare(strict_types=1);

namespace WebxUi\Press\Support;

use Illuminate\Container\Container;

/**
 * The kinds an article may be marked with (decision 3): keys in `webx-press.kinds`, words in
 * `webx-press::kinds.<key>`.
 *
 * Read from the config every time, not once at boot: a key taken out of the list is "no kind" on
 * every article that had it from that moment on, and a test that changes the list sees it.
 */
final class Kinds
{
    /** The longest key the column holds. */
    public const MAX = 32;

    /** @return list<string> */
    public static function all(): array
    {
        $configured = Container::getInstance()->make('config')->get('webx-press.kinds', []);
        $keys = [];

        foreach (is_array($configured) ? $configured : [] as $key) {
            if (is_string($key) && $key !== '' && strlen($key) <= self::MAX && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function has(mixed $key): bool
    {
        return is_string($key) && in_array($key, self::all(), true);
    }

    /** What the kind is called in this language — the key itself when nobody wrote a word for it. */
    public static function label(string $key, ?string $locale = null): string
    {
        $line = 'webx-press::kinds.'.$key;
        $words = Container::getInstance()->make('translator')->get($line, [], $locale);

        return is_string($words) && $words !== $line ? $words : $key;
    }

    /**
     * The options of a select: every kind, labelled by a key the panel translates.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (string $key): array => ['value' => $key, 'label' => 'trans::webx-press::kinds.'.$key],
            self::all(),
        );
    }
}
