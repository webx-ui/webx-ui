<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Traits\Localizable;
use RuntimeException;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Variables\ProductVariables;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\Templates\Template;
use WebxUi\Templates\TemplateError;
use WebxUi\Templates\Variable;
use WebxUi\Templates\Variables;

/**
 * The check of a feed before it is kept, and the preview, which are the same five steps (§8 of the
 * feeds spec): the syntax of each part, the names it uses, CDATA, a sample built by the build's own
 * code from real products, and the sample read by the parser of its format. The first step to fail
 * is the answer. A feed is taken as it stands, saved or not: nothing here writes.
 *
 * **The sample remembers where each part went**, because a parser speaks about the sample and a
 * person wrote three templates. What it cannot tell is the line of a template: a value with a line
 * break moves it, so the line of a part is a line of what that part rendered to.
 *
 * @phpstan-type Piece array{part: string, offset: int, text: string, product: int|null}
 *
 * @phpstan-import-type Named from FeedProof
 */
final class FeedTrial
{
    use Localizable;

    private const PARTS = ['head', 'item', 'foot'];

    /** How far a sample goes for its products: a form cannot wait for a walk over the catalogue. */
    private const LOOK_AT = 1000;

    private const PAGE = 50;

    /** A file of one long line is common, and the place is what is worth showing. */
    private const FRAGMENT_BYTES = 160;

    private const FRAGMENT_BEFORE = 60;

    /** How many names a mistaken one is answered with. */
    private const SIMILAR = 5;

    /** Where a failure of the settings is shown: the rest belongs to the template of a product. */
    private const FIELDS = [
        'filter-facet' => 'filter',
        'source-property' => 'sources',
        'label-property' => 'custom_labels',
        'label-source' => 'custom_labels',
    ];

    /** A person named a product and it does not go in: the reasons besides the third layer's. */
    private const NOT_FOUND = 'not_found';

    private const NOT_SELECTED = 'not_selected';

    private const EXCLUDED = 'excluded';

    private const OUT_OF_STOCK = 'out_of_stock';

    public function __construct(
        private readonly FeedRender $render,
        private readonly FeedSelection $selection,
        private readonly FeedFrame $frame,
        private readonly FeedFileCheck $check,
        private readonly ProductVariables $variables,
        private readonly Config $config,
    ) {}

    /** The check on saving. */
    public function check(Feed $feed): FeedProof
    {
        return $this->run($feed, [], false);
    }

    /**
     * What the form asks to see: the same, with the counters of the whole selection.
     *
     * @param  list<int>  $products  to look at these instead of the first ones
     */
    public function preview(Feed $feed, array $products = []): FeedProof
    {
        return $this->run($feed, array_values(array_unique($products)), true);
    }

    /**
     * The values a preview renders with in the browser on every key (§8.3): the products a sample
     * would take, or the ones named, and what a head and a foot may say. The template plays no part,
     * so a form asks once and types on; a mistake in the settings is the answer, not a refusal.
     *
     * @param  list<int>  $named
     * @return array{products: list<array{id: int, name: string, sku: string|null, values: array<string, mixed>}>, frame: array<string, mixed>, errors: list<array<string, mixed>>}
     */
    public function values(Feed $feed, array $named = []): array
    {
        try {
            return $this->withLocale($feed->locale, fn (): array => $this->valuesOf($feed, array_values(array_unique($named))));
        } catch (FeedFailure $failure) {
            $said = FeedFailure::of($failure->key, $failure->replace);
            $problem = new FeedProblem(self::FIELDS[$failure->key] ?? 'item', $said->getMessage());

            return ['products' => [], 'frame' => [], 'errors' => [$problem->toArray()]];
        }
    }

