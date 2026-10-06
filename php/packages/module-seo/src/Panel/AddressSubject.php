<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\RouteTypes;

/**
 * The entity a public page at this address would be about — asked from the panel.
 *
 * The public page gets its subject from the resolver that served it; `test_url` has no such
 * request, so without this it asked the sources with no subject at all and left the page's own
 * card out of the answer. The lookup is the resolver's, not a guess: only a live row of the
 * registry that owns the whole address counts, the same row the resolver would hand a handler.
 * A row that matched by prefix answers for a tail, and an alias only redirects — neither is the
 * page the question is about.
 */
final class AddressSubject
{
    public function __construct(
        private readonly Resolver $resolver,
        private readonly RouteTypes $types,
    ) {}

    /**
     * The entity and the language of its row, or nulls when no page of an entity lives here.
     *
     * @return array{0: Model|null, 1: string|null}
     */
    public function at(string $url, ?string $locale = null): array
    {
        $resolution = $this->resolver->lookup($url, $locale);

        if ($resolution === null || $resolution->tail !== '' || $resolution->route->isAlias()) {
            return [null, null];
        }

        $row = $resolution->route;
        $type = $this->types->find($row->entity_type);

        if ($type === null || ! is_subclass_of($type->model, Model::class)) {
            return [null, null];
        }

        $entity = $type->model::query()->find($row->entity_id);

        return $entity instanceof Model ? [$entity, $row->locale] : [null, null];
    }
}
