<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\Journal;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Exchange\Columns\ImagesColumn;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\ProductForm;
use WebxUi\Localization\Locales;

/**
 * An import (§4 of the exchange spec): the file's header matched to columns, the rows read in
 * chunks, each row turned into what the editor would send and saved by the form, the totals, and
 * the products that were not in the file.
 *
 * A chunk is one transaction and one mark for the engine; a row is a savepoint inside it, so a
 * bad row is an error beside its number and its neighbours are written anyway. The run keeps how
 * many rows of the file are behind its last committed chunk: a job that dies in the middle of a
 * chunk leaves that chunk uncommitted, and the next attempt starts it again from its first row —
 * nothing is written twice. A check (`dry_run`) is the same run with every chunk rolled back.
 *
 * The file is read once per job rather than once per chunk: a job does chunks until
 * `exchange.job_seconds` have passed and leaves the rest to the next, so that the 85 thousand
 * rows of an XLSX are not unzipped four hundred times.
 */
final class Importer
{
    public const UPSERT = 'upsert';

    public const UPDATE = 'update';

    public const CREATE = 'create';

    public const KEYS = ['sku', 'external_id', 'barcode', 'id'];

    public function __construct(
        private readonly ExchangeColumns $columns,
        private readonly ExchangeFormats $formats,
        private readonly ExchangeFiles $files,
        private readonly ProductForm $form,
        private readonly Catalog $catalog,
        private readonly Journal $journal,
        private readonly Config $config,
        private readonly Container $container,
        private readonly Locales $locales,
    ) {}

    /**
     * What a file looks like before anything is decided: its format, how it is read, the header,
     * five rows under it, and the columns its headers seem to be.
     *
     * @param  array<string, mixed>  $given  a CSV's encoding and separator, when somebody knows better
     * @param  (callable(string): bool)|null  $can
     * @return array{format: string, options: array<string, mixed>, header: list<string>, sample: list<list<string>>, mapping: array<string, string|null>}
     *
     * @throws ValidationException
     */
    public function inspect(string $path, string $name, array $given = [], ?callable $can = null): array
    {
        $format = $this->format($name, $given['format'] ?? null);
        $options = $format->options($path, $given);
        $header = null;
        $sample = [];

        foreach ($format->read($path, $options) as $cells) {
            if ($header === null) {
                $header = array_map(trim(...), $cells);

                continue;
            }

            $sample[] = $cells;

            if (count($sample) >= 5) {
                break;
            }
        }

        $header ??= [];

        return [
            'format' => $format->key(),
            'options' => $options,
            'header' => $header,
            'sample' => $sample,
            'mapping' => $this->suggest($header, $can),
        ];
    }

    /**
     * The column each header seems to be: its code first — `name`, `name@de` — then its words,
     * without regard to case. What nothing matches is not imported.
     *
     * @param  list<string>  $header
     * @param  (callable(string): bool)|null  $can
     * @return array<string, string|null>
     */
    public function suggest(array $header, ?callable $can = null): array
    {
        $columns = $this->columns->available($can);
        $labels = [];

        foreach ($columns as $key => $column) {
            $labels[mb_strtolower($column->label())] ??= $key;
        }

        $mapping = [];
        $taken = [];

        foreach ($header as $cell) {
            $cell = trim($cell);

            if ($cell === '' || array_key_exists($cell, $mapping)) {
                continue;
            }

            $lower = mb_strtolower($cell);
            [$key, $locale] = array_pad(explode('@', $lower, 2), 2, '');
            $code = null;

            if (isset($columns[$key]) && ($locale === '' || ($columns[$key]->localized() && $this->locales->has($locale)))) {
                $code = $lower;
            } elseif (isset($labels[$lower])) {
                $code = $labels[$lower];
            }

            $mapping[$cell] = $code !== null && ! isset($taken[$code]) ? $code : null;

            if ($code !== null) {
                $taken[$code] = true;
            }
        }

        return $mapping;
    }

