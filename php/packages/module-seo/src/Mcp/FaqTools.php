<?php

declare(strict_types=1);

namespace WebxUi\Seo\Mcp;

use WebxUi\Localization\Locales;
use WebxUi\Mcp\Tool;
use WebxUi\Seo\Faq\FaqImport;
use WebxUi\Seo\Faq\PageFaq;
use WebxUi\Seo\Targets\ForeignHost;

/**
 * Page FAQs for an agent (§18.5) — handed out only while `webx-seo.faq.enabled` is on.
 *
 * By address rather than by rule: the brief names pages, and whether a page already has an exact
 * rule is this module's business. One that has none gets one, with empty meta fields.
 */
final class FaqTools
{
    /**
     * @return list<Tool>
     */
    public static function all(): array
    {
        $text = [
            'description' => 'Text in the language of the address, or an object keyed by language code',
            'type' => ['string', 'object'],
        ];

        return [
            Tool::read(
                'faq_get',
                'The FAQ of one page, by its address: the questions in order, each with its answer (HTML) in every language written.',
                static fn (array $arguments): array => self::get((string) ($arguments['url'] ?? '')),
                [
                    'properties' => ['url' => ['type' => 'string', 'description' => 'The address of the page']],
                    'required' => ['url'],
                ],
                scope: 'seo:read',
            ),

            Tool::mutating(
                'faq_set',
                'Write the whole FAQ of one page: the questions replace the ones it had, an empty list removes them. The page\'s exact rule is created if it has none, with empty meta fields. Questions go into the FAQPage markup of the page and wherever the template prints <x-webx-seo::faq />.',
                static fn (array $arguments): array => self::set($arguments),
                [
                    'properties' => [
                        'url' => ['type' => 'string', 'description' => 'The address of the page'],
                        'questions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => ['question' => $text, 'answer' => $text + ['description' => 'HTML or plain text; plain text becomes paragraphs']],
                                'required' => ['question', 'answer'],
                            ],
                        ],
                    ],
                    'required' => ['url', 'questions'],
                ],
                scope: 'seo:write',
            ),

            Tool::mutating(
                'faq_import',
                'Apply a FAQ brief: one row per question — address, question, answer (HTML or text), in the language of the address. Rows are grouped by address in order. "replace" (default) replaces the FAQ of the addresses in the rows, "append" adds to it. An address without an active exact rule gets one with empty meta fields. Run with dry_run first: it reports addresses, questions, what is created and replaced, and the problems by row number.',
                static fn (array $arguments): array => app(FaqImport::class)->run(
                    self::rows($arguments['rows'] ?? null),
                    ($arguments['mode'] ?? null) === FaqImport::APPEND ? FaqImport::APPEND : FaqImport::REPLACE,
                    (bool) ($arguments['dry_run'] ?? false),
                ),
                [
                    'properties' => [
                        'rows' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'address' => ['type' => 'string'],
                                    'question' => ['type' => 'string'],
                                    'answer' => ['type' => 'string'],
                                ],
                                'required' => ['address', 'question', 'answer'],
                            ],
                        ],
                        'mode' => ['type' => 'string', 'enum' => [FaqImport::REPLACE, FaqImport::APPEND]],
                    ],
                    'required' => ['rows'],
                ],
                scope: 'seo:write',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function get(string $url): array
    {
        $faq = app(PageFaq::class);

        try {
            $address = $faq->address($url);
            $rule = $faq->ruleFor($url);
        } catch (ForeignHost) {
            return ['ok' => false, 'reason' => 'The address is on another site.'];
        }

        return [
            'ok' => true,
            'url' => $address['address'],
            'locale' => $address['locale'],
            'rule_id' => $rule?->id,
            'questions' => $rule === null ? [] : $faq->describe($rule),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function set(array $arguments): array
    {
        $faq = app(PageFaq::class);
        $url = (string) ($arguments['url'] ?? '');

        try {
            $address = $faq->address($url);
            $rule = $faq->ruleFor($url);
        } catch (ForeignHost) {
            return ['ok' => false, 'reason' => 'The address is on another site.'];
        }

        $codes = app(Locales::class)->codes();
        $items = [];
        $problems = [];
        $seen = [];

        foreach (is_array($arguments['questions'] ?? null) ? array_values($arguments['questions']) : [] as $index => $row) {
            $question = self::text(is_array($row) ? ($row['question'] ?? null) : null, $address['locale'], $codes);
            $answer = self::text(is_array($row) ? ($row['answer'] ?? null) : null, $address['locale'], $codes);

            if ($question === [] || $answer === []) {
                $problems[] = ['index' => $index, 'reason' => 'A question needs both the question and the answer.'];

                continue;
            }

            $key = PageFaq::key($question[$address['locale']] ?? (string) reset($question));

            if (isset($seen[$key])) {
                $problems[] = ['index' => $index, 'reason' => 'This question is already in the list.'];

                continue;
            }

            $seen[$key] = true;
            $items[] = ['question' => $question, 'answer' => $answer];
        }

        if ($problems !== []) {
            return ['ok' => false, 'reason' => 'Some questions were refused; nothing was written.', 'problems' => $problems];
        }

        $result = [
            'ok' => true,
            'url' => $address['address'],
            'questions' => count($items),
            'rule' => $rule === null ? 'created' : 'existing',
            'redirected_from' => $address['redirected_from'],
        ];

        if ($arguments['dry_run'] ?? false) {
            return ['applied' => false] + $result;
        }

        $rule ??= $faq->newRule($address['address']);
        $faq->write($rule, $items);

        return ['applied' => true, 'rule_id' => $rule->id] + $result;
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private static function text(mixed $value, string $locale, array $codes): array
    {
        $value = is_string($value) ? [$locale => $value] : $value;
        $kept = [];

        foreach (is_array($value) ? $value : [] as $code => $text) {
            if (in_array((string) $code, $codes, true) && is_string($text) && trim($text) !== '') {
                $kept[(string) $code] = trim($text);
            }
        }

        return $kept;
    }

    /**
     * Numbered from one, the way a person counts the rows they sent.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rows(mixed $rows): array
    {
        $numbered = [];

        foreach (is_array($rows) ? array_values($rows) : [] as $index => $row) {
            if (is_array($row)) {
                $numbered[$index + 1] = $row;
            }
        }

        return $numbered;
    }
}
