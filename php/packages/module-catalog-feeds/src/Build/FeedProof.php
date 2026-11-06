<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

/**
 * What a trial of a feed came to (§8 of the feeds spec): the text of the sample, the first mistake
 * and what the products looked at showed. The counters of `rejected` speak about those looked at,
 * which on a big catalogue is the beginning of it; `selected` is the whole of what the filter passes.
 *
 * @phpstan-type Named array{id: int, written: bool, reason: string|null}
 */
final readonly class FeedProof
{
    /** No product suits the feed: only the head and the foot were read. */
    public const NO_PRODUCTS = 'no_products';

    /** A status goes out as one of {@see DATED}, which Google takes only with a date the catalogue lacks. */
    public const PREORDER = 'preorder';

    /** What Google takes only with `availability_date`. */
    public const DATED = ['preorder', 'backorder'];

    /** What a warning says for itself: the rest are counters the journal names. */
    private const WORDS = [self::NO_PRODUCTS => 'no-products', self::PREORDER => 'preorder'];

    /**
     * @param  array<string, int>  $warnings
     * @param  array<string, int>  $rejected
     * @param  list<Named>  $products  the ones the person named, each with its fate
     */
    public function __construct(
        public ?FeedProblem $problem = null,
        public string $text = '',
        public array $warnings = [],
        public int $written = 0,
        public int $lookedAt = 0,
        public array $rejected = [],
        public int $filtered = 0,
        public ?int $selected = null,
        public ?int $excluded = null,
        public array $products = [],
    ) {}

    /**
     * What is worth saying next to a feed that was kept.
     *
     * @return list<array{code: string, count: int, message: string|null}>
     */
    public function warnings(): array
    {
        $list = [];

        foreach ($this->warnings as $code => $count) {
            $list[] = [
                'code' => $code,
                'count' => $count,
                'message' => isset(self::WORDS[$code]) ? (string) __('webx-catalog-feeds::errors.'.self::WORDS[$code]) : null,
            ];
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->problem === null,
            'text' => $this->text,
            'errors' => $this->problem === null ? [] : [$this->problem->toArray()],
            'warnings' => $this->warnings(),
            'selected' => $this->selected,
            'excluded' => $this->excluded,
            'looked_at' => $this->lookedAt,
            'written' => $this->written,
            'rejected' => (object) $this->rejected,
            'filtered' => $this->filtered,
            'products' => $this->products,
        ];
    }
}