    /**
     * Start an import: checked, written as a run, and either done at once — a file of
     * `exchange.sync_limit` rows or fewer — or handed to the queue.
     *
     * @param  array{upload_id?: string, url?: string, path?: string, name?: string}  $source
     * @param  array<string, mixed>  $mapping  the header of the file → a column code, null to leave out
     * @param  array<string, mixed>  $options
     * @param  (callable(string): bool)|null  $can  null — nobody to ask, as in a console
     *
     * @throws ValidationException
     */
    public function start(array $source, array $mapping, array $options, bool $dryRun = false, ?int $profileId = null, ?Authenticatable $admin = null, ?callable $can = null): ExchangeRun
    {
        if ($can !== null && ! $can('catalog.manage')) {
            throw new AccessDeniedHttpException((string) __('webx-catalog::exchange.errors.forbidden', ['permission' => 'catalog.manage']));
        }

        $name = $this->sourceName($source, $admin);
        $format = $this->format($name, $options['format'] ?? null);
        $options = $this->options($options);
        $mapping = $this->mapping($mapping, $options['key'], $can);
        $options['granted'] = $this->granted($can);

        [$adminId, $adminName] = $this->starter($admin);

        $run = ExchangeRun::query()->create([
            'profile_id' => $profileId,
            'direction' => ExchangeProfile::IMPORT,
            'format' => $format->key(),
            'options' => $options,
            'mapping' => $mapping,
            'dry_run' => $dryRun,
            'status' => ExchangeRun::QUEUED,
            'source' => mb_substr(isset($source['url']) ? (string) $source['url'] : $name, 0, 2048),
            'admin_id' => $adminId,
            'admin_name' => $adminName,
        ]);

        try {
            $extension = $format->extensions()[0];

            if (isset($source['upload_id'])) {
                $run->file = $this->files->claim((string) $source['upload_id'], $admin, $run->id, $extension)['file'];
            } elseif (isset($source['path'])) {
                $run->file = $this->files->adopt((string) $source['path'], $run->id, $extension);
            }
        } catch (Throwable $failure) {
            $run->delete();

            throw $failure;
        }

        if (! $dryRun) {
            // Opened now and closed with the last chunk, as a queued bulk action's is: the run is
            // in the journal from the moment it starts, and every chunk writes under it.
            $entry = $this->during($adminId, $adminName, null, fn (): ?HistoryEntry => $this->journal->run(
                Product::TYPE,
                ['direction' => 'import', 'source' => $run->source, 'format' => $run->format, 'profile_id' => $profileId, 'exchange_run_id' => $run->id],
                static fn (?HistoryEntry $entry): ?HistoryEntry => $entry,
                HistoryContext::IMPORT,
            ));
            $run->history_id = $entry?->id;
        }

        $run->save();

        if ($profileId !== null) {
            ExchangeProfile::query()->whereKey($profileId)->update(['last_run_id' => $run->id]);
        }

        if ($run->file !== null && $this->small($run, $format)) {
            while ($this->work($run->id)) {
                // A small file is one chunk; the loop is for a sync limit set above the chunk.
            }
        } else {
            $this->container->make(Dispatcher::class)->dispatch(new ProcessExchangeChunk($run->id));
        }

        return $run->refresh();
    }

    /**
     * Chunks of a queued run until the file ends, the run stops, or the job's time is up; whether
     * there is more for the next job. What the queued job calls.
     *
     * @param  float|null  $budget  seconds; null — to the end
     */
    public function work(int $runId, ?float $budget = null): bool
    {
        $run = ExchangeRun::query()->find($runId);

        if (! $run instanceof ExchangeRun || $run->finished() || ! $run->isImport()) {
            return false;
        }

        $format = $this->formats->find($run->format);

        if ($format === null) {
            // A format's package uninstalled while its run waited.
            $this->failWith($run, (string) __('webx-catalog::exchange.errors.unknown-format', ['known' => implode(', ', $this->formats->extensions())]));

            return false;
        }

        if ($run->file === null) {
            try {
                $temporary = $this->files->download((string) $run->source);
            } catch (Throwable $failure) {
                $this->failWith($run, $failure->getMessage());

                return false;
            }

            try {
                $run->forceFill(['file' => $this->files->adopt($temporary, $run->id, $format->extensions()[0])])->save();
            } finally {
                @unlink($temporary);
            }
        }

        return $this->files->local((string) $run->file, fn (string $path): bool => $this->read($run, $format, $path, $budget));
    }

