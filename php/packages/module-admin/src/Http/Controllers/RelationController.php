<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Localization\Locales;

/**
 * What a `wx-relations` field draws (§3.7 of the recipes spec): candidates for a term, or the rows
 * of what is already chosen when the form opens with nothing but ids.
 *
 * One address for every target, like the link picker's: the permission rule is the target's and
 * is checked once, here. An administrator who may not see the target gets 403, and the field then
 * draws the chosen ids as a list without "Add".
 */
final class RelationController
{
    public function __construct(
        private readonly RelationTargets $targets,
        private readonly Locales $locales,
    ) {}

    public function __invoke(Request $request, string $target): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'ids' => ['nullable', 'array', 'max:200'],
            'ids.*' => ['integer'],
            'except' => ['nullable', 'array', 'max:200'],
            'except.*' => ['integer'],
            'locale' => ['nullable', 'string'],
        ]);

        $found = $this->targets->find($target) ?? throw new NotFoundHttpException;

        if (! $this->targets->allows($found, $request->user())) {
            throw new AccessDeniedHttpException;
        }

        $asked = $request->query('locale');
        $locale = is_string($asked) && $asked !== '' ? $asked : $this->locales->current();

        if ($request->has('ids')) {
            return ApiResponse::data($found->describe(self::ints($request->query('ids')), $locale));
        }

        return ApiResponse::data($found->candidates(
            trim((string) $request->query('q', '')),
            $locale,
            except: self::ints($request->query('except')),
        ));
    }

    /**
     * @return list<int>
     */
    private static function ints(mixed $value): array
    {
        return array_values(array_map(intval(...), array_filter(is_array($value) ? $value : [], is_numeric(...))));
    }
}
