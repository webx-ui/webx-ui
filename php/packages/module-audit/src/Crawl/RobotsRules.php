<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

/**
 * What robots.txt closes, for the `*` group — enough to mark a page `blocked_by_robots`
 * (decision 7: the audit crawls its own site regardless, and says what is closed). Longest match
 * wins, `Allow` wins a tie, `*` and `$` work as search engines read them. The checks of the file
 * itself are A3's.
 */
final readonly class RobotsRules
{
    /** The directives search engines read; `host` and `clean-param` are Yandex's. */
    private const KNOWN = ['user-agent', 'allow', 'disallow', 'sitemap', 'crawl-delay', 'host', 'clean-param'];

    /**
     * @param  list<array{0: bool, 1: string}>  $rules  [allow, pattern]
     * @param  list<string>  $sitemaps
     */
    public function __construct(
        public array $rules = [],
        public array $sitemaps = [],
    ) {}

    public static function parse(string $text): self
    {
        $groups = [];
        $agents = [];
        $inRules = false;
        $sitemaps = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));

            if (! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map(trim(...), explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'sitemap') {
                $sitemaps[] = $value;

                continue;
            }

            if ($field === 'user-agent') {
                // A user-agent line after rules starts a new group.
                if ($inRules) {
                    $agents = [];
                    $inRules = false;
                }

                $agents[] = strtolower($value);

                continue;
            }

            if (($field === 'allow' || $field === 'disallow') && $agents !== []) {
                $inRules = true;

                foreach ($agents as $agent) {
                    if ($value !== '') {
                        $groups[$agent][] = [$field === 'allow', $value];
                    }
                }
            }
        }

        return new self($groups['*'] ?? [], $sitemaps);
    }

    /**
     * The lines a search engine skips (`robots.syntax`): a directive nobody knows, a rule before
     * any `User-agent`, a line that is not `field: value`. Search engines forgive them silently,
     * which is why a typo in `Disalow` closes nothing for years.
     *
     * @return list<array{line: int, value: string, problem: string}>
     */
    public static function problems(string $text): array
    {
        $problems = [];
        $agent = false;

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $number => $raw) {
            $line = trim((string) preg_replace('/#.*$/', '', $raw));

            if ($line === '') {
                continue;
            }

            $value = mb_substr(trim($raw), 0, 200);

            if (! str_contains($line, ':')) {
                $problems[] = ['line' => $number + 1, 'value' => $value, 'problem' => 'robots-not-a-rule'];

                continue;
            }

            $field = strtolower(trim(explode(':', $line, 2)[0]));

            if ($field === 'user-agent') {
                $agent = true;
            } elseif (! in_array($field, self::KNOWN, true)) {
                $problems[] = ['line' => $number + 1, 'value' => $value, 'problem' => 'robots-unknown'];
            } elseif (in_array($field, ['allow', 'disallow', 'crawl-delay'], true) && ! $agent) {
                $problems[] = ['line' => $number + 1, 'value' => $value, 'problem' => 'robots-outside-group'];
            }
        }

        return $problems;
    }

    /**
     * @param  array{rules?: list<array{0: bool, 1: string}>, sitemaps?: list<string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['rules'] ?? [], $data['sitemaps'] ?? []);
    }

    /** @return array{rules: list<array{0: bool, 1: string}>, sitemaps: list<string>} */
    public function toArray(): array
    {
        return ['rules' => $this->rules, 'sitemaps' => $this->sitemaps];
    }

    /** Whether the `*` group closes the path (with its query). */
    public function blocks(string $path): bool
    {
        $best = -1;
        $allowed = true;

        foreach ($this->rules as [$allow, $pattern]) {
            if (! self::matches($pattern, $path)) {
                continue;
            }

            $length = strlen($pattern);

            if ($length > $best || ($length === $best && $allow)) {
                $best = $length;
                $allowed = $allow;
            }
        }

        return ! $allowed;
    }

    private static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $regex = implode('.*', array_map(static fn (string $part): string => preg_quote($part, '~'), explode('*', rtrim($pattern, '$'))));

        return preg_match('~^'.$regex.($anchored ? '$' : '').'~', $path) === 1;
    }
}
