<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests\Fixtures;

use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Inbox\Models\Form;

/**
 * Pages of a site in two languages, as the pages module answers for them: a page has an address in
 * the languages it is translated into and none in the others.
 *
 * `7` is in English and Russian, `8` in English alone, `9` is a draft.
 */
final class TranslatedPages implements LinkSource
{
    /** @var array<int, array<string, string>> */
    private const ADDRESSES = [
        7 => ['en' => 'https://example.test/thank-you', 'ru' => 'https://example.test/ru/spasibo'],
        8 => ['en' => 'https://example.test/english-only'],
        9 => ['en' => 'https://example.test/draft'],
    ];

    public function type(): string
    {
        return 'page';
    }

    public function model(): string
    {
        return Form::class;
    }

    public function title(): string
    {
        return 'Pages';
    }

    public function icon(): string
    {
        return 'file';
    }

    public function order(): int
    {
        return 100;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function search(string $query, string $locale, int $limit): array
    {
        return array_values($this->resolve(array_keys(self::ADDRESSES), $locale));
    }

    public function resolve(array $ids, string $locale): array
    {
        $found = [];

        foreach ($ids as $id) {
            if (! isset(self::ADDRESSES[$id])) {
                continue;
            }

            $url = self::ADDRESSES[$id][$locale] ?? null;

            $found[$id] = new LinkCandidate(
                id: $id,
                title: 'Page '.$id,
                url: $url,
                available: $url !== null && $id !== 9,
                hint: null,
            );
        }

        return $found;
    }
}
