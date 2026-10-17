<?php

declare(strict_types=1);

namespace WebxUi\Menu;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Links\LinkSources;

/**
 * Everything that can change what a menu looks like, and the cache it forgets (§8).
 *
 * The rule is one sentence — any change after which a menu would look different forgets it —
 * and the work is in the list of places such a change can come from, because two of the three
 * are in other packages.
 *
 * There is deliberately no forgetting in a controller or in an agent's tool: both of those
 * write through models, and the models are what is listened to. A write that goes round a
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

        $this->linkedEntities();
    }

    /**
     * The entities the items point at.
     *
     * The menu's own rows move the `menu` group from the provider (`bumpOn`, `moved` included —
     * dragging an item rewrites the bounds with one `update` per pass and never raises
     * `updated`), and the address registry is a group of its own that every built menu depends
     * on. This is the half neither sees: publishing a page and taking it off the site change no
     * address at all, and renaming one without touching its slug changes the label of every
     * item that has none of its own. Without this, a page published a minute ago would not
     * appear in the menu until something else happened to be edited.
     */
    private function linkedEntities(): void
    {
        foreach ($this->sources->all() as $source) {
            $this->on($source->model(), ['saved', 'deleted', 'restored'], function (): void {
                $this->cache->flush();
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
