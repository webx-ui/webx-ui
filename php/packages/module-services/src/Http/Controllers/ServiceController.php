<?php

declare(strict_types=1);

namespace WebxUi\Services\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Localization\Locales;
use WebxUi\Services\Http\Requests\ServiceRequest;
use WebxUi\Services\Http\Resources\ServiceResource;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;
use WebxUi\Services\Panel\Revision;
use WebxUi\Services\Panel\ServiceForm;
use WebxUi\Services\Panel\ServiceList;

/**
 * The section's list, and one service as its editor opens it (§4.6, §4.7).
 *
 * The record is thin on purpose: the form is a described screen, so what a service's values are
 * is decided by the description and checked by `ScreenValues`. What is left here is the one
 * thing the screen cannot answer — whether this editor is writing over somebody else.
 */
final class ServiceController
{
    public function __construct(private readonly ServiceList $list) {}

    /**
     * Every service at once, with the categories the list can be narrowed to beside them: the
     * screen cannot draw its filter without them, and a second round trip is a second chance to
     * show half a screen.
     */
    public function index(Request $request, Locales $locales): JsonResponse
    {
        $locale = $locales->current();

        return new JsonResponse([
            'data' => $this->list->build($request)
                ->get()
                ->map(static fn (Service $service): array => (new ServiceResource($service))->resolve($request))
                ->values()
                ->all(),
            'filters' => [
                'categories' => ServiceCategory::query()
                    ->ordered()
                    ->get()
                    ->map(static fn (ServiceCategory $category): array => [
                        'id' => (int) $category->getKey(),
                        'title' => $category->displayName($locale),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function show(Request $request, Service $service, ServiceForm $form): JsonResponse
    {
        return ApiResponse::data($form->describe($this->loaded($service), $this->author($request)));
    }

    /**
     * A new service: a title and the address made of it. It arrives as a draft, so the registry
     * holds its address — answering 404 — until somebody publishes: an address held from the
     * start is one nobody else can take while the service is being written.
     */
    public function store(ServiceRequest $request): JsonResponse
    {
        $service = new Service(['title' => $request->title(), 'slug' => $request->slug()]);
        $service->save();

        return ApiResponse::data(new ServiceResource($this->loaded($service->refresh())), 201);
    }

    /**
     * Save the draft. A request that names no revision did not read the service first — an
     * import, a script — and is let through: there is no editor to surprise.
     */
    public function update(Request $request, Service $service, ServiceForm $form): JsonResponse
    {
        $sent = $request->input('revision');

        if (is_string($sent) && $sent !== Revision::of($service)) {
            return new JsonResponse([
                'message' => (string) __('webx-services::errors.conflict'),
                'data' => $form->describe($this->loaded($service), $this->author($request)),
            ], 409);
        }

        $user = $request->user();
        $input = $request->input('values');

        $form->save(
            $service,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
            $this->author($request),
        );

        return ApiResponse::data($form->describe($this->loaded($service->refresh()), $this->author($request)));
    }

    /**
     * Into the bin. The address goes with it — a service nobody can reach has no business holding
     * a spelling the next one called the same thing will want.
     */
    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return ApiResponse::noContent();
    }

    private function loaded(Service $service): Service
    {
        return $service->loadMissing(['routes', 'categories', 'cover']);
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
