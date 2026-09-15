<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use WebxUi\Routing\Exceptions\RoutingException;

/**
 * The types that have addresses, filled from service providers.
 *
 * A singleton by the same pattern as `SeoSources`: modules register on boot, everything reads
 * on use, so the order providers happen to boot in never matters.
 *
 * Registering also puts the type into Eloquent's morph map, which is what keeps `entity_type`
 * an alias rather than a class name — a class name in the database breaks on the first move
 * between namespaces. The map is added to, not enforced: `enforceMorphMap()` would make *every*
 * polymorphic relation in the application require a map entry, including ones a project wrote
 * long before it installed this package. Our column is covered either way, because
 * `getMorphClass()` returns the alias as soon as it is in the map.
 */
class RouteTypes
{
    /** @var array<string, RouteType> */
    private array $types = [];

    public function register(RouteType $type): void
    {
        $this->types[$type->type] = $type;

        Relation::morphMap([$type->type => $type->model]);
    }

    /** @return array<string, RouteType> */
    public function all(): array
    {
        return $this->types;
    }

    public function find(string $type): ?RouteType
    {
        return $this->types[$type] ?? null;
    }

    public function get(string $type): RouteType
    {
        return $this->find($type) ?? throw new RoutingException(
            sprintf('No route type "%s" is registered.', $type),
        );
    }

    /** The type this entity belongs to, or null when nothing registered its model. */
    public function for(Model $entity): ?RouteType
    {
        $alias = $entity->getMorphClass();

        if (isset($this->types[$alias])) {
            return $this->types[$alias];
        }

        // A model whose class was never aliased — the morph map lost the registration, or the
        // entity comes from a subclass. Falling back to the class name keeps a single-type
        // application working rather than failing on a lookup it could answer.
        foreach ($this->types as $type) {
            if ($entity instanceof $type->model) {
                return $type;
            }
        }

        return null;
    }

    public function forEntity(Model $entity): RouteType
    {
        return $this->for($entity) ?? throw new RoutingException(sprintf(
            '%s uses HasUrl but no route type is registered for it.',
            $entity::class,
        ));
    }

    public function forget(): void
    {
        $this->types = [];
    }
}