    /** Mark a run the queue gave up on, so that the panel stops waiting for it. */
    public function fail(int $runId): void
    {
        $run = ExchangeRun::query()->find($runId);

        if ($run instanceof ExchangeRun && ! $run->finished()) {
            $this->finish($run, ExchangeRun::FAILED);
        }
    }

    private function read(ExchangeRun $run, ExchangeFormat $format, string $path, ?float $budget): bool
    {
        $read = $run->option('read');

        if (! is_array($read)) {
            $read = $format->options($path, array_intersect_key($run->options ?? [], array_flip(['encoding', 'delimiter'])));
            $run->forceFill(['options' => [...($run->options ?? []), 'read' => $read]])->save();
        }

        if ($run->status === ExchangeRun::QUEUED) {
            $total = -1;

            foreach ($format->read($path, $read) as $ignored) {
                $total++;
            }

            $run->forceFill(['status' => ExchangeRun::RUNNING, 'rows_total' => max(0, $total), 'started_at' => Carbon::now()])->save();
        }

        $started = microtime(true);
        $chunk = max(1, (int) $this->config->get('webx-catalog.exchange.chunk', 200));
        $context = $this->context($run);

        return $this->during($run->admin_id, $run->admin_name, $run->history_id, function () use ($run, $format, $path, $read, $budget, $started, $chunk, $context): bool {
            $map = null;
            $offset = $run->rows_done;
            $passed = 0;
            $batch = [];

            foreach ($format->read($path, $read) as $number => $cells) {
                if ($map === null) {
                    $map = $this->header($cells, $run);

                    continue;
                }

                if ($passed++ < $offset) {
                    continue;
                }

                $batch[] = self::row($number, $cells, $map);

                if (count($batch) < $chunk) {
                    continue;
                }

                if (! $this->chunk($run, $batch, $context)) {
                    return false;
                }

                $batch = [];

                if ($budget !== null && microtime(true) - $started > $budget) {
                    return true;
                }
            }

            if ($batch !== [] && ! $this->chunk($run, $batch, $context)) {
                return false;
            }

            $this->finish($run, ExchangeRun::DONE);

            return false;
        });
    }

    /**
     * One chunk: its rows under one transaction, the run's counters moved in the same one. False
     * when the run is not this job's to go on with — stopped on errors, or its chunk already done
     * by another worker.
     *
     * @param  list<ImportRow>  $rows
     */
    private function chunk(ExchangeRun $run, array $rows, ImportContext $context): bool
    {
        $result = new ChunkResult;
        $level = DB::transactionLevel();

        DB::beginTransaction();

        try {
            // Read again under the lock: a second worker with the same chunk waits here and then
            // finds the offset already past it.
            $locked = ExchangeRun::query()->lockForUpdate()->find($run->id);

            if (! $locked instanceof ExchangeRun || $locked->finished() || $locked->rows_done !== $run->rows_done) {
                DB::rollBack();

                return false;
            }

            $this->catalog->deferTouch(function () use ($rows, $run, $context, $result): void {
                $this->rows($rows, $run, $context, $result);
            });

            if ($run->dry_run) {
                // The check: everything the rows did goes, what they said stays.
                DB::rollBack();
                $context->forget();
                DB::transaction(fn () => $this->progress($run, $result));
            } else {
                $this->progress($run, $result);
                DB::commit();
            }
        } catch (Throwable $failure) {
            while (DB::transactionLevel() > $level) {
                DB::rollBack();
            }

            $context->forget();

            throw $failure;
        }

        if ($run->failed > $this->maxErrors()) {
            $this->finish($run, ExchangeRun::STOPPED);

            return false;
        }

        return true;
    }

