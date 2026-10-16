<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Localization\Locales;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Places;

/**
 * One snippet to show what the section holds (§13 of the snippets spec): a comment and a
 * `console.log`, waiting for the analytics category — and switched off.
 *
 * Off, because a demo must not run anything on a site, and an id of a real service in it would
 * count somebody's visits; an administrator who switches it on sees the line in the console.
 */
final class SnippetsDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly Places $places,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A site with any snippet at all has pasted its own.
        if (Snippet::withTrashed()->exists()) {
            return;
        }

        foreach ((array) ($this->read()['snippets'] ?? []) as $input) {
            if (! is_array($input)) {
                continue;
            }

            $key = is_string($input['key'] ?? null) ? $input['key'] : '';
            $place = is_string($input['place'] ?? null) ? $input['place'] : '';
            $code = is_string($input['code'] ?? null) ? $input['code'] : '';

            if ($key === '' || $code === '' || ! $this->places->has($place)) {
                continue;
            }

            $snippet = Snippet::query()->create([
                'name' => $this->name($input['name'] ?? null, $key),
                'place' => $place,
                'code' => $code,
                'consent' => in_array($input['consent'] ?? null, Snippet::CATEGORIES, true) ? $input['consent'] : 'necessary',
                'enabled' => false,
                'on_stage' => false,
            ]);

            $ledger->created($snippet, $key);
        }
    }

    /**
     * The name in the site's default language when the demo has it, else the English one: a
     * snippet's name is one string, for the administrators.
     */
    private function name(mixed $name, string $key): string
    {
        if (! is_array($name)) {
            return $key;
        }

        $default = $name[$this->locales->defaultCode()] ?? $name['en'] ?? null;

        return is_string($default) && trim($default) !== '' ? trim($default) : $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/snippets.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('snippets.json is not a list of snippets.');
        }

        return $document;
    }
}
