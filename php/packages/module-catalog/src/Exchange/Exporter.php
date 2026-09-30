<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Generator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Catalog\Bulk\BulkSelection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\Locales;

/**
 * An export (§5 of the exchange spec): the products chosen the way a bulk action chooses them,
 * fixed when it starts; the columns of a profile or chosen on the spot; a page of products at a
 * time, each column asked for the whole page at once; the file streamed onto `exchange.disk` and
 * handed out by a signed link until the prune takes it.
 *
 * One job writes the whole file: a spreadsheet cannot be appended to by the next job, and the
 * reading is paged, so the memory stays that of one page. A job that dies starts the file again.
 */
final class Exporter
{
    public function __construct(
        private readonly ExchangeColumns $columns,
        private readonly ExchangeFormats $formats,
        private readonly ExchangeFiles $files,
        private readonly BulkSelection $selection,
        private readonly Config $config,
        private readonly Container $container,
        private readonly Locales $locales,
    ) {}

    /**
     * @param  array<string, mixed>  $selection  `{ ids }` or `{ query }`, and `trashed` for «Deleted»
     * @param  list<string>  $codes  column codes in order, `name@de` for a language; empty — every column
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function start(array $selection, array $codes, string $format, ?int $profileId = null, ?Authenticatable $admin = null, ?callable $can = null): ExchangeRun
    {
        $chosen = $this->formats->find($format) ?? throw ExchangeFiles::refused('format', 'unknown-format', ['known' => implode(', ', $this->formats->keys())]);
        $codes = $this->codes($codes, $can);
        $trashed = filter_var($selection['trashed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $ids = $this->selection->resolve($selection, $trashed, $this->locales->current());

        $context = $this->container->make(HistoryContext::class);
        [$adminId, $adminName] = $admin === null
            ? [$context->adminId(), $context->adminName()]
            : $context->during($context->source(), $admin, $context->grantId(), static fn (): array => [$context->adminId(), $context->adminName()]);

        $run = DB::transaction(function () use ($profileId, $chosen, $codes, $trashed, $ids, $adminId, $adminName, $selection): ExchangeRun {
            $run = ExchangeRun::query()->create([
                'profile_id' => $profileId,
                'direction' => ExchangeProfile::EXPORT,
                'format' => $chosen->key(),
                'options' => ['trashed' => $trashed, 'selection' => $selection],
                'mapping' => $codes,
                'status' => ExchangeRun::QUEUED,
                'rows_total' => count($ids),
                'admin_id' => $adminId,
                'admin_name' => $adminName,
            ]);

            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('catalog_exchange_run_items')->insert(array_map(
                    static fn (int $id): array => ['run_id' => $run->id, 'product_id' => $id],
                    $chunk,
                ));
            }

            return $run;
        });

        if ($profileId !== null) {
            ExchangeProfile::query()->whereKey($profileId)->update(['last_run_id' => $run->id]);
        }

        if (count($ids) <= max(0, (int) $this->config->get('webx-catalog.exchange.sync_limit', 50))) {
            $this->work($run->id);
        } else {
            // After the commit: a worker that picks the job up must find the run and its ids.
            $this->container->make(Dispatcher::class)->dispatch(new ProcessExchangeChunk($run->id));
        }

        return $run->refresh();
    }

    /** Write the whole file of a run. What the queued job calls. */
    public function work(int $runId): void
    {
        $run = ExchangeRun::query()->find($runId);

        if (! $run instanceof ExchangeRun || $run->finished() || $run->isImport()) {
            return;
        }

        $format = $this->formats->find($run->format);

        if ($format === null) {
            $run->forceFill(['status' => ExchangeRun::FAILED, 'finished_at' => Carbon::now()])->save();

            return;
        }

        $run->forceFill(['status' => ExchangeRun::RUNNING, 'rows_done' => 0, 'started_at' => Carbon::now()])->save();
        $temporary = tempnam(sys_get_temp_dir(), 'webx-export-');

        if ($temporary === false) {
            throw new RuntimeException('No room for a temporary file to export into.');
        }

        try {
            $format->write($temporary, $this->rows($run));

            $file = ExchangeFiles::DIRECTORY.'/exports/'.$run->id.'.'.$format->extensions()[0];
            $stream = fopen($temporary, 'rb');

            try {
                $this->files->disk()->writeStream($file, $stream ?: throw new RuntimeException('The export vanished before it was kept.'));
            } finally {
                is_resource($stream) && fclose($stream);
            }

            $run->forceFill(['status' => ExchangeRun::DONE, 'file' => $file, 'finished_at' => Carbon::now()])->save();
        } finally {
            @unlink($temporary);
        }
    }

    public function fail(int $runId): void
    {
        $run = ExchangeRun::query()->find($runId);

        if ($run instanceof ExchangeRun && ! $run->finished()) {
            $run->forceFill(['status' => ExchangeRun::FAILED, 'finished_at' => Carbon::now()])->save();
        }
    }

    /**
     * The header, then a row per product, a page at a time.
     *
     * @return Generator<int, list<string>>
     */
    private function rows(ExchangeRun $run): Generator
    {
        /** @var list<string> $codes */
        $codes = array_values(array_filter((array) ($run->mapping ?? []), is_string(...)));
        $columns = $this->columns->all();
        $default = $this->locales->defaultCode();
        $page = max(1, (int) $this->config->get('webx-catalog.exchange.chunk', 200));
        $trashed = (bool) $run->option('trashed', false);

        yield $codes;

        $cursor = 0;
        $done = 0;

        do {
            $ids = DB::table('catalog_exchange_run_items')
                ->where('run_id', $run->id)
                ->where('product_id', '>', $cursor)
                ->orderBy('product_id')
                ->limit($page)
                ->pluck('product_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if ($ids === []) {
                break;
            }

            $cursor = $ids[array_key_last($ids)];
            $products = ($trashed ? Product::onlyTrashed() : Product::query())->whereKey($ids)->orderBy('id')->get();
            $cells = [];

            foreach ($codes as $index => $code) {
                [$key, $locale] = array_pad(explode('@', $code, 2), 2, '');
                $column = $columns[$key] ?? null;
                $cells[$index] = $column === null ? [] : $column->export($products, $locale === '' || $locale === $default ? null : $locale);
            }

            foreach ($products as $product) {
                $row = [];

                foreach (array_keys($codes) as $index) {
                    $row[] = $cells[$index][(int) $product->id] ?? '';
                }

                yield $row;
            }

            $done += $products->count();
            $run->forceFill(['rows_done' => $done])->save();
        } while (count($ids) === $page);
    }

    /**
     * The codes checked against the columns whoever asks may read; none — every column of the
     * default language, the order they are registered in.
     *
     * @param  list<string>  $codes
     * @param  (callable(string): bool)|null  $can
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function codes(array $codes, ?callable $can): array
    {
        $available = $this->columns->available($can);

        if ($codes === []) {
            return array_keys($available);
        }

        $checked = [];

        foreach ($codes as $code) {
            [$key, $locale] = array_pad(explode('@', is_string($code) ? $code : '', 2), 2, '');
            $known = isset($available[$key]) && ($locale === '' || ($available[$key]->localized() && $this->locales->has($locale)));

            if (! $known || in_array($code, $checked, true)) {
                throw ExchangeFiles::refused('columns', $known ? 'mapping-twice' : 'mapping-unknown', ['code' => (string) $code]);
            }

            $checked[] = (string) $code;
        }

        return $checked;
    }
}
