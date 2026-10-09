<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use WebxUi\Themes\Exceptions\ThemeException;

/**
 * The layers of the site's theme, top first (spec §7.1): the local theme, what it uses, what
 * that uses. Modules are the layer below all of them, but they are not part of the chain — they
 * register their own views and the provider slots the chain in above them.
 *
 * One chain per installation, built once at boot and never changed: the view finder caches the
 * names it resolved for the life of the process, so a chain that moved would serve stale views.
 */
final readonly class ThemeChain
{
    /**
     * @param  list<ThemeManifest>  $layers  Top first.
     */
    public function __construct(public array $layers = []) {}

    /**
     * Every theme comes before the themes it uses, and among the `uses` of one theme the first
     * listed wins. Two themes that stand on the same base share one copy of it, placed below both.
     */
    public static function resolve(string $reference, ThemeLocator $locator): self
    {
        /** @var array<string, ThemeManifest> $done */
        $done = [];
        /** @var list<ThemeManifest> $order */
        $order = [];

        /** @param list<string> $stack */
        $visit = function (string $reference, array $stack, ?string $usedBy) use (&$visit, &$done, &$order, $locator): void {
            $manifest = $locator->locate($reference, $usedBy);

            if (in_array($manifest->name, $stack, true)) {
                throw ThemeException::cycle([...array_slice($stack, (int) array_search($manifest->name, $stack, true)), $manifest->name]);
            }

            if (isset($done[$manifest->name])) {
                return;
            }

            $stack[] = $manifest->name;

            // Visited last to first so that, once the post-order is reversed, the first entry of
            // `uses` sits highest among its siblings.
            foreach (array_reverse($manifest->uses) as $used) {
                $visit($used, $stack, $manifest->name);
            }

            $done[$manifest->name] = $manifest;
            $order[] = $manifest;
        };

        $visit($reference, [], null);

        return new self(array_reverse($order));
    }

    public function isEmpty(): bool
    {
        return $this->layers === [];
    }

    public function top(): ?ThemeManifest
    {
        return $this->layers[0] ?? null;
    }

    /**
     * The `views/` directory of every layer that has one, top first.
     *
     * @return list<string>
     */
    public function viewPaths(): array
    {
        return array_values(array_filter(array_map(fn (ThemeManifest $layer) => $layer->views(), $this->layers)));
    }

    /**
     * Every layer's overrides of one module namespace, top first.
     *
     * @return list<string>
     */
    public function namespaceViewPaths(string $namespace): array
    {
        return array_values(array_filter(array_map(fn (ThemeManifest $layer) => $layer->namespaceViews($namespace), $this->layers)));
    }

    /**
     * The modules the theme cannot render without — summed down the chain (§5).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return array_values(array_unique(array_merge([], ...array_map(fn (ThemeManifest $layer) => $layer->requires, $this->layers))));
    }
}
