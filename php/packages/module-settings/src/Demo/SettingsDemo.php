<?php

declare(strict_types=1);

namespace WebxUi\Settings\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Localization\Locales;
use WebxUi\Settings\Models\Setting;
use WebxUi\Settings\Settings;

/**
 * The general settings filled in, so that the panel has a name in its corner (§9 of the
 * new-site spec).
 *
 * A key somebody has already written is left alone, and only keys the screen describes are
 * written at all: the screen is what says whether a value is per language, and a setting
 * written past it would be one nothing can edit afterwards.
 */
final class SettingsDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ScreenRegistry $screens,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $nodes = [];

        foreach (Settings::SCREENS as $screen) {
            foreach (Tree::fields($this->screens->tree($screen)) as $node) {
                $nodes[(string) $node['name']] = $node;
            }
        }

        foreach ($this->read() as $key => $value) {
            $node = $nodes[$key] ?? null;

            $list = ($node['type'] ?? null) === 'wx-repeater' && is_array($value) && array_is_list($value);

            if ($node === null || (! is_string($value) && ! $list) || Setting::query()->where('key', $key)->exists()) {
                continue;
            }

            $ledger->created(Setting::query()->create([
                'key' => $key,
                'value' => $list ? $this->rows($node, $value) : $this->localized($node, $value),
            ]), $key);
        }
    }

    /**
     * The column holds a map of languages for a localized field and the value itself for
     * anything else; the fixture holds one string either way.
     *
     * @param  array<string, mixed>  $node
     */
    private function localized(array $node, mixed $value): mixed
    {
        return ($node['localized'] ?? false) === true && is_string($value)
            ? [$this->locales->defaultCode() => $value]
            : $value;
    }

    /**
     * A list's rows (the contacts' phones, hours, networks), each field of a row the way its
     * own child of the repeater keeps it.
     *
     * @param  array<string, mixed>  $node
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function rows(array $node, array $rows): array
    {
        $children = [];

        foreach (Tree::fields((array) ($node['children'] ?? [])) as $child) {
            $children[(string) $child['name']] = $child;
        }

        $stored = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $item = [];

            foreach ($row as $name => $value) {
                if (isset($children[$name])) {
                    $item[$name] = $this->localized($children[$name], $value);
                }
            }

            $stored[] = $item;
        }

        return $stored;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/settings.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('settings.json is not a settings document.');
        }

        return $document;
    }
}
