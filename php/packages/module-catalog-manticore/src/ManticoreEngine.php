<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Engine\FacetPlacement;
use WebxUi\Catalog\Engine\FacetResult;
use WebxUi\Catalog\Engine\RebuildsAside;
use WebxUi\Catalog\Engine\SqlEngine;
use WebxUi\Catalog\Facets\BatchCountedFacet;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\Localization\Locales;

/**
 * Manticore as the catalogue's engine (the Manticore spec): a table per language, written from
 * the core's queue, asked the same questions as {@see SqlEngine} and answering them the same way —
 * the shared engine tests hold both to it (decision 26).
 *
 * A question is one round trip: the page, the total and the counts of every facet nobody chose
 * in one statement — a `FACET` per field, so the hundred properties that share `pv` are one —
 * and beside it, at once, a statement per chosen facet without its own choice, and one for the
 * ends of the ranges (decisions 23–25).
 *
 * When the server does not answer (decisions 10–14): the panel always, and a catalogue within
 * `sql_engine_limit`, read the database instead; a larger catalogue answers 503, because the
 * database engine with facets over a hundred thousand products would bring the database down
 * with it. The failure is remembered for half a minute, so nobody waits for it twice.
 *
 * A rebuild fills `{table}_next` beside the live tables and swaps them in at the end
 * (decision 8); a stale schema is only reported (decision 9) — {@see status()}.
 *
 * A search is the words and their beginnings, and a code by any part of it; the product whose
 * code the search is comes first (decisions 19–22). When nothing is found, a second pass: the
 * search as typed with another keyboard layout of the site's languages, then each word as
 * `CALL QSUGGEST` spells it — and the answer says what it was for (decisions 15–17).
 */
final class ManticoreEngine implements RebuildsAside
{
    private const MISSING = 'missing';

    private const STALE = 'stale';

    private const READY = 'ready';

    /** The alias of «the search is this product's code» in a select list. */
    private const EXACT = 'is_exact';

    /** The cache key of what a live table has, by its name. */
    private const LIVE = 'webx-catalog-manticore.live-schema.';

    /** @var array<string, TableSchema> locale → the schema its table should have */
    private array $schemas = [];

    /** @var list<IndexField>|null */
    private ?array $fields = null;

    /** Whether the documents written go into the tables a rebuild fills, rather than the live ones. */
    private bool $rebuilding = false;

    /** Whether another process is rebuilding: what is written goes into both. */
    private bool $besideRebuild = false;

    private bool $fellBack = false;

    public function __construct(
        private readonly Manticore $server,
        private readonly Facets $facets,
        private readonly Sorts $sorts,
        private readonly Documents $documents,
        private readonly Locales $locales,
        private readonly FacetPlacement $placement,
        private readonly SqlEngine $database,
        private readonly Config $config,
        private readonly Cache $cache,
    ) {}

    public function search(CatalogQuery $query): CatalogResult
    {
        $this->fellBack = false;

        if (! $this->server->down()) {
            try {
                return $this->answer($query);
            } catch (ManticoreUnavailable) {
                // Remembered by the server; the fallback below.
            }
        }

        $panel = $query->context === 'panel' || $query->withUnpublished || $query->onlyTrashed;

        if (! $panel && ! $this->small()) {
            throw new CatalogUnavailable($this->server->retryAfter());
        }

        $this->fellBack = true;
        $answer = $this->database->search($query);

        return new CatalogResult($answer->ids, $answer->total, $answer->facets, $answer->exact, $answer->corrected, fellBack: true);
    }

    /** Whether the last answer came from the database because Manticore did not — the panel's notice. */
    public function fellBack(): bool
    {
        return $this->fellBack;
    }

    public function needsIndex(): bool
    {
        return true;
    }

    /**
     * Every language's table made when it is missing; with `$rebuild`, a fresh `{table}_next`
     * beside each, which {@see index()} fills until {@see completeRebuild()}. A table whose schema
     * is out of date is left as it is: the rebuild is for a person to start (decision 9).
     */
    public function prepare(array $fields, bool $rebuild = false): void
    {
        $this->fields = $fields;
        $this->schemas = [];
        $existing = $this->tables();
        $statements = [];

        foreach ($this->locales->codes() as $locale) {
            $schema = $this->schema($locale);
            $table = $this->server->table($locale);
            $next = $this->server->table($locale, next: true);

            if ($rebuild) {
                $statements[] = 'DROP TABLE IF EXISTS '.$next;
                $statements[] = $schema->create($next);
            } elseif (! in_array($table, $existing, true)) {
                $statements[] = $schema->create($table);
            }
        }

        // One after another: a table must be gone before it is made again.
        foreach ($statements as $statement) {
            $this->server->sql($statement);
        }

        $this->forgetLive();

        $this->rebuilding = $rebuild;
        $this->besideRebuild = ! $rebuild && array_filter($existing, static fn (string $name): bool => str_ends_with($name, '_next')) !== [];
    }

