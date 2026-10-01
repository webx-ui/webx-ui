<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Catalog\Exchange\DescribesCell;
use WebxUi\Catalog\Exchange\ExchangeColumns;
use WebxUi\Catalog\Exchange\ExchangeFiles;
use WebxUi\Catalog\Exchange\ExchangeFormats;
use WebxUi\Catalog\Exchange\ExchangeProfile;
use WebxUi\Catalog\Exchange\ExchangeRun;
use WebxUi\Catalog\Exchange\Exporter;
use WebxUi\Catalog\Exchange\Importer;
use WebxUi\Catalog\Exchange\RenamedColumn;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Tool;

/**
 * The exchange to an agent (§9 of the exchange spec): the same `Importer` and `Exporter` the panel
 * starts, so a run an agent started is a run in «Exchange» with its errors and its file.
 *
 * A file comes by address only — an agent has no upload — and an export is handed back as the
 * run's signed `file_url`, which lives as long as the file. `dry_run` of an import is the import's
 * own check: every row through the columns and the form, the errors in full, nothing written.
 *
 * The permissions are the panel's: importing is `catalog.manage`, everything else any of the three.
 */
final class ExchangeTools
{
    /** How many errors a run is answered with; the rest are a CSV file in the panel. */
    private const ERRORS = 50;

    private const ANY = ['catalog.view', 'catalog.manage', 'catalog.delete'];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function tools(): array
    {
        $run = ['type' => 'integer', 'description' => 'The run id, as catalog_import or catalog_export answered.'];
        $profile = ['type' => 'integer', 'description' => 'A profile id from catalog_exchange_profiles.'];

        return [
            Tool::read(
                'exchange_columns',
                'The columns an exchange file may have, the ones you may use: the header code, its words, whether '
                .'"<code>@<locale>" columns exist and for which languages, and how a cell reads. The same list and rules '
                .'as catalog://exchange.',
                fn (array $arguments, ?Authenticatable $user = null): array => ['columns' => $this->columns($this->can($user))],
                permission: self::ANY,
            ),

            Tool::mutating(
                'import',
                'Import a CSV or XLSX file from an http(s) address into the catalogue: the rows are matched to products by the '
                .'key column (sku unless options say otherwise), created or updated through the product form. Give a profile, '
                .'or a mapping with options; with neither, the headers are matched to column codes and labels the way the '
                .'panel suggests. Read catalog://exchange before building a file. Answers the run: a small file is done at '
                .'once, a large one runs in the background — look at it with catalog_exchange_run. With dry_run the run '
                .'checks every row and writes nothing; its errors are the ones the real import would have.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->import($arguments, $user)),
                ['properties' => [
                    'url' => ['type' => 'string', 'description' => 'http(s) address of the file; .csv or .xlsx.'],
                    'profile_id' => $profile,
                    'mapping' => ['type' => 'object', 'description' => 'Header of the file → column code ("Price" → "price", "Name DE" → "name@de"); null leaves a header out.'],
                    'options' => ['type' => 'object', 'description' => 'key (sku, external_id, barcode, id), mode (upsert, update, create), empty_clears, '
                        .'absent (keep, unpublish), absent_scope (categories, all), create_missing, images (append, replace), encoding and '
                        .'delimiter of a CSV, format (csv, xlsx) when the address does not end in one. Over the profile\'s own when both '
                        .'are given.'],
                ], 'required' => ['url']],
            ),

            Tool::read(
                'export',
                'Export products to a file: a filter like catalog_products_list\'s or ids, and the columns of a profile or a list '
                .'of codes (every column when neither). Answers the run; when it is done, its file_url is a link to the file '
                .'that works without signing in, for exchange.keep_hours. A large export runs in the background — look again '
                .'with catalog_exchange_run.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->export($arguments, $user)),
                ['properties' => [
                    'filter' => ['type' => 'object', 'description' => '{ "q", "state", "facets" } as catalog_products_list takes them; the whole catalogue when neither filter nor ids.'],
                    'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'deleted' => ['type' => 'boolean', 'description' => 'The products in «Deleted» rather than the live ones.'],
                    'profile_id' => $profile,
                    'columns' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Column codes in order, "name@de" for a language.'],
                    'format' => ['type' => 'string', 'description' => 'csv or xlsx; the profile\'s, else xlsx.'],
                ]],
                permission: self::ANY,
            ),

            Tool::read(
                'exchange_run',
                'One run of the exchange: its status (queued, running, done, stopped, failed), progress, the counts — created, '
                .'updated, skipped, failed, absent — the first '.self::ERRORS.' errors (row, column, value, reason) with how '
                .'many there are, and for a finished export the file_url.',
                fn (array $arguments): array => $this->run($arguments),
                ['properties' => ['run' => $run], 'required' => ['run']],
                permission: self::ANY,
            ),

            Tool::read(
                'exchange_profiles',
                'The saved profiles of the exchange: name, direction, format, options and mapping (import: header → code; '
                .'export: the codes in order), and the last run.',
                fn (array $arguments): array => ['profiles' => ExchangeProfile::query()
                    ->when(in_array($arguments['direction'] ?? null, [ExchangeProfile::IMPORT, ExchangeProfile::EXPORT], true), static fn ($query) => $query->where('direction', $arguments['direction']))
                    ->orderBy('name')->get()
                    ->map(static fn (ExchangeProfile $one): array => $one->toResponse())->values()->all()],
                ['properties' => ['direction' => ['type' => 'string', 'enum' => [ExchangeProfile::IMPORT, ExchangeProfile::EXPORT]]]],
                permission: self::ANY,
            ),
        ];
    }

