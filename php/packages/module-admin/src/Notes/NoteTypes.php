<?php

declare(strict_types=1);

namespace WebxUi\Admin\Notes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The kinds of record that have notes, filled from service providers.
 *
 * The same pattern as `RouteTypes` in `webx-ui/routing`, and for the same two reasons.
 * Registering puts the alias into Eloquent's morph map, which is what keeps `entity_type` an
 * alias rather than a class name — and the register is also the white list: the notes endpoint
 * takes a type out of the address, and resolving that through the morph map alone would open
 * the notes of every polymorphic model in the application, including the ones that never asked
 * for any.
 *
 * The map is added to, not enforced: `enforceMorphMap()` would make every polymorphic relation
 * in the application require an entry, including ones a project wrote before installing this.
 */
final class NoteTypes
{
    /** @var array<string, class-string<Model>> */
    private array $types = [];

    /**
     * @param  class-string<Model>  $model
     */
    public function register(string $alias, string $model): void
    {
        $this->types[$alias] = $model;

        Relation::morphMap([$alias => $model]);
    }

    /** @return class-string<Model>|null */
    public function find(string $alias): ?string
    {
        return $this->types[$alias] ?? null;
    }

    /** @return array<string, class-string<Model>> */
    public function all(): array
    {
        return $this->types;
    }

    public function forget(): void
    {
        $this->types = [];
    }
}
