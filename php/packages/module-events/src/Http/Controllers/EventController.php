<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Controllers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Events\Http\Requests\EventRequest;
use WebxUi\Events\Http\Resources\EventResource;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Events\Panel\Duplicate;
use WebxUi\Events\Panel\EventForm;
use WebxUi\Events\Panel\EventList;
use WebxUi\Events\Panel\Revision;
use WebxUi\Localization\Locales;

/**
 * The section's list, and one event as its editor opens it (§4.10).
 *
 * The form is a described screen, so what an event's values are is decided by the description
 * and checked by `ScreenValues`. What is left here is what the screen cannot answer — whether
 * this editor is writing over somebody else — and that a save, a new event and a copy are each one
 * transaction: a refusal half way through leaves nothing behind.
 */
final class EventController
{
    public function __construct(
        private readonly EventList $list,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * A page of events, with what the list can be narrowed to beside them: the screen cannot
     * draw its filters without them. `services` is null when no module answers for services —
     * the filter is not drawn then.
     */
    public function index(Request $request, Locales $locales, RelationTargets $targets): JsonResponse
    {
        $locale = $locales->current();
        $service = $targets->find('service');

        $page = $this->list->build($request)->paginate(
            min(100, max(5, (int) $request->integer('per_page', EventList::PER_PAGE))),
        );

        $page->through(static fn (Event $event): array => (new EventResource($event))->resolve($request));

        return new JsonResponse([
            'data' => $page->items(),
            'links' => [
                'first' => $page->url(1),
                'last' => $page->url($page->lastPage()),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $page->currentPage(),
                'from' => $page->firstItem(),
                'last_page' => $page->lastPage(),
                'path' => $page->path(),
                'per_page' => $page->perPage(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
            ],
            'filters' => [
                'categories' => EventCategory::query()->ordered()->get()->map(static fn (EventCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->displayName($locale),
                ])->values()->all(),
                'services' => $service === null ? null : array_map(
                    static fn (array $row): array => ['id' => $row['id'], 'title' => $row['title']],
                    $service->candidates('', $locale, 1000),
                ),
            ],
        ]);
    }

    public function show(Request $request, Event $event, EventForm $form): JsonResponse
    {
        return ApiResponse::data($form->describe($this->loaded($event), $this->author($request)));
    }

    /**
     * A new event: a title and the address made of it, as a draft — the registry holds its
     * address from the start, answering 404, so nobody else takes it while it is being written.
     * In a transaction: an address refused leaves no event without one.
     */
    public function store(EventRequest $request, EventForm $form): JsonResponse
    {
        $event = $this->db->transaction(static function () use ($request): Event {
            $event = new Event(['title' => $request->title(), 'slug' => $request->slug()]);
            $event->save();

            return $event;
        });

        return ApiResponse::data($form->describe($this->loaded($event->refresh()), $this->author($request)), 201);
    }

    /**
     * Save the draft. A request that names no revision did not read the event first — an
     * import, a script — and is let through: there is no editor to surprise.
     */
    public function update(Request $request, Event $event, EventForm $form): JsonResponse
    {
        $sent = $request->input('revision');

        if (is_string($sent) && $sent !== Revision::of($event)) {
            return new JsonResponse([
                'message' => (string) __('webx-events::errors.conflict'),
                'data' => $form->describe($this->loaded($event), $this->author($request)),
            ], 409);
        }

        $user = $request->user();
        $input = $request->input('values');
        $author = $this->author($request);

        $this->db->transaction(static fn () => $form->save(
            $event,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
            $author,
        ));

        return ApiResponse::data($form->describe($this->loaded($event->refresh()), $author));
    }

    /**
     * "Duplicate" (decision 9): the form of the copy, which the panel opens next.
     */
    public function duplicate(Request $request, Event $event, Duplicate $duplicate, EventForm $form): JsonResponse
    {
        $copy = $duplicate->of($event);

        return ApiResponse::data($form->describe($this->loaded($copy), $this->author($request)), 201);
    }

    /** Into the bin, and the address with it. */
    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return ApiResponse::noContent();
    }

    private function loaded(Event $event): Event
    {
        return $event->loadMissing(['routes', 'categories']);
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
