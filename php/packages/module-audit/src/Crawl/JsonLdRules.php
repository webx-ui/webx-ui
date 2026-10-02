<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

/**
 * What the rich results of search engines need from the types §5.5 names, and what they show
 * better with. A list inside a requirement is "any one of": a product needs a price, a review or
 * a rating — one is enough.
 *
 * Only what the search engines' own documentation calls required is required here; the rest of
 * schema.org is somebody else's validator.
 */
final class JsonLdRules
{
    private const ARTICLE = [
        'required' => ['headline'],
        'recommended' => ['image', 'datePublished', 'author', 'description'],
    ];

    /** @var array<string, array{required: list<string|list<string>>, recommended: list<string>}> */
    private const RULES = [
        'Product' => [
            'required' => ['name', ['offers', 'review', 'aggregateRating']],
            'recommended' => ['image', 'description', 'brand', 'sku'],
        ],
        'Article' => self::ARTICLE,
        'NewsArticle' => self::ARTICLE,
        'BlogPosting' => self::ARTICLE,
        'Event' => [
            'required' => ['name', 'startDate', 'location'],
            'recommended' => ['description', 'endDate', 'image', 'offers', 'organizer', 'eventStatus'],
        ],
        'JobPosting' => [
            'required' => ['title', 'description', 'datePosted', 'hiringOrganization', ['jobLocation', 'jobLocationType']],
            'recommended' => ['validThrough', 'employmentType', 'baseSalary'],
        ],
        'FAQPage' => [
            'required' => ['mainEntity'],
            'recommended' => [],
        ],
        'BreadcrumbList' => [
            'required' => ['itemListElement'],
            'recommended' => [],
        ],
    ];

    /** Items of one block kept at most — a catalogue page may print a hundred products. */
    private const ITEMS = 20;

    /**
     * The items of a block — a list, a `@graph` or one object — that have rules, each with the
     * required and the recommended fields it lacks.
     *
     * @param  array<mixed>  $data
     * @return list<array{type: string, missing: list<string>, recommended: list<string>}>
     */
    public static function inspect(array $data): array
    {
        $items = array_is_list($data) ? $data : (isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : [$data]);
        $found = [];

        foreach ($items as $item) {
            if (! is_array($item) || count($found) >= self::ITEMS) {
                continue;
            }

            foreach ((array) ($item['@type'] ?? []) as $type) {
                if (! is_string($type) || ! isset(self::RULES[$type])) {
                    continue;
                }

                $rules = self::RULES[$type];
                $missing = [];

                foreach ($rules['required'] as $field) {
                    $any = (array) $field;

                    if (array_filter($any, static fn (string $name): bool => self::filled($item[$name] ?? null)) === []) {
                        $missing[] = implode(' | ', $any);
                    }
                }

                $missing = [...$missing, ...self::inner($type, $item)];

                $found[] = [
                    'type' => $type,
                    'missing' => $missing,
                    'recommended' => array_values(array_filter($rules['recommended'], static fn (string $name): bool => ! self::filled($item[$name] ?? null))),
                ];
            }
        }

        return $found;
    }

    /**
     * What the parts of a list need: every question of a FAQ its text and answer, every step of a
     * breadcrumb its position and name.
     *
     * @param  array<mixed>  $item
     * @return list<string>
     */
    private static function inner(string $type, array $item): array
    {
        $missing = [];

        if ($type === 'FAQPage') {
            foreach (self::listOf($item['mainEntity'] ?? null) as $question) {
                if (! self::filled($question['name'] ?? null)) {
                    $missing['mainEntity.name'] = true;
                }

                $answer = is_array($question['acceptedAnswer'] ?? null) ? $question['acceptedAnswer'] : [];

                if (! self::filled($answer['text'] ?? null)) {
                    $missing['mainEntity.acceptedAnswer.text'] = true;
                }
            }
        }

        if ($type === 'BreadcrumbList') {
            foreach (self::listOf($item['itemListElement'] ?? null) as $step) {
                if (! self::filled($step['position'] ?? null)) {
                    $missing['itemListElement.position'] = true;
                }

                $named = self::filled($step['name'] ?? null) || (is_array($step['item'] ?? null) && self::filled($step['item']['name'] ?? null));

                if (! $named) {
                    $missing['itemListElement.name'] = true;
                }
            }
        }

        return array_keys($missing);
    }

    /**
     * @return list<array<mixed>>
     */
    private static function listOf(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $list = array_is_list($value) ? $value : [$value];

        return array_values(array_filter($list, 'is_array'));
    }

    private static function filled(mixed $value): bool
    {
        return match (true) {
            $value === null => false,
            is_string($value) => trim($value) !== '',
            is_array($value) => $value !== [],
            default => true,
        };
    }
}