    /**
     * @param  list<ImportRow>  $rows
     */
    private function rows(array $rows, ExchangeRun $run, ImportContext $context, ChunkResult $result): void
    {
        $key = (string) $run->option('key', 'sku');
        $found = $this->found($rows, $key);
        $columns = $this->mappedColumns($run, $context);

        foreach ($rows as $row) {
            $result->rows++;
            $context->beginRow();
            $context->row = $row->number;
            $keyValue = trim($row->cell($key));

            try {
                /** @var array{0: string, 1: Product|null} $outcome */
                $outcome = DB::transaction(function () use ($row, $run, $context, $columns, $key, $keyValue, $found): array {
                    if ($key === 'id' && $keyValue !== '' && ! ctype_digit($keyValue)) {
                        throw new RowError((string) __('webx-catalog::exchange.errors.not-an-id'), 'id', $keyValue);
                    }

                    $product = $keyValue === '' ? null : ($found[$keyValue] ?? null);

                    if ($product?->trashed()) {
                        throw new RowError((string) __('webx-catalog::exchange.errors.trashed', ['id' => $product->id]), $key, $keyValue);
                    }

                    $mode = (string) $run->option('mode', self::UPSERT);

                    if (($product === null && $mode === self::UPDATE) || ($product !== null && $mode === self::CREATE)) {
                        return ['skipped', null];
                    }

                    [$input, $writes] = $this->input($row, $columns, $run, $context);
                    $created = $product === null;

                    $product = $this->form->save($product ?? new Product, $input);

                    foreach ($writes as $code => $value) {
                        $column = $columns[$code];

                        try {
                            if ($column instanceof WritesProduct) {
                                $column->write($product, $value, $context);
                            }
                        } catch (RowError $error) {
                            throw $error->at($code, self::shown($value));
                        }
                    }

                    return [$created ? 'created' : 'updated', $product];
                });
            } catch (RowError $error) {
                $context->rowFailed();
                $result->error($row->number, $error->column, $error->value, $error->getMessage());

                continue;
            } catch (ValidationException $refused) {
                $context->rowFailed();

                $this->refusals($refused, $row, $columns, $result);

                continue;
            } catch (Throwable $failure) {
                report($failure);
                $context->rowFailed();
                $result->error($row->number, null, null, $failure->getMessage());

                continue;
            }

            [$what, $product] = $outcome;
            match ($what) {
                'created' => $result->created++,
                'updated' => $result->updated++,
                default => $result->skipped++,
            };

            if ($product instanceof Product) {
                $found[(string) ($key === 'id' ? $product->id : $product->getAttribute($key))] = $product;
                $result->seen[] = (int) $product->id;
            }
        }
    }

    /**
     * The products the chunk's keys name, deleted ones too, in one query.
     *
     * @param  list<ImportRow>  $rows
     * @return array<string, Product>
     */
    private function found(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = trim($row->cell($key));

            if ($value !== '' && ($key !== 'id' || ctype_digit($value))) {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return [];
        }

        $found = [];

        foreach (Product::withTrashed()->whereIn($key, array_values(array_unique($values)))->get() as $product) {
            $found[(string) $product->getAttribute($key)] = $product;
        }

        return $found;
    }

