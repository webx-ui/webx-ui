<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Seo\Http\Requests\SeoUrlRequest;
use WebxUi\Seo\Http\Resources\SeoUrlResource;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * The rules, as a table and a form.
 *
 * The listing is a paginator handed over as it is — the table in the core reads Laravel's own
 * shape — sorted the way the matcher tries them, so that reading the screen top to bottom is
 * reading the order the site will use.
 */
final class SeoUrlController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:2048'],
            'match_type' => ['nullable', Rule::in(UrlMatcher::types())],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rules = SeoUrl::query()
            ->when($request->filled('q'), fn ($query) => $query->where(
                'pattern',
                'like',
                '%'.addcslashes((string) $request->string('q'), '%_\\').'%',
            ))
            ->when($request->filled('match_type'), fn ($query) => $query->where('match_type', $request->string('match_type')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            // The order the site tries them in: the narrow rules first, then by priority.
            ->orderByRaw("case match_type when 'exact' then 0 when 'mask' then 1 else 2 end")
            ->orderByDesc('priority')
            ->orderBy('id')
            ->paginate((int) $request->integer('per_page', 25));

        return SeoUrlResource::collection($rules);
    }

    public function show(SeoUrl $url): JsonResponse
    {
        return ApiResponse::data(new SeoUrlResource($url));
    }

    public function store(SeoUrlRequest $request): JsonResponse
    {
        $rule = SeoUrl::query()->create($request->values());

        return ApiResponse::data(new SeoUrlResource($rule), 201);
    }

    public function update(SeoUrlRequest $request, SeoUrl $url): JsonResponse
    {
        $url->update($request->values());

        return ApiResponse::data(new SeoUrlResource($url->refresh()));
    }

    public function destroy(SeoUrl $url): JsonResponse
    {
        $url->delete();

        return ApiResponse::noContent();
    }
}
