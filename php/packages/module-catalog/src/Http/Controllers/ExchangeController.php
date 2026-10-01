<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Catalog\Exchange\ExchangeColumns;
use WebxUi\Catalog\Exchange\ExchangeFiles;
use WebxUi\Catalog\Exchange\ExchangeProfile;
use WebxUi\Catalog\Exchange\ExchangeRun;
use WebxUi\Catalog\Exchange\Exporter;
use WebxUi\Catalog\Exchange\Importer;
use WebxUi\Localization\Locales;

/**
 * The exchange of the panel (§8.2 of the exchange spec): the columns there are, a look at a file
 * before it is imported, an import, an export, and the runs with their errors and files.
 *
 * A small file is done inside the request and answered `200`; a large one is `202` with the run
 * the panel polls. Either way the answer is the run.
 */
final class ExchangeController
{
    public function __construct(
        private readonly Importer $importer,
        private readonly Exporter $exporter,
        private readonly ExchangeFiles $files,
    ) {}

    /** The columns the caller may map or export, with the languages of the translated ones. */
    public function columns(Request $request, ExchangeColumns $columns, Locales $locales): JsonResponse
    {
        $others = array_values(array_diff($locales->codes(), [$locales->defaultCode()]));
        $list = [];

        foreach ($columns->available($this->can($request)) as $key => $column) {
            $list[] = [
                'key' => $key,
                'label' => $column->label(),
                'field' => $column->field(),
                'localized' => $column->localized(),
                'locales' => $column->localized() ? $others : [],
            ];
        }

        return ApiResponse::data($list);
    }

    /** `{ upload_id | url, format?, encoding?, delimiter? }` → how the file reads, and a mapping. */
    public function inspect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => ['required_without:url', 'nullable', 'string', 'max:64'],
            'url' => ['required_without:upload_id', 'nullable', 'string', 'max:2048'],
            'format' => ['nullable', 'string', 'max:16'],
            'encoding' => ['nullable', 'string', 'max:32'],
            'delimiter' => ['nullable', 'string', 'max:2'],
        ]);

        $given = array_filter(array_intersect_key($validated, array_flip(['format', 'encoding', 'delimiter'])), static fn (mixed $one): bool => $one !== null);

        if (is_string($validated['upload_id'] ?? null)) {
            ['path' => $path, 'name' => $name] = $this->files->peek($validated['upload_id'], $request->user());

            return ApiResponse::data($this->importer->inspect($path, $name, $given, $this->can($request)));
        }

        $url = (string) $validated['url'];

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw ExchangeFiles::refused('url', 'not-a-url', ['url' => $url]);
        }

        try {
            $temporary = $this->files->download($url);
        } catch (Throwable $failure) {
            throw ValidationException::withMessages(['url' => [$failure->getMessage()]]);
        }

        try {
            return ApiResponse::data($this->importer->inspect($temporary, basename((string) parse_url($url, PHP_URL_PATH)), $given, $this->can($request)));
        } finally {
            @unlink($temporary);
        }
    }

    /** `{ upload_id | url, profile_id | mapping + options, dry_run }` → the run. */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => ['required_without:url', 'nullable', 'string', 'max:64'],
            'url' => ['required_without:upload_id', 'nullable', 'string', 'max:2048'],
            'profile_id' => ['nullable', 'integer'],
            'mapping' => ['required_without:profile_id', 'nullable', 'array'],
            'options' => ['nullable', 'array'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $profile = $this->profile($validated['profile_id'] ?? null, ExchangeProfile::IMPORT);
        $mapping = (array) ($validated['mapping'] ?? $profile->mapping ?? []);
        $options = [...($profile->options ?? []), ...($profile === null ? [] : ['format' => $profile->format]), ...(array) ($validated['options'] ?? [])];
        $source = is_string($validated['upload_id'] ?? null) ? ['upload_id' => $validated['upload_id']] : ['url' => (string) $validated['url']];

        $run = $this->importer->start($source, $mapping, $options, (bool) ($validated['dry_run'] ?? false), $profile?->id, $request->user(), $this->can($request));

        return ApiResponse::data($run->toResponse(), $run->finished() ? 200 : 202);
    }

    /** `{ selection, profile_id | columns + format }` → the run. */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'selection' => ['required', 'array'],
            'selection.ids' => ['sometimes', 'array'],
            'selection.query' => ['sometimes', 'array'],
            'selection.trashed' => ['sometimes', 'boolean'],
            'profile_id' => ['nullable', 'integer'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:80'],
            'format' => ['nullable', 'string', 'max:16'],
        ]);

        $profile = $this->profile($validated['profile_id'] ?? null, ExchangeProfile::EXPORT);
        /** @var list<string> $codes */
        $codes = array_values((array) ($validated['columns'] ?? $profile->mapping ?? []));
        $format = (string) ($validated['format'] ?? $profile->format ?? 'xlsx');

        $run = $this->exporter->start((array) $validated['selection'], $codes, $format, $profile?->id, $request->user(), $this->can($request));

        return ApiResponse::data($run->toResponse(), $run->finished() ? 200 : 202);
    }

    public function runs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['nullable', 'in:import,export'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ExchangeRun::query()->orderByDesc('id');

        if (isset($validated['direction'])) {
            $query->where('direction', $validated['direction']);
        }

        return new JsonResponse($query->paginate((int) ($validated['per_page'] ?? 20))->through(static fn (ExchangeRun $run): array => $run->toResponse())->toArray());
    }

    public function show(int $run): JsonResponse
    {
        return ApiResponse::data($this->run($run)->toResponse());
    }

    /** A page of the errors, or all of them as a CSV file with `?format=csv`. */
    public function errors(Request $request, int $run): JsonResponse|StreamedResponse
    {
        $found = $this->run($run);
        $query = DB::table('catalog_exchange_errors')->where('run_id', $found->id)->orderBy('row')->orderBy('id');

        if ($request->query('format') !== 'csv') {
            $perPage = min(500, max(1, (int) $request->query('per_page', '100')));

            return new JsonResponse($query->paginate($perPage, ['row', 'column', 'value', 'message'])->toArray());
        }

        return new StreamedResponse(static function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['row', 'column', 'value', 'message'], ',', '"', '');

            foreach ($query->lazyById(500, 'id') as $error) {
                fputcsv($out, [(string) $error->row, (string) $error->column, (string) $error->value, (string) $error->message], ',', '"', '');
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="exchange-'.$found->id.'-errors.csv"',
        ]);
    }

    /** The finished export, for the panel; the signed link of the run serves the same file. */
    public function file(int $run): Response
    {
        return $this->download($this->run($run));
    }

    /** The same file by the run's signed link, without a session behind it. */
    public function signed(int $run): Response
    {
        return $this->download($this->run($run));
    }

    private function download(ExchangeRun $run): Response
    {
        $disk = $this->files->disk();

        if ($run->isImport() || $run->file === null || ! $disk->exists($run->file)) {
            throw new NotFoundHttpException;
        }

        $name = 'catalog-export-'.$run->id.'.'.pathinfo($run->file, PATHINFO_EXTENSION);
        $stream = $disk->readStream($run->file);

        return new StreamedResponse(static function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    private function run(int $id): ExchangeRun
    {
        return ExchangeRun::query()->find($id) ?? throw new NotFoundHttpException;
    }

    private function profile(mixed $id, string $direction): ?ExchangeProfile
    {
        if ($id === null) {
            return null;
        }

        $profile = ExchangeProfile::query()->where('direction', $direction)->find((int) $id);

        return $profile ?? throw ExchangeFiles::refused('profile_id', 'profile-missing');
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