    /**
     * What the editor would send for this row, and what the columns that write themselves get.
     *
     * @param  array<string, ExchangeColumn>  $columns
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     *
     * @throws RowError
     */
    private function input(ImportRow $row, array $columns, ExchangeRun $run, ImportContext $context): array
    {
        $clears = (bool) $run->option('empty_clears', false);
        $input = [];
        $writes = [];

        foreach ($row->cells as $key => $byLocale) {
            $column = $columns[$key] ?? null;

            if ($column === null || ($column->field() === '' && ! $column instanceof WritesProduct)) {
                continue;
            }

            $field = $column->field();
            $entry = self::entry($column);

            foreach ($byLocale as $locale => $text) {
                $text = trim($text);

                if ($text === '') {
                    // An empty cell leaves the field alone (decision 6); erasing is asked for.
                    if (! $clears) {
                        continue;
                    }

                    $value = null;
                } else {
                    try {
                        $value = $column->parse($text, $context);
                    } catch (RowError $error) {
                        throw $error->at($locale === '' ? $key : $key.'@'.$locale, $text);
                    }
                }

                if ($column instanceof WritesProduct) {
                    $writes[$key] = $value;
                } elseif ($entry !== null) {
                    // One entry of an object several columns make between them.
                    /** @var array<string, mixed> $current */
                    $current = is_array($input[$field] ?? null) ? $input[$field] : [];

                    if ($column->localized()) {
                        $words = is_array($current[$entry] ?? null) ? $current[$entry] : [];
                        $words[$locale === '' ? $context->defaultLocale : $locale] = $value;
                        $current[$entry] = $words;
                    } else {
                        $current[$entry] = $value;
                    }

                    $input[$field] = $current;
                } elseif ($column->localized()) {
                    $current = $input[$field] ?? [];
                    $current[$locale === '' ? $context->defaultLocale : $locale] = $value;
                    $input[$field] = $current;
                } else {
                    $input[$field] = $value;
                }
            }
        }

        return [$input, $writes];
    }

    /** The entry of an object field the column fills, under whatever code it was renamed to. */
    private static function entry(ExchangeColumn $column): ?string
    {
        $own = $column instanceof RenamedColumn ? $column->column() : $column;

        return $own instanceof EntryColumn ? $own->entry() : null;
    }

    /**
     * The form's refusal, a line per field, named by the column that filled it.
     *
     * @param  array<string, ExchangeColumn>  $columns
     */
    private function refusals(ValidationException $refused, ImportRow $row, array $columns, ChunkResult $result): void
    {
        $byField = [];

        foreach ($columns as $key => $column) {
            $entry = self::entry($column);

            // `properties.values.12` is the column of property 12, not the first property column.
            if ($entry !== null) {
                $byField[$column->field().'.'.$entry] ??= $key;
            }

            $byField[$column->field()] ??= $key;
        }

        $errors = $refused->errors();

        if ($errors === []) {
            $result->error($row->number, null, null, $refused->getMessage());

            return;
        }

        foreach ($errors as $field => $messages) {
            $key = $byField[$field] ?? $byField[explode('.', (string) $field)[0]] ?? null;
            $cells = $key === null ? [] : ($row->cells[$key] ?? []);
            $value = $cells[''] ?? (reset($cells) ?: null);

            $result->error($row->number, $key ?? (string) $field, $value, (string) ($messages[0] ?? $refused->getMessage()));
        }
    }

    /**
     * Write what a chunk did into the run: the counters, the errors up to `max_errors`, and the
     * products it met when "not in the file" will need them.
     */
    private function progress(ExchangeRun $run, ChunkResult $result): void
    {
        $room = max(0, $this->maxErrors() - $run->failed);

        foreach (array_chunk(array_slice($result->errors, 0, $room), 200) as $errors) {
            DB::table('catalog_exchange_errors')->insert(array_map(static fn (array $error): array => ['run_id' => $run->id, ...$error], $errors));
        }

        if ($run->option('absent') === 'unpublish' && $result->seen !== []) {
            foreach (array_chunk(array_values(array_unique($result->seen)), 500) as $ids) {
                DB::table('catalog_exchange_run_items')->insertOrIgnore(array_map(static fn (int $id): array => ['run_id' => $run->id, 'product_id' => $id], $ids));
            }
        }

        $run->forceFill([
            'rows_done' => $run->rows_done + $result->rows,
            'created' => $run->created + $result->created,
            'updated' => $run->updated + $result->updated,
            'skipped' => $run->skipped + $result->skipped,
            'failed' => $run->failed + count($result->errors),
        ])->save();
    }