    public function index(array $documents): void
    {
        $lines = [];

        foreach ($this->locales->codes() as $locale) {
            $next = $this->server->table($locale, next: true);

            foreach ($this->targets($locale) as $table) {
                // A live table of an older schema takes what it has columns for (§6 of the spec).
                $schema = $table === $next ? $this->schema($locale) : $this->reading($locale);

                foreach ($documents as $id => $document) {
                    $lines[] = ['replace' => ['table' => $table, 'id' => (int) $id, 'doc' => $schema->row($document)]];
                }
            }
        }

        $this->server->bulk($lines);
    }

    public function remove(array $ids): void
    {
        $lines = [];

        foreach ($this->locales->codes() as $locale) {
            foreach ($this->targets($locale, live: true) as $table) {
                foreach ($ids as $id) {
                    $lines[] = ['delete' => ['table' => $table, 'id' => (int) $id]];
                }
            }
        }

        $this->server->bulk($lines);
    }

    /**
     * The rebuilt tables in place of the live ones. There is no rename onto a table that exists,
     * so the old one is dropped first; the moment between is a few milliseconds.
     */
    public function completeRebuild(): void
    {
        foreach ($this->locales->codes() as $locale) {
            $table = $this->server->table($locale);
            $this->server->sql('DROP TABLE IF EXISTS '.$table);
            $this->server->sql('ALTER TABLE '.$this->server->table($locale, next: true).' RENAME '.$table);
        }

        $this->rebuilding = false;
        $this->forgetLive();
    }

    /**
     * Each language's table as it stands: missing, of another schema (and why), or ready, with
     * the documents in it, and whether a rebuild is filling the one beside it — what
     * `webx:doctor` reports and «System → Search index» shows (decision 27).
     *
     * @return array<string, array{table: string, state: string, reason: string|null, documents: int|null, rebuilding: bool}>
     */
    public function status(): array
    {
        $existing = $this->tables();
        $status = [];
        $asked = [];

        foreach ($this->locales->codes() as $locale) {
            $table = $this->server->table($locale);
            $status[$locale] = [
                'table' => $table,
                'state' => self::MISSING,
                'reason' => null,
                'documents' => null,
                'rebuilding' => in_array($this->server->table($locale, next: true), $existing, true),
            ];

            if (in_array($table, $existing, true)) {
                $asked[$locale] = $table;
            }
        }

        $described = $this->describe(array_values($asked), count: true);

        foreach ($asked as $locale => $table) {
            $live = $described[$table];
            $this->cache->put(self::LIVE.$table, $live, $this->liveFor());
            $reason = $this->schema($locale)->differs($live['columns'], $live['settings']);
            $status[$locale]['state'] = $reason === null ? self::READY : self::STALE;
            $status[$locale]['reason'] = $reason;
            $status[$locale]['documents'] = $live['documents'];
        }

        return $status;
    }

    /**
     * What each language's table holds for one product — whether it is there and with which
     * flags — for an agent asked why the product is not found (decision 28). Null for a table
     * that is not there.
     *
     * @return array<string, array{table: string, indexed: bool|null, deleted?: bool, visible?: bool, published?: bool}>
     */
    public function document(int $id): array
    {
        $existing = $this->tables();
        $asked = [];
        $answer = [];

        foreach ($this->locales->codes() as $locale) {
            $table = $this->server->table($locale);
            $answer[$locale] = ['table' => $table, 'indexed' => null];

            if (in_array($table, $existing, true)) {
                $asked[$locale] = 'SELECT id, is_deleted, is_visible, is_published FROM '.$table.' WHERE id = '.$id;
            }
        }

        $sets = array_combine(array_keys($asked), $this->server->sqlMany(array_values($asked)));

        foreach ($sets as $locale => $set) {
            $row = self::rows($set[0] ?? [])[0] ?? null;
            $answer[$locale]['indexed'] = $row !== null;

            if ($row !== null) {
                $answer[$locale] += [
                    'deleted' => (bool) $row['is_deleted'],
                    'visible' => (bool) $row['is_visible'],
                    'published' => (bool) $row['is_published'],
                ];
            }
        }

        return $answer;
    }