    public function resource(): McpResource
    {
        return new McpResource(
            'catalog://exchange',
            'Exchange files',
            'How a CSV or XLSX file for catalog_import is built: the header, every column with how its cell reads — the '
            .'core\'s and the satellites\' — the key, the modes and the options, what an empty cell means.',
            fn (): array => $this->rules(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $config = $this->container->make('config');

        return [
            'file' => [
                'formats' => $this->container->make(ExchangeFormats::class)->keys(),
                'header' => 'The first row: column codes ("price", "name@de") or their labels, case aside. A header that matches nothing is not imported.',
                'csv' => 'UTF-8 (a BOM is fine), else read as '.$config->get('webx-catalog.exchange.csv_fallback_encoding', 'Windows-1252')
                    .'; the separator is guessed from the first row among "," ";" and a tab. Quote cells that hold the separator.',
                'rows' => 'One product a row. Empty rows are skipped but counted, so "row 12" in an error is the row a spreadsheet shows.',
                'max_mb' => intdiv($this->container->make(ExchangeFiles::class)->maxBytes(), 1024 * 1024),
                'languages' => 'A translated column is the default language; "<code>@<locale>" is the translation, e.g. name@de.',
            ],
            'columns' => $this->columns(null),
            'options' => [
                'key' => 'How a row finds its product: sku (default), external_id, barcode or id. The key column must be in the file. A product in «Deleted» found by the key is an error of the row, not a duplicate.',
                'mode' => 'upsert (default) creates and updates; update skips rows of new products; create skips rows of existing ones. A skipped row is counted, not an error.',
                'empty_clears' => 'false (default): an empty cell leaves the field as it is; true: it clears it. A column left out of the file or the mapping is never touched.',
                'create_missing' => 'false (default): an unknown category, brand, label or property value is an error of the row; true: it is created. Stock statuses are never created.',
                'absent' => 'keep (default) or unpublish: products the file does not name are taken off the site — only in the categories the file names (absent_scope: categories) or in the whole catalogue (all). Never deleted; not done when the run stopped.',
                'images' => 'append (default) adds the addresses the gallery does not have; replace also takes off the pictures the cell does not name.',
            ],
            'errors' => 'A bad row does not stop the others: it is rolled back alone and logged with its row, column, value and reason. More than '
                .$config->get('webx-catalog.exchange.max_errors', 1000).' errors stop the run; what was written stays.',
            'advice' => 'Run with dry_run first: the same checks, nothing written, every error listed.',
        ];
    }

    /**
     * The columns the caller may use, with how their cells read.
     *
     * @param  (callable(string): bool)|null  $can
     * @return list<array<string, mixed>>
     */
    private function columns(?callable $can): array
    {
        $locales = $this->container->make(Locales::class);
        $others = array_values(array_diff($locales->codes(), [$locales->defaultCode()]));
        $list = [];

        foreach ($this->container->make(ExchangeColumns::class)->available($can) as $key => $column) {
            $described = $column instanceof RenamedColumn ? $column->column() : $column;

            $list[] = [
                'key' => $key,
                'label' => $column->label(),
                'locales' => $column->localized() ? $others : [],
                'cell' => $described instanceof DescribesCell ? $described->cellFormat() : 'Text as it is.',
            ];
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function import(array $arguments, ?Authenticatable $user): array
    {
        $url = is_string($arguments['url'] ?? null) ? trim($arguments['url']) : '';
        $profile = $this->profile($arguments['profile_id'] ?? null, ExchangeProfile::IMPORT);
        $given = is_array($arguments['options'] ?? null) ? $arguments['options'] : [];
        $options = [...($profile->options ?? []), ...($profile === null ? [] : ['format' => $profile->format]), ...$given];
        $can = $this->can($user);

        $mapping = match (true) {
            is_array($arguments['mapping'] ?? null) => $arguments['mapping'],
            $profile !== null => (array) $profile->mapping,
            default => $this->suggested($url, $options, $can),
        };

        $run = $this->container->make(Importer::class)->start(
            ['url' => $url],
            $mapping,
            $options,
            (bool) ($arguments[Tool::DRY_RUN] ?? false),
            $profile?->id,
            $user,
            $can,
        );

        return $this->answer($run);
    }

    /**
     * The mapping the panel would have suggested: the file is read once here for its header.
     *
     * @param  array<string, mixed>  $options
     * @param  (callable(string): bool)|null  $can
     * @return array<string, string|null>
     */
    private function suggested(string $url, array $options, ?callable $can): array
    {
        if (preg_match('#^https?://\S+$#i', $url) !== 1) {
            throw new ToolFailure('url is an http(s) address.');
        }

        $files = $this->container->make(ExchangeFiles::class);

        try {
            $temporary = $files->download($url);
        } catch (Throwable $failure) {
            throw new ToolFailure('The file could not be fetched: '.$failure->getMessage());
        }

        try {
            $given = array_filter(array_intersect_key($options, array_flip(['format', 'encoding', 'delimiter'])), static fn (mixed $one): bool => is_string($one) && $one !== '');

            return $this->container->make(Importer::class)->inspect($temporary, basename((string) parse_url($url, PHP_URL_PATH)), $given, $can)['mapping'];
        } finally {
            @unlink($temporary);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function export(array $arguments, ?Authenticatable $user): array
    {
        $profile = $this->profile($arguments['profile_id'] ?? null, ExchangeProfile::EXPORT);
        $selection = match (true) {
            is_array($arguments['ids'] ?? null) => ['ids' => $arguments['ids']],
            is_array($arguments['filter'] ?? null) => ['query' => $arguments['filter']],
            default => ['query' => []],
        };
        $selection['trashed'] = (bool) ($arguments['deleted'] ?? false);

        /** @var list<string> $codes */
        $codes = array_values(array_map('strval', is_array($arguments['columns'] ?? null) ? $arguments['columns'] : (array) ($profile->mapping ?? [])));
        $format = is_string($arguments['format'] ?? null) ? $arguments['format'] : ($profile->format ?? 'xlsx');

        $run = $this->container->make(Exporter::class)->start($selection, $codes, $format, $profile?->id, $user, $this->can($user));

        return $this->answer($run);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function run(array $arguments): array
    {
        $id = $arguments['run'] ?? null;
        $id = is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : throw new ToolFailure('run is an id.');

        return $this->answer(ExchangeRun::query()->find($id) ?? throw new ToolFailure('There is no such run.'));
    }

    /**
     * The run, and its first errors with how many there are.
     *
     * @return array<string, mixed>
     */
    private function answer(ExchangeRun $run): array
    {
        $errors = DB::table('catalog_exchange_errors')->where('run_id', $run->id);

        return [
            'run' => $run->toResponse(),
            'errors_total' => (clone $errors)->count(),
            'errors' => $errors->orderBy('row')->orderBy('id')->limit(self::ERRORS)->get(['row', 'column', 'value', 'message'])
                ->map(static fn (object $error): array => (array) $error)->all(),
        ];
    }

    private function profile(mixed $id, string $direction): ?ExchangeProfile
    {
        if ($id === null) {
            return null;
        }

        return ExchangeProfile::query()->where('direction', $direction)->find((int) $id)
            ?? throw new ToolFailure("There is no {$direction} profile [{$id}]; catalog_exchange_profiles lists them.");
    }

    /**
     * Nobody to ask on the local stdio server: everything, as the console does.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user === null ? null : static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }

    /**
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(Closure $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (AccessDeniedHttpException $denied) {
            throw new ToolFailure($denied->getMessage());
        }
    }
}
