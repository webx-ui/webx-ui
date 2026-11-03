<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Panel\PointForm;
use WebxUi\Delivery\PointKinds;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Normalizer;
use WebxUi\Localization\Locales;

/**
 * The shop's own points (§14.2): a table a page at a time, by kind, locality and state, and one
 * point as its form opens it. The carriers' points are not here — they are the sync's.
 */
final class PointController
{
    public function __construct(
        private readonly PointForm $form,
        private readonly PointKinds $kinds,
        private readonly Locales $locales,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->locales->current();
        $query = DeliveryPoint::query()->with('locality.region')->where('carrier', Carriers::STORE);

        if ($request->filled('kind')) {
            $query->where('kind', (string) $request->query('kind'));
        }

        if ($request->filled('locality')) {
            $query->where('locality_id', (int) $request->query('locality'));
        }

        match ((string) $request->query('state', '')) {
            'active' => $query->where('active', true),
            'inactive' => $query->where('active', false),
            default => null,
        };

        $key = Normalizer::key((string) $request->query('q', ''));

        if ($key !== '') {
            $query->where('search', 'like', '%'.addcslashes($key, '%_\\').'%');
        }

        $this->sort($query, (string) $request->query('sort', ''));

        $page = $query->orderBy('id')->paginate(min(100, max(10, (int) $request->query('per_page', 25))));
        $kinds = $this->kinds->all($locale);
        $page->setCollection($page->getCollection()->map(
            static fn (DeliveryPoint $point): array => PointForm::row($point, $locale, $kinds),
        ));

        return new JsonResponse([
            ...$page->toArray(),
            'filters' => ['kinds' => $this->form->kindOptions($locale)],
        ]);
    }

    /**
     * The order the table asks for: `title`, `kind`, `locality` or `active`, a minus for the other
     * way round. By name is by `search`, which starts with the name for the shop's points (they
     * have no number) and is already normalised; by locality is by the town's own key.
     *
     * @param  Builder<DeliveryPoint>  $query
     */
    private function sort(Builder $query, string $sort): void
    {
        $desc = str_starts_with($sort, '-');
        $direction = $desc ? 'desc' : 'asc';

        match ($desc ? substr($sort, 1) : $sort) {
            'title' => $query->orderBy('search', $direction),
            'kind' => $query->orderBy('kind', $direction),
            'active' => $query->orderBy('active', $direction),
            'locality' => $query->orderBy(
                GeoLocality::query()->select('name_key')->whereColumn('geo_localities.id', 'delivery_points.locality_id'),
                $direction,
            ),
            default => null,
        };
    }

    public function show(DeliveryPoint $point): JsonResponse
    {
        $this->own($point);

        return ApiResponse::data($this->form->describe($point));
    }

    public function store(Request $request): JsonResponse
    {
        $point = $this->form->save(new DeliveryPoint, (array) $request->input('values', []), $this->can($request));

        return ApiResponse::data($this->form->describe($point), 201);
    }

    public function update(Request $request, DeliveryPoint $point): JsonResponse
    {
        $this->own($point);
        $point = $this->form->save($point, (array) $request->input('values', []), $this->can($request));

        return ApiResponse::data($this->form->describe($point));
    }

    /**
     * Deleted for good: a buyer's choice of it falls back to choosing again, and an order keeps
     * its snapshot (§11.1). A point to bring back later is switched off instead.
     */
    public function destroy(DeliveryPoint $point): JsonResponse
    {
        $this->own($point);
        $point->delete();

        return ApiResponse::noContent();
    }

    /** A carrier's point is not the panel's to edit: 404, as if it were not there. */
    private function own(DeliveryPoint $point): void
    {
        abort_if($point->carrier !== Carriers::STORE, 404);
    }

    /** @return callable(string): bool */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
