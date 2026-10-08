<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * After a rewrite of keys, the caches the panel's own modules keep of rendered content: the
 * settings, the published trees of block regions, the menus. A rewrite goes around the models,
 * so the events those caches listen to never fired, and the site would go on drawing the old
 * key until the cache expired.
 *
 * Named by class rather than imported: none of these modules is a dependency of the library,
 * and a site without one simply has nothing of it to forget.
 */
final class ForgetRenderedContent
{
    public function __construct(private readonly Container $container) {}

    public function handle(): void
    {
        $this->each('WebxUi\Settings\Settings', static fn (object $settings) => $settings->forget());

        $this->each('WebxUi\Blocks\Regions', static function (object $regions): void {
            foreach (array_keys($regions->declared()) as $name) {
                $regions->forget($name);
            }
        });

        $this->each('WebxUi\Blocks\Rendering\Renderer', static fn (object $renderer) => $renderer->flush());
        $this->each('WebxUi\Menu\MenuCache', static fn (object $menus) => $menus->flush());
    }

    /**
     * @param  callable(object): mixed  $forget
     */
    private function each(string $class, callable $forget): void
    {
        if (! class_exists($class)) {
            return;
        }

        try {
            $forget($this->container->make($class));
        } catch (Throwable $error) {
            // A cache that cannot be reached expires on its own; the conversion is done.
            report($error);
        }
    }
}
