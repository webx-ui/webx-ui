<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Seo\Faq\PageFaq;
use WebxUi\Seo\Features;
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
            'has_faq' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $faq = Features::faq();

        $rules = SeoUrl::query()
            ->when($faq, fn ($query) => $query->withCount('faqItems'))
            ->when($faq && $request->boolean('has_faq'), fn ($query) => $query->whereHas('faqItems'))
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
        return ApiResponse::data(new SeoUrlResource(self::withFaq($url)));
    }

    public function store(SeoUrlRequest $request, PageFaq $faq): JsonResponse
    {
        $rule = DB::transaction(static function () use ($request, $faq): SeoUrl {
            $rule = SeoUrl::query()->create($request->values());
            self::writeFaq($rule, $request, $faq);

            return $rule;
        });

        return ApiResponse::data(new SeoUrlResource(self::withFaq($rule)), 201);
    }

    public function update(SeoUrlRequest $request, SeoUrl $url, PageFaq $faq): JsonResponse
    {
        DB::transaction(static function () use ($request, $url, $faq): void {
            $url->update($request->values());
            self::writeFaq($url, $request, $faq);
        });

        return ApiResponse::data(new SeoUrlResource(self::withFaq($url->refresh())));
    }

    public function destroy(SeoUrl $url): JsonResponse
    {
        $url->delete();

        return ApiResponse::noContent();
    }

    /** The questions sent with the rule replace the ones it had; none sent, none touched. */
    private static function writeFaq(SeoUrl $rule, SeoUrlRequest $request, PageFaq $faq): void
    {
        $items = $request->faq();

        // Emptied in the save that turns the rule into a mask: the request let it through for that.
        if ($items !== null && ($rule->match_type === UrlMatcher::EXACT || $items === [])) {
            $faq->write($rule, $items);
        }
    }

    private static function withFaq(SeoUrl $rule): SeoUrl
    {
        return Features::faq() ? $rule->load('faqItems') : $rule;
    }
}