    /**
     * The end of a run: "not in the file" when it got to the end, its status, and the journal's
     * run told how it went.
     */
    private function finish(ExchangeRun $run, string $status): void
    {
        $absent = $status === ExchangeRun::DONE ? $this->absent($run) : 0;

        $run->forceFill(['status' => $status, 'absent' => $absent, 'finished_at' => Carbon::now()])->save();

        if ($run->history_id === null) {
            return;
        }

        $entry = HistoryEntry::query()->find($run->history_id);

        if ($entry !== null) {
            $entry->forceFill([
                'summary' => [
                    ...($entry->summary ?? []),
                    'rows' => $entry->rows()->count(),
                    'status' => $status,
                    'created' => $run->created,
                    'updated' => $run->updated,
                    'skipped' => $run->skipped,
                    'errors' => $run->failed,
                    'absent' => $absent,
                ],
            ])->save();
        }
    }

    /**
     * The step "not in the file" (decision 7): the published products the file did not name,
     * within the categories it did — or the whole catalogue, when the profile says so — are
     * unpublished. Never deleted. A check counts them and touches nothing.
     */
    private function absent(ExchangeRun $run): int
    {
        if ($run->option('absent') !== 'unpublish') {
            return 0;
        }

        $seen = static fn (QueryBuilder $items) => $items->select('product_id')->from('catalog_exchange_run_items')->where('run_id', $run->id);

        $query = Product::query()->where('is_published', true)->whereNotIn('id', $seen);

        if ($run->option('absent_scope', 'categories') !== 'all') {
            $categories = Product::withTrashed()->whereIn('id', $seen)->whereNotNull('category_id')->distinct()->pluck('category_id')->all();

            if ($categories === []) {
                return 0;
            }

            $query->whereIn('category_id', $categories);
        }

        /** @var list<int> $ids */
        $ids = $query->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        if ($run->dry_run || $ids === []) {
            return count($ids);
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            DB::transaction(function () use ($chunk): void {
                Product::query()->whereKey($chunk)->update(['is_published' => false, 'updated_at' => Carbon::now()]);

                foreach ($chunk as $id) {
                    $this->journal->recordFor(Product::TYPE, $id, HistoryEntry::UNPUBLISHED, [['field' => 'is_published', 'from' => true, 'to' => false]]);
                }

                $this->catalog->touch($chunk);
            });
        }

        return count($ids);
    }

    private function failWith(ExchangeRun $run, string $message): void
    {
        $result = new ChunkResult;
        $result->error(0, null, $run->source, $message);
        DB::table('catalog_exchange_errors')->insert(['run_id' => $run->id, ...$result->errors[0]]);
        $run->forceFill(['failed' => $run->failed + 1])->save();
        $this->finish($run, ExchangeRun::FAILED);
    }

