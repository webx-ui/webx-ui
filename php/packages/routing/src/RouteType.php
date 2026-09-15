<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\Formatters\PathFormatter;

/**
 * A kind of entity that has addresses.
 *
 * Routing knows no module names: pages, articles, categories and products arrive here as
 * registrations, and `/article/` is a segment the news module asked for rather than a notion
 * this package has.
 *
 * ```php
 * RouteTypes::register(new RouteType(
 *     type:        'product',
 *     model:       Product::class,
 *     formatter:   SlugSku::class,
 *     handler:     ProductPage::class,
 *     acceptsTail: false,
 *     onConflict:  OnConflict::Suffix,
 * ));
 * ```
 */
class RouteType
{
    /**
     * @param  string  $type  The morph alias, as stored in `routes.entity_type`.
     * @param  class-string<Model>  $model
     * @param  PathFormatter|class-string<PathFormatter>  $formatter
     * @param  class-string<RouteHandler>|null  $handler  Null while a type is only written, not served.
     * @param  bool  $acceptsTail  Whether a longer address may be matched by this row's prefix.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $model,
        public readonly PathFormatter|string $formatter,
        public readonly ?string $handler = null,
        public readonly bool $acceptsTail = false,
        public readonly OnConflict $onConflict = OnConflict::Fail,
    ) {}

    /**
     * The formatter to use, with a project's override on top.
     *
     * `webx-routing.types.<type>.formatter` is how a site changes the address scheme of
     * somebody else's module without touching it — and `webx:routes:rebuild --type=<type>`
     * is how the addresses that already exist follow the change.
     */
    public function formatter(): PathFormatter
    {
        $container = Container::getInstance();

        /** @var PathFormatter|class-string<PathFormatter>|null $configured */
        $configured = $container->make('config')->get("webx-routing.types.{$this->type}.formatter");

        $formatter = $configured ?? $this->formatter;

        return $formatter instanceof PathFormatter ? $formatter : $container->make($formatter);
    }
}