    /**
     * @param  list<int>  $named
     * @return array{products: list<array{id: int, name: string, sku: string|null, values: array<string, mixed>}>, frame: array<string, mixed>, errors: list<array<string, mixed>>}
     */
    private function valuesOf(Feed $feed, array $named): array
    {
        $selected = $this->selection->query($feed);
        $limit = $named === [] ? max(1, (int) $this->config->get('webx-catalog-feeds.sample', 5)) : count($named);
        $onlyInStock = (bool) ($feed->filter['only_in_stock'] ?? false);
        $products = [];
        $categories = [];
        $after = 0;
        $looked = 0;

        while (count($products) < $limit && $looked < self::LOOK_AT) {
            $page = $named === []
                ? $this->render->products($selected, $after, self::PAGE)
                : (clone $selected)->with('images')->whereIn('catalog_products.id', $named)->orderBy('catalog_products.id')->get();

            if ($page->isEmpty()) {
                break;
            }

            $after = (int) $page->last()->getKey();
            $looked += $page->count();
            $kept = $page->filter(static fn (Product $product): bool => FeedChecks::reason($product, $feed->locale) === null)->take($limit - count($products));
            $rows = $kept->isEmpty() ? [] : $this->render->values($feed, $kept);

            foreach ($kept as $product) {
                $row = $rows[(int) $product->id];

                if ($onlyInStock && ($row['is_purchasable'] ?? null) === '') {
                    continue;
                }

                $products[] = [
                    'id' => (int) $product->id,
                    'name' => (string) $product->getTranslation('name', $feed->locale, false),
                    'sku' => $product->sku,
                    'values' => $row,
                ];

                if ($product->category_id !== null) {
                    $categories[(int) $product->category_id] = true;
                }
            }

            if ($named !== []) {
                break;
            }
        }

        $build = new FeedBuild;
        $build->forceFill(['written' => count($products), 'state' => ['categories' => array_keys($categories)]]);

        return ['products' => $products, 'frame' => $this->frame->values($feed, $build, ['categories']), 'errors' => []];
    }

    /**
     * @param  list<int>  $named
     */
    private function run(Feed $feed, array $named, bool $counters): FeedProof
    {
        // A class of its own checks what it writes (§10).
        if ($feed->driver !== null) {
            return new FeedProof;
        }

        $problem = $this->syntax($feed) ?? $this->names($feed) ?? $this->cdata($feed) ?? $this->currency($feed);

        if ($problem instanceof FeedProblem) {
            return new FeedProof($problem);
        }

        // The values speak the feed's language; what is said about them, the language of the panel.
        $panel = App::getLocale();

        try {
            return $this->withLocale($feed->locale, fn (): FeedProof => $this->sample($feed, $named, $counters, $panel));
        } catch (FeedFailure $failure) {
            $said = FeedFailure::of($failure->key, $failure->replace);

            return new FeedProof(new FeedProblem(self::FIELDS[$failure->key] ?? 'item', $said->getMessage()));
        }
    }

    private function syntax(Feed $feed): ?FeedProblem
    {
        if (trim((string) $feed->item) === '') {
            return new FeedProblem('item', (string) __('webx-catalog-feeds::errors.no-item'));
        }

        foreach (self::PARTS as $part) {
            try {
                Template::usages((string) $feed->{$part});
            } catch (TemplateError $error) {
                return $this->unreadable($part, $error);
            }
        }

        return null;
    }

    /** A product's variable means nothing in the head: it has no product (§4.6). */
    private function names(Feed $feed): ?FeedProblem
    {
        foreach (self::PARTS as $part) {
            $known = $this->known($part);

            foreach (Template::usages((string) $feed->{$part}) as $usage) {
                if (! $known->knows($usage['name'], $usage['sections'])) {
                    $similar = $this->similar($usage['name'], $usage['sections'], $known);

                    $message = (string) __('webx-catalog-feeds::errors.unknown-variable', [
                        'name' => $usage['name'],
                        'part' => $this->word($part),
                    ]);

                    return new FeedProblem($part, $similar === [] ? $message : $message.' '.__('webx-catalog-feeds::errors.similar-variables', [
                        'names' => implode(', ', $similar),
                    ]));
                }
            }
        }

        return null;
    }

    /**
     * What a name was probably meant to be: the closest names, the fields of the lists it stands in
     * among them. A typo reads better with its correction beside it (§12).
     *
     * @param  list<string>  $sections
     * @param  Variables<Variable>  $known
     * @return list<string>
     */
    private function similar(string $name, array $sections, Variables $known): array
    {
        $names = array_map(static fn (Variable $variable): string => $variable->name, array_values($known->variables));

        foreach ($sections as $section) {
            $names = [...$names, ...array_keys($known->variable($section)->fields ?? [])];
        }

        $distances = [];

        foreach (array_unique($names) as $candidate) {
            $distance = levenshtein($name, $candidate);

            if ($distance <= max(2, intdiv(strlen($name), 3)) || str_contains($candidate, $name)) {
                $distances[$candidate] = $distance;
            }
        }

        asort($distances);

        return array_slice(array_keys($distances), 0, self::SIMILAR);
    }