    /**
     * @param  list<string>  $header
     * @return array<int, array{0: string, 1: string}> the position of a cell → its column and language
     */
    private function header(array $header, ExchangeRun $run): array
    {
        $mapping = $run->mapping ?? [];
        $default = $this->locales->defaultCode();
        $map = [];
        $taken = [];

        foreach ($header as $index => $cell) {
            $code = $mapping[trim($cell)] ?? null;

            if (! is_string($code) || isset($taken[$code])) {
                continue;
            }

            $taken[$code] = true;
            [$key, $locale] = array_pad(explode('@', $code, 2), 2, '');
            $map[$index] = [$key, $locale === $default ? '' : $locale];
        }

        return $map;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<int, array{0: string, 1: string}>  $map
     */
    private static function row(int $number, array $cells, array $map): ImportRow
    {
        $byColumn = [];

        foreach ($map as $index => [$key, $locale]) {
            $byColumn[$key][$locale] = $cells[$index] ?? '';
        }

        return new ImportRow($number, $byColumn);
    }

    /**
     * The columns the run maps, among the ones its starter was allowed.
     *
     * @return array<string, ExchangeColumn>
     */
    private function mappedColumns(ExchangeRun $run, ImportContext $context): array
    {
        $keys = [];

        foreach ((array) ($run->mapping ?? []) as $code) {
            if (is_string($code)) {
                $keys[explode('@', $code, 2)[0]] = true;
            }
        }

        return array_intersect_key($this->columns->available($context->can(...)), $keys);
    }

    private function context(ExchangeRun $run): ImportContext
    {
        $granted = array_values(array_filter((array) $run->option('granted', []), is_string(...)));

        return new ImportContext(
            createMissing: (bool) $run->option('create_missing', false),
            dryRun: $run->dry_run,
            defaultLocale: $this->locales->defaultCode(),
            can: static fn (string $permission): bool => in_array('*', $granted, true) || in_array($permission, $granted, true),
            options: $run->options ?? [],
            runId: $run->id,
        );
    }

    /**
     * The settings of a run with the defaults filled in and the nonsense refused.
     *
     * @param  array<string, mixed>  $given
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function options(array $given): array
    {
        $choices = [
            'key' => self::KEYS,
            'mode' => [self::UPSERT, self::UPDATE, self::CREATE],
            'absent' => ['keep', 'unpublish'],
            'absent_scope' => ['categories', 'all'],
            'images' => [ImagesColumn::APPEND, ImagesColumn::REPLACE],
        ];

        $options = [
            'key' => 'sku',
            'mode' => self::UPSERT,
            'empty_clears' => false,
            'absent' => 'keep',
            'absent_scope' => 'categories',
            'create_missing' => false,
            'images' => ImagesColumn::APPEND,
        ];

        foreach ($choices as $name => $allowed) {
            if (! isset($given[$name])) {
                continue;
            }

            if (! in_array($given[$name], $allowed, true)) {
                throw ExchangeFiles::refused('options.'.$name, 'option', ['name' => $name, 'allowed' => implode(', ', $allowed)]);
            }

            $options[$name] = $given[$name];
        }

        foreach (['empty_clears', 'create_missing'] as $flag) {
            if (isset($given[$flag])) {
                $options[$flag] = filter_var($given[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach (['encoding', 'delimiter'] as $read) {
            if (is_string($given[$read] ?? null) && $given[$read] !== '') {
                $options[$read] = $given[$read];
            }
        }

        return $options;
    }

    /**
     * The mapping with every code checked against the columns the starter may use, and the key
     * among them.
     *
     * @param  array<string, mixed>  $mapping
     * @param  (callable(string): bool)|null  $can
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    public function mapping(array $mapping, string $key, ?callable $can): array
    {
        $columns = $this->columns->available($can);
        $checked = [];

        foreach ($mapping as $header => $code) {
            if ($code === null || $code === '') {
                continue;
            }

            [$column, $locale] = array_pad(explode('@', is_string($code) ? $code : '', 2), 2, '');
            $known = isset($columns[$column]) && ($locale === '' || ($columns[$column]->localized() && $this->locales->has($locale)));

            if (! $known || in_array($code, $checked, true)) {
                throw ExchangeFiles::refused('mapping', $known ? 'mapping-twice' : 'mapping-unknown', ['code' => is_scalar($code) ? (string) $code : '?']);
            }

            $checked[trim((string) $header)] = (string) $code;
        }

        if (! in_array($key, $checked, true)) {
            throw ExchangeFiles::refused('options.key', 'key-not-mapped', ['key' => $key]);
        }

        return $checked;
    }

    /**
     * The permissions the starter has, as they are now: a queued chunk has nobody to ask.
     *
     * @param  (callable(string): bool)|null  $can
     * @return list<string>
     */
    private function granted(?callable $can): array
    {
        if ($can === null) {
            return ['*'];
        }

        // Every permission anybody could ask about: the modules' own, and whatever a node of the
        // product form stands behind.
        $known = [];

        foreach ($this->container->make(ModuleRegistry::class)->all() as $module) {
            array_push($known, ...$module->permissions());
        }

        array_push($known, ...self::screenPermissions($this->container->make(ScreenRegistry::class)->tree(Product::SCREEN)));

        return array_values(array_filter(array_unique($known), $can));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<string>
     */
    private static function screenPermissions(array $nodes): array
    {
        $found = [];

        foreach ($nodes as $node) {
            if (is_string($node['can'] ?? null) && $node['can'] !== '') {
                $found[] = $node['can'];
            }

            if (is_array($node['children'] ?? null)) {
                array_push($found, ...self::screenPermissions(array_values($node['children'])));
            }
        }

        return $found;
    }

    /**
     * @param  array{upload_id?: string, url?: string, path?: string, name?: string}  $source
     *
     * @throws ValidationException
     */
    private function sourceName(array $source, ?Authenticatable $admin): string
    {
        if (isset($source['upload_id'])) {
            return $this->files->peek((string) $source['upload_id'], $admin)['name'];
        }

        if (isset($source['url'])) {
            $url = (string) $source['url'];

            if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                throw ExchangeFiles::refused('url', 'not-a-url', ['url' => $url]);
            }

            return basename((string) parse_url($url, PHP_URL_PATH));
        }

        if (isset($source['path'])) {
            return (string) ($source['name'] ?? basename((string) $source['path']));
        }

        throw ExchangeFiles::refused('upload_id', 'no-source');
    }

    /**
     * @throws ValidationException
     */
    private function format(string $name, mixed $key): ExchangeFormat
    {
        $format = is_string($key) && $key !== '' ? $this->formats->find($key) : $this->formats->forName($name);

        return $format ?? throw ExchangeFiles::refused('format', 'unknown-format', ['known' => implode(', ', $this->formats->extensions())]);
    }

    /** Whether the file is small enough to be done inside the request. */
    private function small(ExchangeRun $run, ExchangeFormat $format): bool
    {
        $limit = max(0, (int) $this->config->get('webx-catalog.exchange.sync_limit', 50));

        return $this->files->local((string) $run->file, static function (string $path) use ($run, $format, $limit): bool {
            $rows = -1;

            foreach ($format->read($path, $format->options($path, array_intersect_key($run->options ?? [], array_flip(['encoding', 'delimiter'])))) as $ignored) {
                if (++$rows > $limit) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function starter(?Authenticatable $admin): array
    {
        $context = $this->container->make(HistoryContext::class);

        // Whoever the door says started it; the caller's own word when the door said nothing.
        return $admin === null
            ? [$context->adminId(), $context->adminName()]
            : $context->during($context->source(), $admin, $context->grantId(), static fn (): array => [$context->adminId(), $context->adminName()]);
    }

    /**
     * Do `$work` as the administrator who started the run, under its row in the journal and in
     * the source `import` — in a worker with nobody logged in, and in the request that started it.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function during(?int $adminId, string $adminName, ?int $historyId, Closure $work): mixed
    {
        $context = $this->container->make(HistoryContext::class);
        $admin = $adminId === null ? null : new GenericUser(['id' => $adminId, 'name' => $adminName]);

        return $context->during(HistoryContext::IMPORT, $admin, null, static fn (): mixed => $context->inRun($historyId, HistoryContext::IMPORT, $work));
    }

    private function maxErrors(): int
    {
        return max(1, (int) $this->config->get('webx-catalog.exchange.max_errors', 1000));
    }

    private static function shown(mixed $value): string
    {
        return match (true) {
            is_array($value) => implode(';', array_map(static fn (mixed $one): string => is_scalar($one) ? (string) $one : '', $value)),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
