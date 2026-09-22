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

        foreach (Tree::fields($this->screens->tree(Settings::SCREEN)) as $node) {
            $nodes[(string) $node['name']] = $node;
        }

        foreach ($this->read() as $key => $value) {
            $node = $nodes[$key] ?? null;

            if ($node === null || ! is_string($value) || Setting::query()->where('key', $key)->exists()) {
                continue;
            }

            $ledger->created(Setting::query()->create([
                'key' => $key,
                // The column holds a map of languages for a localized field and the value
                // itself for anything else; the fixture holds one string either way.
                'value' => ($node['localized'] ?? false) === true
                    ? [$this->locales->defaultCode() => $value]
                    : $value,
            ]), $key);
        }
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
