<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Routing\RouteTypes;

/**
 * The entities an agent may address, by a name rather than a class.
 *
 * `webx-blocks.entities` lists the models that hold blocks; an agent should not have to spell
 * `App\Models\Page` with its backslashes to reach one. The name is the route type the model is
 * registered under — `page`, the word the site itself uses for it — and the kebab-case class
 * name for a model the registry does not know.
 */
final class Entities
{
    public function __construct(
        private readonly Config $config,
        private readonly RouteTypes $types,
    ) {}

    /**
     * @return array<string, class-string<Model>> name → model class
     */
    public function names(): array
    {
        $names = [];
        $classes = $this->config->get('webx-blocks.entities', []);

        foreach (is_array($classes) ? $classes : [] as $class) {
            if (is_string($class) && is_subclass_of($class, Model::class)) {
                $names[$this->nameFor($class)] = $class;
            }
        }

        return $names;
    }

    /**
     * @throws ToolFailure when the name or the id is unknown
     */
    public function find(string $name, int|string $id): Model
    {
        $names = $this->names();
        $class = $names[$name] ?? null;

        if ($class === null) {
            $known = $names === []
                ? 'none yet — the site lists the models that hold blocks in webx-blocks.entities'
                : implode(', ', array_keys($names));

            throw new ToolFailure("No entity is called [{$name}]. Known: {$known}.");
        }

        $entity = $class::query()->find($id);

        if (! $entity instanceof Model) {
            throw new ToolFailure("No {$name} has the id [{$id}].");
        }

        return $entity;
    }

    public function nameOf(Model $entity): string
    {
        return $this->nameFor($entity::class);
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
