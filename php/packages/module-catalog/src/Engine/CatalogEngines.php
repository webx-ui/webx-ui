<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * The engines a site can choose between with `webx-catalog.engine`: the core's `sql`, and
 * `manticore` once `webx-ui/catalog-manticore` registers it.
 */
final class CatalogEngines
{
    /** @var array<string, class-string<CatalogEngine>> */
    private array $engines = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<CatalogEngine>  $engine
     */
    public function register(string $name, string $engine): void
    {
        $this->engines[$name] = $engine;
    }

    public function make(string $name): CatalogEngine
    {
        $class = $this->engines[$name] ?? throw new LogicException(
            "No catalogue engine is called [{$name}]; there are: ".implode(', ', array_keys($this->engines)).'.',
        );

        /** @var CatalogEngine $engine */
        $engine = $this->container->make($class);

        return $engine;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->engines);
    }
}