    /**
     * @return Variables<Variable>
     */
    private function known(string $part): Variables
    {
        return $part === 'item' ? $this->variables->variables() : $this->frame->variables();
    }

    private function cdata(Feed $feed): ?FeedProblem
    {
        if ($feed->format !== 'xml') {
            return null;
        }

        foreach (self::PARTS as $part) {
            try {
                Template::refuseInCdata((string) $feed->{$part});
            } catch (TemplateError $error) {
                return $this->unreadable($part, $error);
            }
        }

        return null;
    }

    /** With no currency on the site a price with one would be a number and a space. */
    private function currency(Feed $feed): ?FeedProblem
    {
        if (Feed::siteCurrency() !== '') {
            return null;
        }

        foreach (self::PARTS as $part) {
            foreach (Template::usages((string) $feed->{$part}) as $usage) {
                if (str_ends_with($usage['name'], '_with_currency') || in_array($usage['name'], ['feed.currency', 'currencies'], true)) {
                    return new FeedProblem('currency', (string) __('webx-catalog-feeds::errors.currency-unset'));
                }
            }
        }

        return null;
    }

    private function unreadable(string $part, TemplateError $error): FeedProblem
    {
        return new FeedProblem($part, (string) __('webx-catalog-feeds::errors.template', ['reason' => $error->words()]));
    }

    /**
     * @param  list<int>  $named
     */
    private function sample(Feed $feed, array $named, bool $counters, string $panel): FeedProof
    {
        $selected = $this->selection->query($feed);
        $item = $this->render->item($feed);
        $names = $this->render->names($feed);
        $limit = max(1, (int) $this->config->get('webx-catalog-feeds.sample', 5));

        /** @var list<array{text: string, product: int}> $items */
        $items = [];
        $write = static function (array $row, int $id) use (&$items, $item, $limit, $named): void {
            $text = $item->render($row);

            if ($named !== [] || count($items) < $limit) {
                $items[] = ['text' => $text, 'product' => $id];
            }
        };

        $walk = $named === []
            ? $this->walk($feed, $selected, $names, $write, $limit, $items)
            : $this->named($feed, $selected, $names, $write, $named);

        $build = new FeedBuild;
        $build->forceFill(['written' => count($items), 'state' => ['categories' => $walk['categories']]]);
        [$head, $foot] = $this->render->frame($feed, $build);

        $pieces = $this->pieces($feed, $head, $items, $foot);
        $text = implode('', array_column($pieces, 'text'));
        $warnings = $walk['warnings'];

        if ($items === []) {
            $warnings = [FeedProof::NO_PRODUCTS => 1, ...$warnings];
        }

        $preorders = count(array_intersect($feed->stock_map, FeedProof::DATED));

        if ($preorders > 0) {
            $warnings[FeedProof::PREORDER] = $preorders;
        }

        return new FeedProof(
            problem: trim($text) === '' ? null : $this->withLocale($panel, fn (): ?FeedProblem => $this->parse($feed, $text, $pieces)),
            text: $text,
            warnings: $warnings,
            written: count($items),
            lookedAt: $walk['looked_at'],
            rejected: $walk['rejected'],
            filtered: $walk['filtered'],
            selected: $counters ? (clone $selected)->count() : null,
            excluded: $counters && $feed->exists ? $this->selection->excluded($feed) : ($counters ? 0 : null),
            products: $walk['products'],
        );
    }

    /**
     * The first products that make it. They may be far in: on a catalogue that starts with what has
     * no pictures, the first page alone would call a good template untested.
     *
     * @param  Builder<Product>  $selected
     * @param  list<string>  $names
     * @param  Closure(array<string, mixed>, int): void  $write
     * @param  array<array-key, mixed>  $items
     * @return array{warnings: array<string, int>, rejected: array<string, int>, filtered: int, looked_at: int, categories: list<int>, products: list<Named>}
     */
    private function walk(Feed $feed, Builder $selected, array $names, Closure $write, int $limit, array &$items): array
    {
        $tally = ['warnings' => [], 'rejected' => [], 'filtered' => 0, 'looked_at' => 0, 'categories' => [], 'products' => []];
        $after = 0;

        while (count($items) < $limit && $tally['looked_at'] < self::LOOK_AT) {
            $products = $this->render->products($selected, $after, self::PAGE);

            if ($products->isEmpty()) {
                break;
            }

            $after = (int) $products->last()->getKey();
            $tally['looked_at'] += $products->count();
            $tally = $this->add($tally, $this->render->page($feed, $products, $names, $write));
        }

        return $tally;
    }

