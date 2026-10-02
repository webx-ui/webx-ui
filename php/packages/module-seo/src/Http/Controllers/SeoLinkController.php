<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Routing\Models\Route;
use WebxUi\Seo\Links\LinkBlocks;
use WebxUi\Seo\Links\LinkExport;
use WebxUi\Seo\Links\LinkImport;
use WebxUi\Seo\Links\LinkSpreadsheet;
use WebxUi\Seo\Links\LinkWriter;
use WebxUi\Seo\Models\SeoLinkBlock;
use WebxUi\Seo\Targets\UrlTarget;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * Interlinking in the panel (§18.4): the list of donors, a donor's block as one form, the import
 * with its preview, the export in the same flat format, and the heading set in bulk.
 *
 * Registered only while `webx-seo.links.enabled` is on — otherwise every one of these is a 404.
 */
final class SeoLinkController
{
    public function __construct(
        private readonly LinkBlocks $blocks,
        private readonly LinkWriter $writer,
        private readonly UrlTargets $targets,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:2048'],
            'broken' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $found = $this->blocks->search($request->filled('q') ? (string) $request->string('q') : null, $request->boolean('broken'));
        $perPage = (int) $request->integer('per_page', 25);
        $page = max(1, (int) $request->integer('page', 1));

        $paginator = new LengthAwarePaginator(
            $found->forPage($page, $perPage)->map(fn (SeoLinkBlock $block): array => $this->blocks->describe($block, false))->values(),
            $found->count(),
            $perPage,
            $page,
            ['path' => $request->url()],
        );

        return JsonResource::collection($paginator);
    }

    public function show(SeoLinkBlock $block): JsonResponse
    {
        return ApiResponse::data($this->blocks->describe($block->load('items')));
    }

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::data($this->write($request, null), 201);
    }

    public function update(Request $request, SeoLinkBlock $block): JsonResponse
    {
        return ApiResponse::data($this->write($request, $block));
    }

    public function destroy(SeoLinkBlock $block): JsonResponse
    {
        $block->delete();

        return ApiResponse::noContent();
    }

    /** A preview unless `dry_run` is false: the file is read and checked either way. */
    public function import(Request $request, LinkImport $import): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:csv,txt,xlsx'],
            'mode' => ['nullable', Rule::in([LinkImport::REPLACE, LinkImport::APPEND])],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());

        try {
            $rows = LinkSpreadsheet::read((string) $file?->getRealPath(), $extension);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('webx-seo::links.unreadable')]);
        }

        return ApiResponse::data($import->run(
            $rows,
            (string) $request->input('mode', LinkImport::REPLACE),
            $request->boolean('dry_run', true),
        ));
    }

    public function export(Request $request, LinkExport $export): BinaryFileResponse
    {
        $request->validate(['format' => ['nullable', Rule::in(['csv', 'xlsx'])]]);

        $format = (string) $request->input('format', 'csv');
        $path = tempnam(sys_get_temp_dir(), 'wxl').'.'.$format;

        $export->write($path, $format);

        return response()->download($path, 'interlinking.'.$format)->deleteFileAfterSend();
    }

    /**
     * One heading on many donors (§18.4): the ones ticked, or every donor under an address
     * prefix. A preview of how many unless `dry_run` is false.
     */
    public function heading(Request $request): JsonResponse
    {
        $request->validate([
            'heading' => ['nullable', 'string', 'max:255'],
            'ids' => ['nullable', 'array', 'required_without:prefix'],
            'ids.*' => ['integer'],
            'prefix' => ['nullable', 'string', 'max:2048', 'required_without:ids'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        /** @var list<int>|null $ids */
        $ids = $request->filled('ids') ? array_map('intval', (array) $request->input('ids')) : null;

        return ApiResponse::data($this->blocks->applyHeading(
            $this->blocks->selection($ids, $request->filled('prefix') ? (string) $request->string('prefix') : null),
            $request->filled('heading') ? (string) $request->string('heading') : null,
            $request->boolean('dry_run', true),
        ));
    }

    /** Addresses from the registry for the acceptor field to suggest. */
    public function addresses(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:8'],
        ]);

        $term = trim((string) $request->string('q'), "/ \t");

        $rows = Route::query()
            ->canonical()
            ->when($request->filled('locale'), fn ($query) => $query->where('locale', (string) $request->string('locale')))
            ->when($term !== '', fn ($query) => $query->where('path', 'like', '%'.addcslashes(mb_strtolower($term), '%_\\').'%'))
            ->orderByRaw('length(path)')
            ->limit(20)
            ->get();

        return ApiResponse::data($rows->map(fn (Route $route): array => [
            'url' => $this->targets->address(new UrlTarget($route->locale, '/'.$route->path, $route->entity_type, $route->entity_id)),
            'locale' => $route->locale,
            'entity_type' => $route->entity_type,
            'entity_id' => $route->entity_id,
        ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function write(Request $request, ?SeoLinkBlock $block): array
    {
        $request->validate([
            'donor' => ['required', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:8'],
            'heading' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['present', 'array'],
            'items.*.acceptor' => ['required', 'string', 'max:2048'],
            'items.*.anchor' => ['required', 'string', 'max:255'],
        ]);

        /** @var list<array<string, mixed>> $items */
        $items = array_values((array) $request->input('items', []));
        $locale = $request->filled('locale') ? (string) $request->string('locale') : null;
        $plan = $this->writer->plan((string) $request->string('donor'), $items, $locale);

        $errors = [];

        foreach ($plan->problems as $problem) {
            if ($problem['level'] === LinkWriter::ERROR) {
                $errors[$problem['field'] === 'donor' ? 'donor' : 'items.'.$problem['line'].'.'.$problem['field']][] = $problem['message'];
            }
        }

        if ($plan->donor !== null && $this->writer->blockFor($plan->donor->target, $block?->id) !== null) {
            $errors['donor'][] = (string) __('webx-seo::links.donor-taken');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $saved = $this->writer->save($plan, [
            'heading' => $request->input('heading'),
            'is_active' => $request->boolean('is_active', true),
        ], false, $block);

        return $this->blocks->describe($saved->load('items')) + [
            'warnings' => array_values(array_filter($plan->problems, static fn (array $problem): bool => $problem['level'] === LinkWriter::WARNING)),
        ];
    }
}