    /**
     * The answer to the question, or — when a search found nothing and was not asked as typed —
     * to the first correction of it that finds something (decision 16). A correction that finds
     * nothing either leaves the empty answer as it was: nothing is shown for words nobody typed.
     */
    private function answer(CatalogQuery $query): CatalogResult
    {
        $result = $this->ask($query);
        $term = trim((string) $query->search);

        if ($result->total > 0 || $term === '' || $query->asTyped) {
            return $result;
        }

        try {
            $corrected = $this->correction($query, $term);
        } catch (ManticoreError) {
            // A table of an older schema has no infix to suggest from: as typed, until rebuilt.
            return $result;
        }

        if ($corrected === null) {
            return $result;
        }

        $found = $this->ask($query->withSearch($corrected));

        return $found->total === 0
            ? $result
            : new CatalogResult($found->ids, $found->total, $found->facets, $found->exact, $corrected);
    }

    /**
     * Another spelling of the search that finds something: first the other keyboard layouts,
     * side by side, the first that finds a product winning; then each word as the table's
     * dictionary would spell it. Null when there is none.
     */
    private function correction(CatalogQuery $query, string $term): ?string
    {
        $locale = $this->localeOf($query);
        $schema = $this->reading($locale);
        $table = $this->server->table($locale);
        $layouts = new KeyboardLayouts((array) $this->config->get('webx-catalog-manticore.layouts', []));
        $spellings = $layouts->alternatives($term, $this->locales->codes(), $locale);

        if ($spellings !== []) {
            $answers = $this->server->sqlMany(array_map(
                fn (string $spelling): string => $this->select($table, $query->withSearch($spelling), $schema).' LIMIT 1',
                $spellings,
            ));

            foreach ($spellings as $index => $spelling) {
                if (self::rows($answers[$index][0] ?? []) !== []) {
                    return $spelling;
                }
            }
        }

        // A table of an older schema without infix has nothing to suggest from, until rebuilt.
        if ($schema->infixLength() === 0) {
            return null;
        }

        $words = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $asked = [];

        foreach ($words as $index => $word) {
            // A code or a number is not a misspelt word.
            if (mb_strlen($word) >= 2 && preg_match('/^\p{L}+$/u', $word) === 1) {
                $asked[$index] = 'CALL QSUGGEST('.Manticore::quote(mb_strtolower($word)).', '.Manticore::quote($table).', 1 AS limit)';
            }
        }

        if ($asked === []) {
            return null;
        }

        $answers = array_combine(array_keys($asked), $this->server->sqlMany(array_values($asked)));

        foreach ($answers as $index => $sets) {
            $best = self::rows($sets[0] ?? [])[0]['suggest'] ?? null;

            if (is_string($best) && $best !== '') {
                $words[$index] = $best;
            }
        }

        $corrected = implode(' ', $words);

        return mb_strtolower($corrected) === mb_strtolower(implode(' ', preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [])) ? null : $corrected;
    }