    /**
     * The products a person asked about, and for each one that stays out, why.
     *
     * @param  Builder<Product>  $selected
     * @param  list<string>  $names
     * @param  Closure(array<string, mixed>, int): void  $write
     * @param  list<int>  $named
     * @return array{warnings: array<string, int>, rejected: array<string, int>, filtered: int, looked_at: int, categories: list<int>, products: list<Named>}
     */
    private function named(Feed $feed, Builder $selected, array $names, Closure $write, array $named): array
    {
        $tally = ['warnings' => [], 'rejected' => [], 'filtered' => 0, 'looked_at' => count($named), 'categories' => [], 'products' => []];

        $products = (clone $selected)->with('images')->whereIn('catalog_products.id', $named)->orderBy('catalog_products.id')->get();
        $page = $this->render->page($feed, $products, $names, $write);
        $tally = $this->add($tally, $page);

        $inside = $products->modelKeys();
        $outside = array_values(array_diff($named, $inside));
        $existing = Product::query()->whereKey($outside)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $excluded = $feed->exists ? $this->selection->excludedAmong($feed, $outside) : [];

        foreach ($named as $id) {
            $reason = $page->rejected[$id] ?? (in_array($id, $page->filtered, true) ? self::OUT_OF_STOCK : null);

            if (! in_array($id, $inside, false)) {
                $reason = match (true) {
                    ! in_array($id, $existing, true) => self::NOT_FOUND,
                    in_array($id, $excluded, true) => self::EXCLUDED,
                    default => self::NOT_SELECTED,
                };
            }

            $tally['products'][] = ['id' => $id, 'written' => $reason === null, 'reason' => $reason];
        }

        return $tally;
    }

    /**
     * @template T of array{warnings: array<string, int>, rejected: array<string, int>, filtered: int, looked_at: int, categories: list<int>, products: list<Named>}
     *
     * @param  T  $tally
     * @return T
     */
    private function add(array $tally, FeedPage $page): array
    {
        foreach ($page->warnings as $code => $count) {
            $tally['warnings'][$code] = ($tally['warnings'][$code] ?? 0) + $count;
        }

        foreach ($page->reasons() as $reason => $count) {
            $tally['rejected'][$reason] = ($tally['rejected'][$reason] ?? 0) + $count;
        }

        $tally['filtered'] += count($page->filtered);
        $tally['categories'] = array_values(array_unique([...$tally['categories'], ...$page->categories]));

        return $tally;
    }

    /**
     * The sample in the order of the file, each piece at its offset.
     *
     * @param  list<array{text: string, product: int}>  $items
     * @return list<Piece>
     */
    private function pieces(Feed $feed, string $head, array $items, string $foot): array
    {
        $pieces = [];
        $offset = 0;

        $add = static function (string $part, string $text, ?int $product) use (&$pieces, &$offset): void {
            if ($text !== '') {
                $pieces[] = ['part' => $part, 'offset' => $offset, 'text' => $text, 'product' => $product];
                $offset += strlen($text);
            }
        };

        $add('head', $head, null);

        foreach ($items as $index => $item) {
            if ($index > 0) {
                $add('separator', $feed->separator, null);
            }

            $add('item', $item['text'], $item['product']);
        }

        $add('foot', $foot, null);

        return $pieces;
    }

    /**
     * @param  list<Piece>  $pieces
     */
    private function parse(Feed $feed, string $text, array $pieces): ?FeedProblem
    {
        return match ($feed->format) {
            'json' => $this->json($feed, $text, $pieces),
            'xml', 'csv' => $this->file($feed, $text, $pieces),
            default => null,
        };
    }

    /**
     * A build reads each product by itself and the frame apart, so a sample that only reads as a
     * whole, an item that opens what the foot closes, must not pass here. A feed of lines has no
     * whole to read.
     *
     * @param  list<Piece>  $pieces
     */
    private function json(Feed $feed, string $text, array $pieces): ?FeedProblem
    {
        foreach ($pieces as $piece) {
            if ($piece['part'] !== 'item') {
                continue;
            }

            try {
                $this->check->jsonText($piece['text']);
            } catch (FeedFailure $failure) {
                // The end of a product is still that product, not what follows it.
                $inside = min($this->jsonPosition($piece['text'], $failure), strlen($piece['text']) - 1);

                return $this->located($failure, $text, $pieces, $piece['offset'] + $inside);
            }
        }

        if (trim((string) $feed->head.$feed->foot) === '' && trim($feed->separator) === '') {
            return null;
        }

        try {
            $this->check->jsonText($text);
        } catch (FeedFailure $failure) {
            return $this->located($failure, $text, $pieces, $this->jsonPosition($text, $failure));
        }

        return null;
    }

