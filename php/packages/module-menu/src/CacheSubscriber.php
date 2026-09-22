<?php

declare(strict_types=1);

namespace WebxUi\Menu;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;
use WebxUi\Routing\Models\Route;

/**
 * Everything that can change what a menu looks like, and the cache it forgets (§8).
 *
 * The rule is one sentence — any change after which a menu would look different forgets it —
 * and the work is in the list of places such a change can come from, because two of the three
 * are in other packages.
 *
 * There is deliberately no forgetting in a controller or in an agent's tool: both of those
 * write through models, and the models are what is listened to here. A write that goes round a
 * model — a mass `update`, an import — calls {@see MenuCache::flush()} itself, and the guide
 * says so.
 *
 * Listening by event name rather than through `observe()`: the models of the link sources are
 * class names this package has never heard of, and a string is the one way to subscribe to all
 * of them the same way.
 */
final class CacheSubscriber
{
    private bool $subscribed = false;

    public function __construct(
        private readonly Dispatcher $events,
        private readonly MenuCache $cache,
        private readonly LinkSources $sources,
    ) {}

    public function subscribe(): void
    {
        if ($this->subscribed) {
            return;
        }

        $this->subscribed = true;

        $this->ownTree();
        $this->addressRegistry();
        $this->linkedEntities();
    }

    /**
     * The menu itself.
     *
     * `moved` is not here for completeness. Dragging an item rewrites the bounds with one
     * `update` per pass and never raises `updated` — that is what makes a thousand-node branch
     * cheap — so a subscription to `updated` alone would mean reordering a menu never forgot
     * its cache at all.
     */
    private function ownTree(): void
    {
        $this->on(MenuItem::class, ['saved', 'deleted', 'moved'], function (Model $item): void {
            if ($item instanceof MenuItem) {
                $this->cache->forget($item->menuKey());
            }
        });

        $this->on(Menu::class, ['saved', 'deleted'], function (Model $menu): void {
            if ($menu instanceof Menu) {
                $this->cache->forget($menu->key);
            }
        });
    }

    /**
     * The address registry.
     *
     * A page renamed last week changed what the menu points at without anybody touching the
     * menu — and so did every page under it, whose models nobody saved and whose addresses were
     * rewritten row by row. `webx-ui/routing` raises no events of its own, so the model is
     * listened to directly; inventing an event in a library for the sake of one subscriber
     * would be the more elaborate way of doing less.
     *
     * A row with no entity behind it is a redirect or an alias somebody wrote by hand, and
     * there is no index to narrow that down by: everything goes.
     */
    private function addressRegistry(): void
    {
        $this->on(Route::class, ['saved', 'deleted'], function (Model $route): void {
            if (! $route instanceof Route) {
                return;
            }

            $type = $route->entity_type;
            $id = $route->entity_id;

            if (is_string($type) && $type !== '' && $id !== null) {
                $this->cache->forgetForEntity($type, $id);

                return;
            }

            $this->cache->flush();
        });
    }

    /**
     * The entities the items point at.
     *
     * This is the half the registry cannot see. Publishing a page and taking it off the site
     * change no address at all, and renaming one without touching its slug changes the label of
     * every item that has none of its own. Without this, a page published a minute ago would
     * not appear in the menu until something else happened to be edited.
     */
    private function linkedEntities(): void
    {
        foreach ($this->sources->all() as $source) {
            $this->on($source->model(), ['saved', 'deleted', 'restored'], function (Model $entity) use ($source): void {
                $key = $entity->getKey();

                if (is_int($key) || is_string($key)) {
                    $this->cache->forgetForEntity($source->type(), $key);
                }
            });
        }
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $events
     * @param  callable(Model): void  $forget
     */
    private function on(string $model, array $events, callable $forget): void
    {
        foreach ($events as $event) {
            $this->events->listen("eloquent.{$event}: {$model}", static function (mixed ...$payload) use ($forget): void {
                $subject = $payload[0] ?? null;

                if ($subject instanceof Model) {
                    $forget($subject);
                }
            });
        }
    }
}
