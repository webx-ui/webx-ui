<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Routing\RouteTypes;

/**
 * The entities an agent may address, by a name rather than a class.
 *
 * `webx-blocks.entities` lists the models that hold blocks; an agent should not have to spell
 * `App\Models\Page` with its backslashes to reach one. The name is the route type the model is
 * registered under — `page`, the word the site itself uses for it — and the kebab-case class
 * name for a model the registry does not know.
 *
 * One name is always there, whatever the site listed: `region`, this package's own entity, whose
 * id is the region's name (§8 of the regions spec). A declared region nobody has saved is found
 * all the same — empty and unsaved — and the first write makes its row.
 */
final class Entities
{
    public const REGION = 'region';

    public function __construct(
        private readonly Config $config,
        private readonly RouteTypes $types,
        private readonly Regions $regions,
    ) {}

    /**
     * @return array<string, class-string<Model>> name → model class
     */
    public function names(): array
    {
        $names = [];
        $classes = $this->config->get('webx-blocks.entities', []);

        foreach (is_array($classes) ? $classes : [] as $class) {
            if (is_string($class) && is_subclass_of($class, Model::class) && $class !== Region::class) {
                $names[$this->nameFor($class)] = $class;
            }
        }

        $names[self::REGION] = Region::class;

        return $names;
    }

    /**
     * @throws ToolFailure when the name or the id is unknown
     */
    public function find(string $name, int|string $id): Model
    {
        if ($name === self::REGION) {
            return $this->region((string) $id);
        }

        $names = $this->names();
        $class = $names[$name] ?? null;

        if ($class === null) {
            throw new ToolFailure("No entity is called [{$name}]. Known: ".implode(', ', array_keys($names)).'.');
        }

        $entity = $class::query()->find($id);

        if (! $entity instanceof Model) {
            throw new ToolFailure("No {$name} has the id [{$id}].");
        }

        return $entity;
    }

    public function nameOf(Model $entity): string
    {
        return $entity instanceof Region ? self::REGION : $this->nameFor($entity::class);
    }

    /** The same, from the class alone — what a list of where a type stands carries. */
    public function nameOfClass(string $class): string
    {
        return $class === Region::class ? self::REGION : $this->nameFor($class);
    }

    /**
     * @throws ToolFailure when the site does not declare the region
     */
    public function region(string $name): Region
    {
        if (! $this->regions->has($name)) {
            $declared = array_keys($this->regions->declared());

            throw new ToolFailure("No region is called [{$name}]. Declared: ".($declared === [] ? 'none — the site declares them in webx-blocks.regions' : implode(', ', $declared)).'.');
        }

        return $this->regions->findOrNew($name);
    }

    private function nameFor(string $class): string
    {
        foreach ($this->types->all() as $type) {
            if ($type->model === $class) {
                return $type->type;
            }
        }

        return Str::kebab(class_basename($class));
    }
}