    /**
     * What a build's check of the file says about the sample, the same code on a file of its own.
     *
     * @param  list<Piece>  $pieces
     */
    private function file(Feed $feed, string $text, array $pieces): ?FeedProblem
    {
        $path = tempnam(sys_get_temp_dir(), 'webx-feed-') ?: throw new RuntimeException('No room for the sample of a feed.');

        try {
            file_put_contents($path, $text);
            $this->check->file($feed, $path, [], INF);

            return null;
        } catch (FeedFailure $failure) {
            return $this->located($failure, $text, $pieces, $this->filePosition($text, $failure));
        } finally {
            @unlink($path);
        }
    }

    /** The byte of a place in JSON: the parser counts bytes, up to the end of the last good token. */
    private function jsonPosition(string $text, FeedFailure $failure): int
    {
        $start = $this->lineStart($text, (int) ($failure->replace['line'] ?? 1));
        $after = min(strlen($text), $start + max(0, (int) ($failure->replace['column'] ?? 1) - 1));

        return $after + strspn($text, " \t\r\n", $after);
    }

    /** The byte of a place in a file: libxml counts characters, a row of a csv has no column. */
    private function filePosition(string $text, FeedFailure $failure): int
    {
        $start = $this->lineStart($text, (int) ($failure->replace['line'] ?? 1));
        $end = strpos($text, "\n", $start);
        $line = substr($text, $start, ($end === false ? strlen($text) : $end) - $start);
        $before = mb_substr($line, 0, max(0, (int) ($failure->replace['column'] ?? 1) - 1), 'UTF-8');

        return $start + strlen($before);
    }

    /** Where a line of a parser's starts; the end of the text for a line that is not there. */
    private function lineStart(string $text, int $line): int
    {
        $offset = 0;

        for ($at = 1; $at < $line; $at++) {
            $next = strpos($text, "\n", $offset);

            if ($next === false) {
                return strlen($text);
            }

            $offset = $next + 1;
        }

        return $offset;
    }

    /**
     * @param  list<Piece>  $pieces
     */
    private function located(FeedFailure $failure, string $text, array $pieces, int $position): FeedProblem
    {
        $piece = $this->pieceAt($pieces, $position);
        $line = substr_count($text, "\n", 0, $position) + 1;
        $partLine = substr_count($text, "\n", $piece['offset'], $position - $piece['offset']) + 1;
        $fragment = $this->fragment($text, $position);
        $said = FeedFailure::of($failure->key, [...$failure->replace, 'line' => $line]);

        return new FeedProblem(
            field: $piece['part'],
            message: rtrim($said->getMessage(), '. ').'. '.__('webx-catalog-feeds::errors.sample-place', [
                'part' => $this->word($piece['part']),
                'line' => $partLine,
                'fragment' => $fragment,
            ]),
            line: $line,
            part: $piece['part'],
            partLine: $partLine,
            product: $piece['product'],
            fragment: $fragment,
        );
    }

    /**
     * The piece a place falls in; the end of the text is the last one's, where an unclosed tag shows.
     *
     * @param  list<Piece>  $pieces
     * @return Piece
     */
    private function pieceAt(array $pieces, int $position): array
    {
        $found = $pieces[0];

        foreach ($pieces as $piece) {
            if ($piece['offset'] > $position) {
                break;
            }

            $found = $piece;
        }

        return $found;
    }

    private function fragment(string $text, int $position): string
    {
        // The line a parser stopped at; the end of a text is where the last line with words is.
        $place = min($position, strlen(rtrim($text)));
        $start = strrpos(substr($text, 0, $place), "\n");
        $start = $start === false ? 0 : $start + 1;
        $end = strpos($text, "\n", $place);
        $line = substr($text, $start, ($end === false ? strlen($text) : $end) - $start);

        return trim(mb_strcut($line, max(0, $place - $start - self::FRAGMENT_BEFORE), self::FRAGMENT_BYTES, 'UTF-8'));
    }

    private function word(string $part): string
    {
        return (string) __('webx-catalog-feeds::feed.'.$part);
    }
}