    /**
     * The page, the total and the counts, in one round trip of statements side by side.
     */
    private function ask(CatalogQuery $query): CatalogResult
    {
        $locale = $this->localeOf($query);
        $schema = $this->reading($locale);
        $table = $this->server->table($locale);
        $plan = $this->plan($query, $schema);
        $limit = max(1, (int) $this->config->get('webx-catalog-manticore.facet_values', 5000));
        $offset = (max(1, $query->page) - 1) * max(1, $query->perPage);
        $matches = max($offset + max(1, $query->perPage), $limit);

        $main = $this->select($table, $query, $schema)
            .' ORDER BY '.$this->order($query, $schema, $locale)
            .' LIMIT '.$offset.', '.max(1, $query->perPage)
            .' OPTION max_matches='.$matches.$this->weights($schema)
            .' FACET 1';

        foreach ($plan['columns'] as $column) {
            $main .= ' FACET '.$column.' ORDER BY COUNT(*) DESC LIMIT '.$limit;
        }

        $statements = ['main' => $main];

        if ($this->exactCode($query, $schema) !== null) {
            $statements['exact'] = $this->select($table, $query, $schema).' AND '.self::EXACT.' = 1 LIMIT 2';
        }

        if ($plan['ranges'] !== []) {
            $statements['ranges'] = $this->ends($table, $query, $schema, $plan['ranges']);
        }

        foreach ($plan['chosen'] as $key => $facet) {
            $statements['chosen:'.$key] = $facet->kind() === FacetKind::Range
                ? $this->ends($table, $query, $schema, [$key => $facet], except: $key)
                : $this->select($table, $query, $schema, except: $key)
                    .' LIMIT 1 OPTION max_matches='.$limit.$this->weights($schema)
                    .' FACET '.$this->field($facet, $schema)?->name.' ORDER BY COUNT(*) DESC LIMIT '.$limit;
        }

        $sample = max(1, (int) $this->config->get('webx-catalog-manticore.relevance_sample', 1000));

        if ($plan['picks']) {
            $statements['sample'] = $this->select($table, $query, $schema).' LIMIT '.$sample.' OPTION max_matches='.$sample;
        }

        $answers = array_combine(array_keys($statements), $this->server->sqlMany(array_values($statements)));
        $sets = $answers['main'];
        $ids = array_map(static fn (array $row): int => (int) $row['id'], self::rows($sets[0] ?? []));
        $total = (int) (self::rows($sets[1] ?? [])[0]['count(*)'] ?? 0);
        $exact = array_map(static fn (array $row): int => (int) $row['id'], self::rows($answers['exact'][0] ?? []));

        if ($query->count === []) {
            return new CatalogResult($ids, $total, exact: $exact);
        }

        $byColumn = [];

        foreach (array_values($plan['columns']) as $index => $column) {
            $byColumn[$column] = self::counts($sets[$index + 2] ?? [], $column);
        }

        $counted = $this->unchosen($plan['grouped'], $byColumn, $schema);

        if (isset($answers['ranges'])) {
            $counted += $this->rangeResults($plan['ranges'], $answers['ranges'][0] ?? []);
        }

        foreach ($plan['chosen'] as $key => $facet) {
            $sets = $answers['chosen:'.$key];

            if ($facet->kind() === FacetKind::Range) {
                $counted += $this->rangeResults([$key => $facet], $sets[0] ?? []);

                continue;
            }

            $field = $this->field($facet, $schema);

            if ($field !== null) {
                $counts = self::counts($sets[1] ?? [], $field->name);
                $counted[$key] = $this->result($facet, $facet instanceof BatchCountedFacet
                    ? ($facet->splitIndexCounts([$key], $counts)[$key] ?? [])
                    : $this->own($field, $counts));
            }
        }

        $placing = null;

        if ($plan['picks']) {
            $found = array_map(static fn (array $row): int => (int) $row['id'], self::rows($answers['sample'][0] ?? []));
            $placing = $this->placement->place($query, DB::table('catalog_products')->select('id')->whereIn('id', $found === [] ? [0] : $found));
        }

        return new CatalogResult($ids, $total, FacetPlacement::arrange($query, $counted, $placing), $exact);
    }

    /**
     * What to count and how: the columns one `FACET` each answers for every facet nobody chose,
     * those facets grouped by the column and by who lays the column out, the ranges, and the chosen.
     *
     * @return array{
     *     columns: list<string>,
     *     grouped: array<string, array{column: string, facet: Facet, keys: list<string>}>,
     *     ranges: array<string, Facet>,
     *     chosen: array<string, Facet>,
     *     picks: bool,
     * }
     */
    private function plan(CatalogQuery $query, TableSchema $schema): array
    {
        $plan = ['columns' => [], 'grouped' => [], 'ranges' => [], 'chosen' => [], 'picks' => false];

        foreach ($query->count as $key) {
            $facet = $this->facets->find($key);
            $field = $facet === null ? null : $this->field($facet, $schema);

            // A key of a JSON map counts nothing but a range.
            if ($facet === null || $field === null || ($facet->kind() !== FacetKind::Range && str_contains($field->name, '.'))) {
                continue;
            }

            if (FacetPlacement::chosen($query, $key)) {
                $plan['chosen'][$key] = $facet;

                continue;
            }

            if ($facet->kind() === FacetKind::Range) {
                $plan['ranges'][$key] = $facet;

                continue;
            }

            if (! in_array($field->name, $plan['columns'], true)) {
                $plan['columns'][] = $field->name;
            }

            $group = $field->name.'|'.($facet instanceof BatchCountedFacet ? $facet::class.'|'.spl_object_id($this->facets->sourceOf($key) ?? $facet) : $key);
            $plan['grouped'][$group] ??= ['column' => $field->name, 'facet' => $facet, 'keys' => []];
            $plan['grouped'][$group]['keys'][] = $key;
        }

        $plan['picks'] = $query->count !== [] && $this->placement->picks($query);

        return $plan;
    }

