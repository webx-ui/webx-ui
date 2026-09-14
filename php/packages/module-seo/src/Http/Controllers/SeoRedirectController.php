<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Seo\Http\Requests\SeoRedirectRequest;
use WebxUi\Seo\Http\Resources\SeoRedirectResource;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Panel\UrlMatcher;

final class SeoRedirectController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:2048'],
            'match_type' => ['nullable', Rule::in(UrlMatcher::types())],
            'is_active' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['pattern', '-pattern', 'hits', '-hits', 'created_at', '-created_at'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $term = addcslashes((string) $request->string('q'), '%_\\');

        $redirects = SeoRedirect::query()
            ->when($request->filled('q'), fn ($query) => $query->where(
                fn ($where) => $where->where('pattern', 'like', "%{$term}%")->orWhere('target', 'like', "%{$term}%"),
            ))
            ->when($request->filled('match_type'), fn ($query) => $query->where('match_type', $request->string('match_type')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy(...$this->sort((string) $request->string('sort')))
            ->paginate((int) $request->integer('per_page', 25));

        return SeoRedirectResource::collection($redirects);
    }

    /**
     * `-hits` for the busiest first. Newest first by default: a redirect list is worked on from
     * the end an editor was last at.
     *
     * @return array{string, string}
     */
    private function sort(string $sort): array
    {
        $column = ltrim($sort, '-');

        return $column === ''
            ? ['id', 'desc']
            : [$column, str_starts_with($sort, '-') ? 'desc' : 'asc'];
    }

    public function show(SeoRedirect $redirect): JsonResponse
    {
        return ApiResponse::data(new SeoRedirectResource($redirect));
    }

    public function store(SeoRedirectRequest $request): JsonResponse
    {
        $redirect = SeoRedirect::query()->create($request->values());

        return ApiResponse::data(new SeoRedirectResource($redirect), 201);
    }

    public function update(SeoRedirectRequest $request, SeoRedirect $redirect): JsonResponse
    {
        $redirect->update($request->values());

        return ApiResponse::data(new SeoRedirectResource($redirect->refresh()));
    }

    public function destroy(SeoRedirect $redirect): JsonResponse
    {
        $redirect->delete();

        return ApiResponse::noContent();
    }
}
