<?php

declare(strict_types=1);

namespace WebxUi\Services\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;
use WebxUi\Services\Panel\Revision;
use WebxUi\Services\Panel\ServiceForm;
use WebxUi\Services\Panel\ServiceList;

/**
 * What an agent can do with the services of a catalogue (§4.8).
 *
 * The same doors the panel uses. The list is {@see ServiceList}, so the four states and the two
 * orders are the ones the editor sees; the values go through {@see ServiceForm}, which checks them
 * against the described screen — a price a project patched onto `services.form` is a field an
 * agent writes under its own name and is refused exactly where the panel would refuse it; the
 * order is {@see Ordering}, the code behind the drag.
 *
 * The body is not written here, as with pages and articles: blocks are `blocks_edit_content`.
 */
final class ServiceTools
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $service = [
            'type' => ['integer', 'string'],
            'description' => 'The service: its id, or the address it answers at — "/services/implants-turnkey".',
        ];
        $category = [
            'type' => ['integer', 'string'],
            'description' => 'A category: its id or its slug. service_categories_list and services://catalog have both.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string, or every language as { "en": "…", "ru": "…" }.',
        ];

        return [
            Tool::read(
                'list',
                'The services of this catalogue in the order they stand in: what each is called in every '
                .'language, the address it answers at, whether it is on the site, and its categories — the '
                .'first of them the main one. Narrowed to a category, the list is in that category\'s own '
                .'order, which is not the order of the whole list. Read this (or services://catalog) first: '
                .'a service is named by its address, and a second one called the same thing is a duplicate.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Services whose title or address contains this, in any language the site has.'],
                    'status' => ['type' => 'string', 'enum' => [
                        Service::STATUS_DRAFT,
                        Service::STATUS_PUBLISHED,
                        Service::STATUS_MODIFIED,
                        Service::STATUS_UNPUBLISHED,
                    ], 'description' => 'Never published · on the site · on the site with edits waiting · taken off it.'],
                    'category' => $category + ['description' => 'Only the services in this category, in its order: its id or its slug.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first. A service in the bin has no address.'],
                ]],
                permission: ['services.view', 'services.manage'],
            ),

            Tool::read(
                'get',
                'One service in full: the values of its editor — title, address and lead in every language, '
                .'the categories in the order that makes the first one the main one, the cover, the SEO card, '
                .'any field the project added to the editor, the tree of blocks — the revision those values '
                .'are, and a link to the draft as the site would print it. Pass blocks: false for the settings '
                .'without the body.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->get($arguments, $user),
                ['properties' => [
                    'service' => $service,
                    'blocks' => ['type' => 'boolean', 'description' => 'The block tree in the values; true when omitted.'],
                ], 'required' => ['service']],
                permission: ['services.view', 'services.manage'],
            ),

            Tool::mutating(
                'create',
                'Start a service at the end of the list. It is a draft: nothing is on the site until somebody '
                .'publishes it. The address is made from the title when you do not write one, and an address '
                .'a category, a page or another service already answers at is refused rather than given a '
                .'suffix — services and their categories share one level. Categories set here take effect at '
                .'once: they are links, not values in a draft.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'title' => $text,
                    'slug' => $text + ['description' => 'The last segment of the address, per language; made from the title when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'The rest of the editor\'s fields — lead, categories, cover, the SEO card, the project\'s fields — as services_get returns them.'],
                ], 'required' => ['title']],
                permission: 'services.manage',
            ),

            Tool::mutating(
                'update',
                'Change the values of a service — title, address, lead, categories, cover, the SEO card and the '
                .'fields the project added — into its draft. A field left out keeps what it had. Send the '
                .'revision services_get gave you and the write is refused if somebody saved in between. The '
                .'body is not written here: blocks go through blocks_edit_content. The categories are not '
                .'drafted — they are on the site the moment they are saved.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'service' => $service,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as services_get returns them. Localized fields take { "en": "…" }.'],
                    'revision' => ['type' => 'string', 'description' => 'The revision services_get returned. Left out, the write goes in over whatever happened since.'],
                ], 'required' => ['service', 'values']],
                permission: 'services.manage',
            ),

            Tool::mutating(
                'publish',
                'Put the draft on the site: its values become the service and a version is written. Ask a '
                .'person first unless they asked you to publish.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->publish($arguments, $user)),
                ['properties' => ['service' => $service], 'required' => ['service']],
                permission: 'services.manage',
            ),

            Tool::mutating(
                'unpublish',
                'Take a service off the site. It answers 404 from then on and leaves the index and its '
                .'categories; its address stays reserved and whatever was being prepared is still there.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->unpublish($arguments)),
                ['properties' => ['service' => $service], 'required' => ['service']],
                permission: 'services.manage',
            ),

            Tool::mutating(
                'delete',
                'Put a service in the bin. Its address is released, so afterwards it can only be named by its '
                .'id. Nothing is destroyed: the bin in the panel puts it back, as long as nobody has taken its '
                .'address in the meantime.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['service' => $service], 'required' => ['service']],
                permission: 'services.manage',
            ),

            Tool::mutating(
                'reorder',
                'Put services in a new order. Without a category it is the order of the whole list; with one it '
                .'is the order inside that category only, and every other category keeps its own. Name the '
                .'services in the order they should stand in — the ones you leave out stay where they are.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'services' => ['type' => 'array', 'items' => $service, 'description' => 'The services, first to last.'],
                    'category' => $category + ['description' => 'The category whose own order this is. The whole list when omitted.'],
                ], 'required' => ['services']],
                permission: 'services.manage',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = [
            'q' => (string) ($arguments['search'] ?? ''),
            'status' => (string) ($arguments['status'] ?? ''),
            'trashed' => ($arguments['trashed'] ?? false) === true ? '1' : '0',
        ];

        $category = $this->category($arguments['category'] ?? null);

        if ($category !== null) {
            $query['category'] = (string) $category->getKey();
        }

        // The panel's own query: no pages, because a catalogue is dozens of rows and the order is
        // only an order when the whole of it is in view.
        $services = $this->container->make(ServiceList::class)->build(Request::create('/', 'GET', $query))->get();

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'order' => $category === null ? 'the whole list' : 'the order of this category',
            'count' => $services->count(),
            'services' => $services->map(fn (Service $service): array => $this->summary($service))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments, ?Authenticatable $user = null): array
    {
        $service = $this->service($arguments['service'] ?? null);
        $values = $this->form()->values($service);

        if (($arguments['blocks'] ?? true) !== true) {
            unset($values['blocks']);
        }

        return [
            'service' => $this->summary($service),
            'values' => $values,
            // Send it back with services_update, and a write over somebody else's is refused.
            'revision' => Revision::of($service),
            'preview_url' => $this->preview($service, $user),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $title = $this->text($arguments['title'] ?? null, 'title');
        $slug = isset($arguments['slug']) ? $this->text($arguments['slug'], 'slug') : $this->slugFrom($title);
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        $this->refuseBlocks($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => ['title' => $title, 'slug' => $slug],
                'would_answer_at' => $this->addresses($slug),
            ];
        }

        $service = new Service;
        $service->setTranslations('title', $title);
        $service->setTranslations('slug', $slug);
        $service->save();

        if ($values !== []) {
            $this->form()->save($service, $values, $this->can($user), $this->authorId($user));
        }

        return $this->get(['service' => $service->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $service = $this->service($arguments['service'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. services_get says what the fields are.');
        }

        $this->refuseBlocks($values);
        $this->sameRevision($arguments, $service);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_write' => 'draft',
                'fields' => array_keys($values),
                'service' => $this->reference($service),
            ];
        }

        $this->form()->save($service, $values, $this->can($user), $this->authorId($user));

        return $this->get(['service' => $service->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publish(array $arguments, ?Authenticatable $user): array
    {
        $service = $this->service($arguments['service'] ?? null);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_publish' => $this->reference($service),
                'status' => $service->status(),
                'has_waiting_edits' => $service->hasDraft(),
            ];
        }

        $service->publish($this->authorId($user), EntityVersion::SOURCE_MCP);

        return ['service' => $this->summary($service->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function unpublish(array $arguments): array
    {
        $service = $this->service($arguments['service'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_unpublish' => $this->reference($service), 'status' => $service->status()];
        }

        $service->unpublish();

        return ['service' => $this->summary($service->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $service = $this->service($arguments['service'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($service), 'status' => $service->status()];
        }

        $service->delete();

        return ['trashed' => true, 'id' => (int) $service->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['services'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`services` is the list of services in their new order: ids or addresses.');
        }

        $ids = array_values(array_map(fn (mixed $one): int => (int) $this->service($one)->getKey(), $given));
        $category = $this->category($arguments['category'] ?? null);

        if ($category !== null) {
            // Only the services the category holds: `item_position` lives on the link, and a
            // service that is not in the category has no place in its order to be given.
            $inside = $category->services()->pluck('services.id')->map(intval(...))->all();
            $outside = array_values(array_diff($ids, $inside));

            if ($outside !== []) {
                throw new ToolFailure(sprintf(
                    'Not in this category: #%s. File them into it with services_update first, or leave them out.',
                    implode(', #', $outside),
                ));
            }
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_order' => $ids, 'category' => $category?->getKey()];
        }

        Ordering::move(Service::class, $ids, $category === null ? null : (int) $category->getKey());

        return $this->list($category === null ? [] : ['category' => $category->getKey()]);
    }

    /**
     * One service as an agent needs it — every language at once, the draft's title where there is
     * one, and the registry's addresses, which are what the site answers at right now.
     *
     * @return array<string, mixed>
     */
    private function summary(Service $service): array
    {
        $service->loadMissing(['routes', 'categories']);
        $shown = $service->hasDraft() ? $service->withDraft() : $service;

        $summary = [
            'id' => (int) $service->getKey(),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'lead' => $shown->getTranslations('lead'),
            'urls' => $this->urls($service),
            'status' => $service->status(),
            'has_draft' => $service->hasDraft(),
            'position' => (int) $service->position,
            'updated_at' => $service->updated_at?->toAtomString(),
            // The first is the main one: the breadcrumbs go through it.
            'categories' => $service->categories
                ->map(static fn (ServiceCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->getTranslations('title'),
                    'slug' => $category->getTranslations('slug'),
                ])
                ->values()
                ->all(),
        ];

        if ($service->trashed()) {
            $summary['deleted_at'] = $service->deleted_at?->toAtomString();
        }

        return $summary;
    }

    /**
     * @return array<string, array{path: string, url: string}>
     */
    private function urls(Service $service): array
    {
        $urls = [];

        foreach ($service->loadMissing('routes')->routes as $route) {
            if ($route->kind === Route::CANONICAL) {
                $urls[$route->locale] = ['path' => '/'.$route->path, 'url' => $service->url($route->locale)];
            }
        }

        return $urls;
    }

    private function reference(Service $service): string
    {
        $urls = $this->urls($service);
        $address = $urls[$this->locales()->defaultCode()] ?? reset($urls);

        return sprintf('#%s (%s)', $service->getKey(), is_array($address) ? $address['path'] : 'no address');
    }

    /**
     * Where a service with these slugs would answer, before it exists — what a dry run reports.
     *
     * @param  array<string, string>  $slug
     * @return array<string, string>
     */
    private function addresses(array $slug): array
    {
        $prefix = UrlNormaliser::key((string) config('webx-services.prefix', 'services'));

        return array_map(
            static fn (string $one): string => '/'.UrlNormaliser::join($prefix, $one),
            $slug,
        );
    }

    /**
     * A service by id or by address — the address is what `services://catalog` hands over.
     */
    private function service(mixed $reference): Service
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $service = Service::withTrashed()->find((int) $reference);

            return $service instanceof Service
                ? $service
                : throw new ToolFailure("No service has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('A service is an id, or an address like "/services/implants-turnkey".');
        }

        $path = UrlNormaliser::key($reference);

        $route = Route::query()
            ->where('path', $path)
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', (new Service)->getMorphClass())
            ->first();

        $service = $route === null ? null : Service::query()->find($route->entity_id);

        return $service instanceof Service
            ? $service
            : throw new ToolFailure(
                "No service answers at [/{$path}]. services_list has the addresses; a service in the bin has none."
            );
    }

    /**
     * A category by id or by slug in any language — the slug is what an agent read off an address.
     */
    private function category(mixed $reference): ?ServiceCategory
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $category = ServiceCategory::query()->find((int) $reference);
        } elseif (is_string($reference)) {
            $slug = trim(UrlNormaliser::key($reference), '/');
            // The last segment: `services/implants` and `implants` name the same category.
            $slug = Str::afterLast($slug, '/');
            $category = ServiceCategory::query()->whereTranslationLikeAny('slug', $slug)->first();
        } else {
            throw new ToolFailure('`category` is an id or a slug.');
        }

        return $category instanceof ServiceCategory
            ? $category
            : throw new ToolFailure('No such category. service_categories_list says what there is.');
    }

    /**
     * Run a change, and turn a refusal into something the agent can read: the screen refusing a
     * value and the registry refusing an address are both a `ValidationException`.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (CategoryException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * Said rather than ignored: an agent that sent a block tree and got a cheerful answer would
     * think it had saved one.
     *
     * @param  array<string, mixed>  $values
     */
    private function refuseBlocks(array $values): void
    {
        if (array_key_exists('blocks', $values)) {
            throw new ToolFailure(
                'The body of a service is not written here: use blocks_edit_content, which names the block it '
                .'changes and leaves the rest alone. Read it first with blocks_get_content.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function sameRevision(array $arguments, Service $service): void
    {
        $sent = $arguments['revision'] ?? null;

        if (! is_string($sent) || $sent === '') {
            return;
        }

        $current = Revision::of($service);

        if ($sent !== $current) {
            throw new ToolFailure(
                "The service changed since you read it: revision is [{$current}], you sent [{$sent}]. "
                .'Read it again with services_get and redo the edit on what is there now.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function text(mixed $value, string $field): array
    {
        if (is_string($value)) {
            return trim($value) === ''
                ? throw new ToolFailure("`{$field}` cannot be empty.")
                : [$this->locales()->defaultCode() => trim($value)];
        }

        if (! is_array($value) || $value === []) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            }
        }

        return $texts === [] ? throw new ToolFailure("`{$field}` cannot be empty.") : $texts;
    }

    /**
     * @param  array<string, string>  $title
     * @return array<string, string>
     */
    private function slugFrom(array $title): array
    {
        return array_filter(array_map(static fn (string $text): string => Str::slug($text), $title));
    }

    private function preview(Service $service, ?Authenticatable $user): ?string
    {
        try {
            return Preview::url($service, $this->authorId($user));
        } catch (Throwable) {
            // A service the preview cannot sign — no address yet — is still one worth reading.
            return null;
        }
    }

    /**
     * The permission check the screen asks for a field behind one — the same question the panel
     * asks of its editor, so an agent cannot write a card its administrator could not.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user instanceof HasPermissions
            ? static fn (string $permission): bool => $user->hasPermission($permission)
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function authorId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private function form(): ServiceForm
    {
        return $this->container->make(ServiceForm::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
