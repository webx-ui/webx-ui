<?php

declare(strict_types=1);

namespace WebxUi\Seo\Faq;

use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Targets\ForeignHost;

/**
 * Page FAQs from a brief (§18.5): lines grouped by address in the order of the file, each
 * address's questions replaced by its lines (the default) or followed by them. An address with
 * no active exact rule gets one, with nothing in its meta fields — the rule exists to carry the
 * questions, and an empty field gives way to the source below it.
 *
 * Always runnable as a preview first — the same walk with nothing written. A bad line costs
 * that line, not the file. The text goes in in the language of the address.
 */
final class FaqImport
{
    public const REPLACE = 'replace';

    public const APPEND = 'append';

    public const ERROR = 'error';

    public const WARNING = 'warning';

    /** @var array<string, SeoUrl>|null */
    private ?array $rules = null;

    public function __construct(private readonly PageFaq $faq) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows  keyed by the line number errors are reported under
     * @return array<string, mixed>
     */
    public function run(array $rows, string $mode = self::REPLACE, bool $dryRun = true, ?string $locale = null): array
    {
        $append = $mode === self::APPEND;
        $this->rules = null;
        $groups = [];
        $problems = [];

        foreach ($rows as $line => $row) {
            $address = self::cell($row, 'address');
            $question = self::cell($row, 'question');
            $answer = self::cell($row, 'answer');

            if ($address === '' || $question === '' || $answer === '') {
                $problems[] = $this->problem($line, $address === '' ? 'address' : ($question === '' ? 'question' : 'answer'), 'empty');

                continue;
            }

            if (mb_strlen($question) > 1000) {
                $problems[] = $this->problem($line, 'question', 'question-long');

                continue;
            }

            try {
                $resolved = $this->faq->address($address, $locale);
            } catch (ForeignHost) {
                $problems[] = $this->problem($line, 'address', 'foreign-host');

                continue;
            }

            $key = $resolved['address'];

            if (! isset($groups[$key])) {
                $groups[$key] = ['locale' => $resolved['locale'], 'items' => []];

                if ($resolved['redirected_from'] !== null) {
                    $problems[] = $this->problem($line, 'address', 'redirected', self::WARNING, [
                        'from' => $resolved['redirected_from'],
                        'to' => $key,
                    ]);
                }
            }

            $groups[$key]['items'][] = ['question' => $question, 'answer' => $answer, 'line' => $line];
        }

        $counts = ['addresses' => 0, 'questions' => 0, 'created' => 0, 'replaced' => 0, 'appended' => 0];
        $pages = [];

        foreach ($groups as $address => $group) {
            $rule = $this->rule((string) $address);
            $seen = [];

            if ($append && $rule !== null) {
                foreach ($rule->faqItems as $item) {
                    $seen[PageFaq::key($item->questionText($group['locale']))] = true;
                }
            }

            $items = [];

            foreach ($group['items'] as $item) {
                $key = PageFaq::key($item['question']);

                if (isset($seen[$key])) {
                    $problems[] = $this->problem($item['line'], 'question', 'duplicate');

                    continue;
                }

                $seen[$key] = true;
                $items[] = [
                    'question' => [$group['locale'] => $item['question']],
                    'answer' => [$group['locale'] => $item['answer']],
                ];
            }

            if ($items === []) {
                continue;
            }

            $action = $rule === null ? 'create' : ($append ? 'append' : 'replace');

            $counts['addresses']++;
            $counts['questions'] += count($items);
            $counts[['create' => 'created', 'append' => 'appended', 'replace' => 'replaced'][$action]]++;

            $pages[] = ['address' => $address, 'questions' => count($items), 'action' => $action];

            if (! $dryRun) {
                $rule ??= $this->faq->newRule((string) $address);
                $this->faq->write($rule, $items, $append);
                $this->rules[(string) $address] = $rule;
            }
        }

        usort($problems, static fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        return [
            'ok' => true,
            'applied' => ! $dryRun,
            'mode' => $append ? self::APPEND : self::REPLACE,
            ...$counts,
            'errors' => count(array_filter($problems, static fn (array $problem): bool => $problem['level'] === self::ERROR)),
            'problems' => $problems,
            'pages' => $pages,
        ];
    }

    /** The active exact rule of an address, from one read of them all. */
    private function rule(string $address): ?SeoUrl
    {
        if ($this->rules === null) {
            $this->rules = [];

            $found = SeoUrl::query()
                ->active()
                ->where('match_type', UrlMatcher::EXACT)
                ->with('faqItems')
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get();

            foreach ($found as $rule) {
                $this->rules[UrlNormaliser::normalise($rule->currentPattern())] ??= $rule;
            }
        }

        return $this->rules[$address] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function cell(array $row, string $column): string
    {
        return is_string($row[$column] ?? null) ? trim($row[$column]) : '';
    }

    /**
     * @param  array<string, string>  $replace
     * @return array{line: int, field: string, code: string, level: string, message: string}
     */
    private function problem(int $line, string $field, string $code, string $level = self::ERROR, array $replace = []): array
    {
        return [
            'line' => $line,
            'field' => $field,
            'code' => $code,
            'level' => $level,
            'message' => (string) __('webx-seo::faq.'.$code, $replace),
        ];
    }
}
