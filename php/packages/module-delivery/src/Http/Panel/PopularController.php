<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Panel\LocalityRows;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Localization\Locales;

/**
 * The popular towns of the checkout's dialog (decision 40, §14.2): one list for the site, in the
 * order dragged here. A switched-off locality stays in the list and is marked — the dialog does
 * not show it until it is switched on again.
 */
final class PopularController
{
    public function __construct(
        private readonly PopularLocalities $popular,
        private readonly Locales $locales,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data($this->rows($this->popular->ids()));
    }

    /** The whole list, in its new order. */
    public function update(Request $request): JsonResponse
    {
        return ApiResponse::data($this->rows($this->attempt(fn (): array => $this->popular->set((array) $request->input('ids', [])))));
    }

    /** «Add»: one locality, at the end unless a position is given. */
    public function store(Request $request): JsonResponse
    {
        $id = (int) $request->input('locality_id');
        $position = $request->filled('position') ? (int) $request->input('position') : null;

        return ApiResponse::data($this->rows($this->attempt(fn (): array => $this->popular->add($id, $position))), 201);
    }

    public function destroy(int $locality): JsonResponse
    {
        return ApiResponse::data($this->rows($this->popular->remove($locality)));
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function rows(array $ids): array
    {
        $locale = $this->locales->current();
        $localities = GeoLocality::query()->with('region')->whereKey($ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(
            static fn (int $id): ?array => $localities->has($id) ? LocalityRows::brief($localities->get($id), $locale) : null,
            $ids,
        )));
    }

    /**
     * @param  callable(): list<int>  $write
     * @return list<int>
     */
    private function attempt(callable $write): array
    {
        try {
            return $write();
        } catch (DeliveryException $refusal) {
            throw ValidationException::withMessages(['locality_id' => [$refusal->getMessage()]]);
        }
    }
}
