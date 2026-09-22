<?php

declare(strict_types=1);

namespace WebxUi\Seo\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Localization\Locales;
use WebxUi\Seo\Fields;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * One rule written for a shape of address rather than for a page (§9 of the new-site spec).
 *
 * A mask over the blog, because that is the case a mask is for: an address that does not exist
 * yet, and will, and should already be covered. Everything a rule can hold is translated
 * except the plain fields, so the fixture writes one string and the default language gets it.
 */
final class SeoDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        foreach ($this->read() as $rule) {
            if (! is_array($rule) || ! is_string($rule['pattern'] ?? null)) {
                continue;
            }

            $pattern = $rule['pattern'];
            $type = is_string($rule['match_type'] ?? null) ? $rule['match_type'] : UrlMatcher::MASK;

            // Somebody's own rule for the same address stays theirs.
            if (SeoUrl::query()->where('match_type', $type)->where('pattern', $pattern)->exists()) {
                continue;
            }

            $values = [
                'match_type' => $type,
                'pattern' => $pattern,
                'priority' => (int) ($rule['priority'] ?? 0),
                'is_active' => (bool) ($rule['is_active'] ?? true),
            ];

            foreach (Fields::TRANSLATED as $field) {
                if (is_string($rule[$field] ?? null) && $rule[$field] !== '') {
                    $values[$field] = [$this->locales->defaultCode() => $rule[$field]];
                }
            }

            foreach (Fields::PLAIN as $field) {
                if (array_key_exists($field, $rule)) {
                    $values[$field] = $rule[$field];
                }
            }

            $ledger->created(SeoUrl::query()->create($values), $type.' '.$pattern);
        }
    }

    /**
     * @return list<mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/rules.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('rules.json is not a list of SEO rules.');
        }

        return array_values($document);
    }
}