    /**
     * @param  array<string, array{column: string, facet: Facet, keys: list<string>}>  $grouped
     * @param  array<string, array<string, int>>  $byColumn
     * @return array<string, FacetResult>
     */
    private function unchosen(array $grouped, array $byColumn, TableSchema $schema): array
    {
        $counted = [];

        foreach ($grouped as $group) {
            $facet = $group['facet'];
            $counts = $byColumn[$group['column']] ?? [];

            if ($facet instanceof BatchCountedFacet) {
                $split = $facet->splitIndexCounts($group['keys'], $counts);

                foreach ($group['keys'] as $key) {
                    $one = $this->facets->find($key);

                    if ($one !== null) {
                        $counted[$key] = $this->result($one, $split[$key] ?? []);
                    }
                }

                continue;
            }

            $field = $this->field($facet, $schema);

            if ($field !== null) {
                $counted[$facet->key()] = $this->result($facet, $this->own($field, $counts));
            }
        }

        return $counted;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function result(Facet $facet, array $counts): FacetResult
    {
        if ($facet->kind() === FacetKind::Toggle) {
            return new FacetResult($facet->key(), FacetKind::Toggle, count: (int) ($counts['1'] ?? 0));
        }

        return new FacetResult($facet->key(), $facet->kind(), counts: $counts);
    }

    /**
     * A field of one facet: an empty value is no value — a product without a brand has `0`.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function own(IndexField $field, array $counts): array
    {
        $flag = $field->type === IndexField::BOOL && ! $field->multi;
        $number = $field->type === IndexField::INT;

        return array_filter($counts, static fn (int $count, int|string $value): bool => $flag
            ? (string) $value === '1'
            : (string) $value !== '' && ! ($number && (string) $value === '0'), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * The lowest and the highest of each range, over the products every other choice leaves.
     *
     * @param  array<string, Facet>  $ranges
     */
    private function ends(string $table, CatalogQuery $query, TableSchema $schema, array $ranges, ?string $except = null): string
    {
        $select = [];

        foreach (array_values($ranges) as $index => $facet) {
            $value = $this->number($facet, $schema);

            if ($value === null) {
                continue;
            }

            $select[] = 'MIN(IF('.$value['set'].', '.$value['expression'].', 1e38)) AS lo_'.$index;
            $select[] = 'MAX(IF('.$value['set'].', '.$value['expression'].', -1e38)) AS hi_'.$index;
        }

        [$columns, $where] = $this->where($query, $schema, $except);

        return 'SELECT '.implode(', ', [...($select === [] ? ['COUNT(*) AS none'] : $select), ...$columns])
            .' FROM '.$table
            .($where === [] ? '' : ' WHERE '.implode(' AND ', $where));
    }

    /**
     * @param  array<string, Facet>  $ranges
     * @param  array<string, mixed>  $set
     * @return array<string, FacetResult>
     */
    private function rangeResults(array $ranges, array $set): array
    {
        $row = self::rows($set)[0] ?? [];
        $results = [];

        foreach (array_values($ranges) as $index => $facet) {
            $low = $row['lo_'.$index] ?? null;
            $high = $row['hi_'.$index] ?? null;

            $results[$facet->key()] = new FacetResult(
                $facet->key(),
                FacetKind::Range,
                min: is_numeric($low) && (float) $low < 1e37 ? self::exact((float) $low) : null,
                max: is_numeric($high) && (float) $high > -1e37 ? self::exact((float) $high) : null,
            );
        }

        return $results;
    }

    private function select(string $table, CatalogQuery $query, TableSchema $schema, ?string $except = null): string
    {
        [$columns, $where] = $this->where($query, $schema, $except);

        return 'SELECT '.implode(', ', ['id', ...$columns])
            .' FROM '.$table
            .($where === [] ? '' : ' WHERE '.implode(' AND ', $where));
    }

    /**
     * The conditions of the question, every choice but `$except`'s, and the expressions they need
     * in the select list — Manticore filters by a computed value only through its alias.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function where(CatalogQuery $query, TableSchema $schema, ?string $except): array
    {
        $where = [];
        $columns = [];
        $term = trim((string) $query->search);

        if ($term !== '') {
            $match = $this->match($term, $schema);
            $where[] = $match === null ? 'id = 0' : 'MATCH('.Manticore::quote($match).')';
            $code = $this->exactCode($query, $schema);

            if ($code !== null) {
                $columns[] = 'IN('.TableSchema::CODE_KEYS.', '.Manticore::quote($code).') AS '.self::EXACT;
            }
        }

        if ($query->onlyTrashed) {
            $where[] = 'is_deleted = 1';
        } else {
            $where[] = 'is_deleted = 0';

            if (! $query->withUnpublished) {
                $where[] = 'is_visible = 1';
            }
        }

        match ($query->state) {
            CatalogQuery::STATE_PUBLISHED => $where[] = 'is_published = 1',
            CatalogQuery::STATE_UNPUBLISHED => $where[] = 'is_published = 0',
            CatalogQuery::STATE_NO_CATEGORY => $where[] = 'category_id = 0',
            default => null,
        };

        $chosen = [];

        foreach ($query->scope as $key => $value) {
            $chosen[] = [$key, $value];
        }

        foreach ($query->facets as $key => $value) {
            if ($key !== $except && ! $value->isEmpty()) {
                $chosen[] = [$key, $value];
            }
        }

        foreach ($chosen as $index => [$key, $value]) {
            $facet = $this->facets->find($key);

            if ($facet === null) {
                continue;
            }

            foreach ($this->condition($facet, $value, $schema, 'f_'.$index) as $kind => $parts) {
                $kind === 'columns' ? array_push($columns, ...$parts) : array_push($where, ...$parts);
            }
        }

        return [$columns, $where];
    }

    /**
     * How one choice narrows the table.
     *
     * @return array{columns: list<string>, where: list<string>}
     */
    private function condition(Facet $facet, FacetValue $value, TableSchema $schema, string $alias): array
    {
        $field = $this->field($facet, $schema);

        // A facet the index knows nothing about matches nothing, rather than everything.
        if ($field === null) {
            return ['columns' => [], 'where' => ['id = 0']];
        }

        if ($facet->kind() === FacetKind::Range) {
            $number = $this->number($facet, $schema);

            if ($number === null) {
                return ['columns' => [], 'where' => ['id = 0']];
            }

            $where = [$number['set']];

            if ($value->min !== null) {
                $where[] = $alias.' >= '.self::float($value->min);
            }

            if ($value->max !== null) {
                $where[] = $alias.' <= '.self::float($value->max);
            }

            return ['columns' => [$number['expression'].' AS '.$alias], 'where' => $where];
        }

        if ($field->type === IndexField::BOOL && ! $field->multi) {
            return ['columns' => [], 'where' => [$field->name.' = 1']];
        }

        $values = $facet instanceof BatchCountedFacet ? $facet->indexValues($value)->values : $value->values;

        if (in_array($field->type, [IndexField::INT, IndexField::TIMESTAMP], true)) {
            $list = array_values(array_unique(array_map('intval', array_filter($values, 'is_numeric'))));

            return ['columns' => [], 'where' => [$list === [] ? 'id = 0' : $field->name.' IN ('.implode(', ', $list).')']];
        }

        $list = array_map(static fn (string $one): string => Manticore::quote($one), $values);

        return ['columns' => [], 'where' => [$list === [] ? 'id = 0' : $field->name.' IN ('.implode(', ', $list).')']];
    }

    /**
     * The number a range reads, and the condition that it is there at all: a product without a
     * price is outside every range and outside the ends.
     *
     * @return array{expression: string, set: string}|null
     */
    private function number(Facet $facet, TableSchema $schema): ?array
    {
        $name = $facet->field()->name;

        if (str_contains($name, '.')) {
            [$map, $key] = explode('.', $name, 2);
            $field = $schema->attribute($map);

            if ($field === null || ! $schema->json($field)) {
                return null;
            }

            $path = $map.'['.Manticore::quote($key).']';

            return ['expression' => 'DOUBLE('.$path.')', 'set' => $path.' IS NOT NULL'];
        }

        $field = $schema->attribute($name);

        if ($field === null || $field->multi || $schema->json($field)) {
            return null;
        }

        return ['expression' => $name, 'set' => $schema->flagged($field) ? $name.'__set = 1' : 'id > 0'];
    }

    /** The attribute a facet lies in, as this table has it; a key of a JSON map is its own name. */
    private function field(Facet $facet, TableSchema $schema): ?IndexField
    {
        $field = $facet->field();

        if (str_contains($field->name, '.')) {
            $map = $schema->attribute(explode('.', $field->name, 2)[0]);

            return $map !== null && $schema->json($map) ? $field : null;
        }

        return $schema->attribute($field->name);
    }

    /**
     * The sort's steps in this table's columns; a missing price is last both ways, and the id ends
     * it. The product whose code the search is comes before them all (decision 20).
     */
    private function order(CatalogQuery $query, TableSchema $schema, string $locale): string
    {
        $first = $this->exactCode($query, $schema) === null ? [] : [self::EXACT.' DESC'];
        $steps = [];

        foreach ($this->sorts->resolve($query->sort)->indexOrder($locale) as $name => $direction) {
            $direction = $direction === 'asc' ? 'ASC' : 'DESC';
            $text = $schema->sortable($name);

            if ($text !== null) {
                $steps[] = $text.' '.$direction;

                continue;
            }

            $field = $schema->attribute($name);

            if ($field === null || $field->multi || $schema->json($field)) {
                continue;
            }

            if ($schema->flagged($field)) {
                $steps[] = $name.'__set DESC';
            }

            $steps[] = $name.' '.$direction;
        }

        // Manticore sorts by five keys at most; the id is the one that makes pages stable.
        return implode(', ', [...$first, ...array_slice($steps, 0, 4 - count($first)), 'id DESC']);
    }

    /**
     * The search in the query language (decisions 7, 19, 22): every word as written and, from
     * `min_prefix_len` letters on, as the beginning of a longer one — `чехол | чехол*`; and the
     * whole search as letters and digits, by any part of a code — `@codes_flat *at1234*`.
     * Whatever the reader typed is a word, never an operator. Null when there is no word in it.
     */
    private function match(string $term, TableSchema $schema): ?string
    {
        $words = [];

        foreach (preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (preg_match('/[\p{L}\p{N}]/u', $word) !== 1) {
                continue;
            }

            $escaped = self::escape($word);
            $words[] = mb_strlen($word) >= max(1, $schema->prefixLength()) && preg_match('/^[\p{L}\p{N}]+$/u', $word) === 1
                ? '('.$escaped.' | '.$escaped.'*)'
                : $escaped;
        }

        if ($words === []) {
            return null;
        }

        $match = implode(' ', $words);
        $flat = TableSchema::flat($term);

        if ($schema->hasCodes() && $schema->infixLength() > 0 && mb_strlen($flat) >= $schema->infixLength()) {
            $match = '('.$match.') | (@'.TableSchema::CODES_FLAT.' *'.$flat.'*)';
        }

        return $match;
    }

    /** The search as a whole code, flat — what a product's code must be to come first; or null. */
    private function exactCode(CatalogQuery $query, TableSchema $schema): ?string
    {
        $flat = TableSchema::flat(trim((string) $query->search));

        return $flat === '' || ! $schema->hasCodes() ? null : $flat;
    }

    private function localeOf(CatalogQuery $query): string
    {
        return in_array($query->locale, $this->locales->codes(), true) ? $query->locale : $this->locales->defaultCode();
    }

    private function weights(TableSchema $schema): string
    {
        $own = max(1, (int) $this->config->get('webx-catalog-manticore.weights.own', 10));
        $other = max(1, (int) $this->config->get('webx-catalog-manticore.weights.other', 3));
        $weights = array_map(static fn (string $column): string => $column.'='.$own, $schema->textColumns());

        if ($schema->hasOther()) {
            $weights[] = TableSchema::OTHER.'='.$other;
        }

        // Not `expand_keywords`: with the table's infix it would look for `*word*` in the names too,
        // and only a code is searched by a part (decision 22) — {@see match()} writes the stars.
        return $weights === [] ? '' : ', field_weights=('.implode(', ', $weights).')';
    }

    /**
     * Where the documents of one language go: the live table, or the one a rebuild fills — and
     * both while another process rebuilds, so that nothing saved meanwhile is lost in the swap.
     *
     * @return list<string>
     */
    private function targets(string $locale, bool $live = false): array
    {
        $table = $this->server->table($locale);
        $next = $this->server->table($locale, next: true);

        if ($this->rebuilding && ! $live) {
            return [$next];
        }

        return $this->rebuilding || $this->besideRebuild ? [$table, $next] : [$table];
    }

    /**
     * The schema a language's live table is asked and written by: the one the contributors
     * write now, or — while the table is of an older one, until a person rebuilds it
     * (decision 9) — that one narrowed to what the table has. Manticore answers a column it
     * does not know with an error, not with nothing; a narrowed question answers a new facet
     * with nothing and the search without what the table lacks (§6 of the spec).
     *
     * The live table's columns are asked once a minute, not on every question.
     */
    private function reading(string $locale): TableSchema
    {
        $schema = $this->schema($locale);
        $table = $this->server->table($locale);
        $live = $this->cache->get(self::LIVE.$table);

        if (! is_array($live)) {
            $live = $this->describe([$table])[$table] ?? null;

            // A table that is not there yet is made by the next `prepare()`: ask it as it will be.
            if ($live === null) {
                return $schema;
            }

            $this->cache->put(self::LIVE.$table, $live, $this->liveFor());
        }

        /** @var array{columns: array<string, array{type: string, props: string}>, settings: array<string, string>} $live */
        return $schema->differs($live['columns'], $live['settings']) === null
            ? $schema
            : $schema->within($live['columns'], $live['settings']);
    }

    /**
     * What the server says of each table: its columns, its settings and, with `$count`, its
     * documents. A table that is not there is left out.
     *
     * @param  list<string>  $tables
     * @return array<string, array{columns: array<string, array{type: string, props: string}>, settings: array<string, string>, documents: int|null}>
     */
    private function describe(array $tables, bool $count = false): array
    {
        if ($tables === []) {
            return [];
        }

        $step = $count ? 3 : 2;
        $queries = [];

        foreach ($tables as $table) {
            $queries[] = 'DESCRIBE '.$table;
            $queries[] = 'SHOW TABLE '.$table.' SETTINGS';

            if ($count) {
                $queries[] = 'SELECT COUNT(*) AS documents FROM '.$table;
            }
        }

        try {
            $answers = $this->server->sqlMany($queries);
        } catch (ManticoreError) {
            // One of them is not there: ask one by one which.
            if (count($tables) === 1) {
                return [];
            }

            return array_merge(...array_map(fn (string $table): array => $this->describe([$table], $count), $tables));
        }

        $described = [];

        foreach ($tables as $index => $table) {
            $columns = [];

            foreach (self::rows($answers[$index * $step][0] ?? []) as $row) {
                $columns[(string) $row['Field']] = ['type' => (string) $row['Type'], 'props' => (string) $row['Properties']];
            }

            $described[$table] = [
                'columns' => $columns,
                'settings' => self::settings($answers[$index * $step + 1][0] ?? []),
                'documents' => $count ? (int) (self::rows($answers[$index * $step + 2][0] ?? [])[0]['documents'] ?? 0) : null,
            ];
        }

        return $described;
    }

    /** The live tables asked again at the next question: they were just made or swapped. */
    private function forgetLive(): void
    {
        foreach ($this->locales->codes() as $locale) {
            $this->cache->forget(self::LIVE.$this->server->table($locale));
        }
    }

    private function liveFor(): int
    {
        return max(1, (int) $this->config->get('webx-catalog-manticore.schema_for', 60));
    }

    private function schema(string $locale): TableSchema
    {
        return $this->schemas[$locale] ??= new TableSchema(
            $this->fields ??= $this->documents->schema($this->locales->codes()),
            $locale,
            $this->locales->codes(),
            $this->locales->defaultCode(),
            array_filter((array) $this->config->get('webx-catalog-manticore.morphology', []), 'is_string'),
            max(0, (int) $this->config->get('webx-catalog-manticore.min_prefix_len', 3)),
            max(0, (int) $this->config->get('webx-catalog-manticore.min_infix_len', 3)),
        );
    }

    /**
     * The tables of this prefix on the server.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        $prefix = $this->server->prefix().'_catalog_products_';
        $tables = [];

        foreach (self::rows($this->server->sql('SHOW TABLES')[0] ?? []) as $row) {
            $name = (string) ($row['Table'] ?? $row['Index'] ?? '');

            if (str_starts_with($name, $prefix)) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /** Whether the catalogue is small enough for the database to carry it alone (decision 11). */
    private function small(): bool
    {
        $live = $this->cache->remember(
            'webx-catalog-manticore.live-products',
            max(1, (int) $this->config->get('webx-catalog-manticore.down_for', 30)),
            static fn (): int => Product::query()->count(),
        );

        return (int) $live <= (int) $this->config->get('webx-catalog.sql_engine_limit', 2000);
    }

    /**
     * @param  array<string, mixed>  $set
     * @return list<array<string, mixed>>
     */
    private static function rows(array $set): array
    {
        $rows = $set['data'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $set
     * @return array<string, int>
     */
    private static function counts(array $set, string $column): array
    {
        $counts = [];

        foreach (self::rows($set) as $row) {
            $value = $row[$column] ?? null;

            if (is_scalar($value)) {
                $counts[(string) (is_bool($value) ? (int) $value : $value)] = (int) ($row['count(*)'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $set
     * @return array<string, string>
     */
    private static function settings(array $set): array
    {
        $settings = [];

        foreach (self::rows($set) as $row) {
            foreach (explode("\n", (string) ($row['Value'] ?? '')) as $line) {
                if (str_contains($line, '=')) {
                    [$name, $value] = explode('=', $line, 2);
                    $settings[trim($name)] = trim($value);
                }
            }
        }

        return $settings;
    }

    /** Words as words: everything the query language reads as an operator is escaped. */
    private static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\()|\-!@~"&\/^$=<>\[\]*?:\'%])/u', '\\\\$1', $text);
    }

    private static function float(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') ?: '0';
    }

    /** A 32-bit float as the number it was written as: 99.99, not 99.989998. */
    private static function exact(float $value): float
    {
        return (float) sprintf('%.7g', $value);
    }
}
