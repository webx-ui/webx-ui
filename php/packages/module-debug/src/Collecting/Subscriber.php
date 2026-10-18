<?php

declare(strict_types=1);

namespace WebxUi\Debug\Collecting;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Routing\Events\RouteMatched;
use Symfony\Component\Mime\Address;
use Throwable;
use WebxUi\Admin\Support\SqlShape;
use WebxUi\Blocks\Events\BlockRendered;
use WebxUi\Blocks\Events\BlockRendering;
use WebxUi\Blocks\Events\RegionRendered;
use WebxUi\Blocks\Events\RegionRendering;
use WebxUi\Cache\Events\GroupRead;
use WebxUi\Debug\Collector;
use WebxUi\Debug\Support\CallSite;
use WebxUi\Debug\Support\Masker;
use WebxUi\Routing\Events\Resolved;
use WebxUi\Routing\Events\Resolving;

/**
 * The listeners that fill the {@see Collector} — every one of them subscribed the first time a
 * request of this process is collected, never before (§5 of the debug bar spec).
 *
 * That is the whole of «collect only when the bar will be shown»: a visitor's request never
 * starts collecting, so it never gets here, and the events of our own packages (§6.1) are not
 * even created while nobody listens. A worker that collected once keeps the listeners and pays
 * one check of {@see Collector::$active} per event afterwards.
 */
final class Subscriber
{
    private bool $subscribed = false;

    public function __construct(
        private readonly Collector $collector,
        private readonly Dispatcher $events,
        private readonly Views $views,
        private readonly CallSite $sites,
        private readonly Masker $masker,
        private readonly Config $config,
    ) {}

    public function subscribe(): void
    {
        if ($this->subscribed) {
            return;
        }

        $this->subscribed = true;

        $this->listen(QueryExecuted::class, $this->query(...));
        $this->listen(TransactionBeginning::class, function (): void {
            $this->transaction('begin');
        });
        $this->listen(TransactionCommitted::class, function (): void {
            $this->transaction('commit');
        });
        $this->listen(TransactionRolledBack::class, function (): void {
            $this->transaction('rollback');
        });

        $this->listen(RouteMatched::class, function (): void {
            if ($this->collector->isOpen('middleware')) {
                $this->collector->close('middleware');
                $this->collector->open('handler', 'handler');
            }
        });

        $this->listen(Resolving::class, function (): void {
            $this->collector->open('resolve', 'resolve');
        });
        $this->listen(Resolved::class, function (Resolved $event): void {
            $this->collector->close('resolve');
            $type = $event->resolution->type;
            $this->collector->open('handle', 'handle'.($type !== null && $type->handler !== null ? ' '.class_basename($type->handler) : ''));
        });

        $this->listen(RegionRendering::class, function (RegionRendering $event): void {
            $this->collector->open('region', 'region:'.$event->name);
            $this->collector->enter(['kind' => 'region', 'name' => $event->name]);
        });
        $this->listen(RegionRendered::class, function (RegionRendered $event): void {
            $this->collector->leave(['source' => $event->source]);
            $this->collector->close('region');
        });

        $this->listen(BlockRendering::class, function (BlockRendering $event): void {
            $block = $event->block;
            $this->collector->open('block', 'block:'.$block->key);
            $this->collector->enter(['kind' => 'block', 'key' => $block->key, 'type' => $block->type, 'version' => $block->version, 'depth' => $block->depth]);
        });
        $this->listen(BlockRendered::class, function (BlockRendered $event): void {
            $failure = $event->failure;
            $this->collector->leave($failure === null ? [] : ['error' => $failure::class.': '.mb_substr($failure->getMessage(), 0, 300)]);
            $this->collector->close('block');
        });

        $this->listen(GroupRead::class, $this->groupRead(...));
        $this->listen(CacheHit::class, function (CacheHit $event): void {
            $this->cacheEvent('hit', $event->key, $event->storeName);
        });
        $this->listen(CacheMissed::class, function (CacheMissed $event): void {
            $this->cacheEvent('miss', $event->key, $event->storeName);
        });
        $this->listen(KeyWritten::class, function (KeyWritten $event): void {
            $this->cacheEvent('write', $event->key, $event->storeName);
        });
        $this->listen(KeyForgotten::class, function (KeyForgotten $event): void {
            $this->cacheEvent('forget', $event->key, $event->storeName);
        });

        $this->listen(RequestSending::class, $this->httpSending(...));
        $this->listen(ResponseReceived::class, $this->httpReceived(...));
        $this->listen(ConnectionFailed::class, $this->httpFailed(...));

        $this->listen(JobQueued::class, $this->jobQueued(...));
        $this->listen(MessageSending::class, $this->mailSending(...));
        $this->listen(MessageLogged::class, $this->logged(...));

        foreach ((array) $this->config->get('webx-debug.events', []) as $event) {
            if (is_string($event) && $event !== '') {
                $this->listen($event, function () use ($event): void {
                    $this->collector->events[$event] = ($this->collector->events[$event] ?? 0) + 1;
                });
            }
        }

        // Every view made while collecting — `creator` rather than `composer`, so a view is
        // counted once, when it is made, not again for every render of it.
        $this->views->creator('*', function (View $view): void {
            if (! $this->collector->active) {
                return;
            }

            $this->measure(function () use ($view): void {
                $name = $view->name();

                if ($this->collector->page === null) {
                    $this->collector->page = $name;
                    $this->collector->open('render', 'render');
                }

                if (isset($this->collector->views[$name])) {
                    $this->collector->views[$name]['count']++;

                    return;
                }

                if (count($this->collector->views) >= $this->collector->limit('views', 500)) {
                    $this->collector->truncated = true;

                    return;
                }

                $path = method_exists($view, 'getPath') ? (string) $view->getPath() : '';
                $this->collector->views[$name] = ['name' => $name, 'path' => $path, 'count' => 1];
            });
        });
    }

    /**
     * @param  callable(object): mixed|callable(): mixed  $listener
     */
    private function listen(string $event, callable $listener): void
    {
        $this->events->listen($event, function (mixed ...$payload) use ($listener): void {
            if (! $this->collector->active) {
                return;
            }

            $this->measure(static fn (): mixed => $listener(...$payload));
        });
    }

    /** The collector's own time, kept apart so the bar can say what it cost. */
    private function measure(callable $work): void
    {
        $started = microtime(true);

        try {
            $work();
        } catch (Throwable) {
            // A report a little short is better than a page that breaks for being watched.
        } finally {
            $this->collector->overhead += microtime(true) - $started;
        }
    }

    private function query(QueryExecuted $event): void
    {
        $collector = $this->collector;
        $collector->queryCount++;
        $collector->queryTime += (float) $event->time;

        if (count($collector->queries) >= $collector->limit('queries', 1000)) {
            $collector->truncated = true;

            return;
        }

        $sql = $event->sql;
        $limit = $collector->limit('sql', 10000);
        $bindings = $event->connection->prepareBindings($event->bindings);
        $site = $this->sites->find();

        $collector->queries[] = [
            'kind' => 'query',
            'index' => $collector->queryCount,
            'sql' => mb_strlen($sql) > $limit ? mb_substr($sql, 0, $limit).'…' : $sql,
            'bindings' => $this->masker->bindings($sql, $bindings),
            'time' => round((float) $event->time, 2),
            'connection' => $event->connectionName,
            'at' => $collector->at(),
            'depth' => $collector->depth,
            'shape' => SqlShape::of($sql),
            'site' => $site['label'] ?? null,
            'file' => $site['file'] ?? null,
            'line' => $site['line'] ?? null,
            'in' => $collector->nodeLabel($collector->currentNode()),
        ];
    }

    private function transaction(string $kind): void
    {
        if ($kind === 'begin') {
            $this->collector->queries[] = ['kind' => $kind, 'depth' => $this->collector->depth, 'at' => $this->collector->at()];
            $this->collector->depth++;

            return;
        }

        $this->collector->depth = max(0, $this->collector->depth - 1);
        $this->collector->queries[] = ['kind' => $kind, 'depth' => $this->collector->depth, 'at' => $this->collector->at()];
    }

    private function groupRead(GroupRead $event): void
    {
        $key = $event->name;
        $entry = $this->collector->groups[$key] ?? ['depends' => $event->depends, 'memo' => 0, 'store' => 0, 'miss' => 0];
        match ($event->source) {
            GroupRead::MEMO => $entry['memo']++,
            GroupRead::STORE => $entry['store']++,
            default => $entry['miss']++,
        };
        $this->collector->groups[$key] = $entry;
    }

    private function cacheEvent(string $type, string $key, ?string $store): void
    {
        if (count($this->collector->cache) >= $this->collector->limit('cache', 2000)) {
            $this->collector->truncated = true;

            return;
        }

        $this->collector->cache[] = ['type' => $type, 'key' => mb_substr($key, 0, 250), 'store' => $store];
    }

    private function httpSending(RequestSending $event): void
    {
        $this->collector->http[spl_object_id($event->request)] = [
            'method' => $event->request->method(),
            'url' => $this->masker->url($event->request->url()),
            'started' => microtime(true),
            'at' => $this->collector->at(),
            'status' => null,
            'ms' => null,
            'error' => null,
        ];
    }

    private function httpReceived(ResponseReceived $event): void
    {
        $id = spl_object_id($event->request);
        $entry = $this->collector->http[$id] ?? ['method' => $event->request->method(), 'url' => $this->masker->url($event->request->url()), 'started' => null, 'at' => $this->collector->at(), 'error' => null];
        $entry['status'] = $event->response->status();
        $entry['ms'] = is_float($entry['started']) ? round((microtime(true) - $entry['started']) * 1000, 2) : null;
        $this->collector->http[$id] = $entry;
    }

    private function httpFailed(ConnectionFailed $event): void
    {
        $id = spl_object_id($event->request);
        $entry = $this->collector->http[$id] ?? ['method' => $event->request->method(), 'url' => $this->masker->url($event->request->url()), 'started' => null, 'at' => $this->collector->at(), 'status' => null];
        $entry['error'] = mb_substr($event->exception->getMessage(), 0, 300);
        $entry['ms'] = is_float($entry['started']) ? round((microtime(true) - $entry['started']) * 1000, 2) : null;
        $this->collector->http[$id] = $entry;
    }

    private function jobQueued(JobQueued $event): void
    {
        $job = $event->job;

        $this->collector->jobs[] = [
            'job' => is_object($job) ? $job::class : (is_string($job) ? $job : get_debug_type($job)),
            'connection' => $event->connectionName,
            'queue' => is_string($event->queue) ? $event->queue : null,
            'delay' => is_int($event->delay) ? $event->delay : null,
            'at' => $this->collector->at(),
        ];
    }

    private function mailSending(MessageSending $event): void
    {
        $message = $event->message;
        $to = array_map(fn (Address $address): string => $this->masker->email($address->getAddress()), $message->getTo());

        $this->collector->mail[] = [
            'to' => $to,
            'subject' => $message->getSubject(),
            'mailer' => is_string($event->data['mailer'] ?? null) ? $event->data['mailer'] : null,
            'at' => $this->collector->at(),
        ];
    }

    private function logged(MessageLogged $event): void
    {
        if (count($this->collector->log) >= $this->collector->limit('log', 200)) {
            $this->collector->truncated = true;

            return;
        }

        $context = $event->context;
        $exception = $context['exception'] ?? null;
        unset($context['exception']);

        $this->collector->log[] = [
            'level' => $event->level,
            'message' => mb_substr($event->message, 0, 2000),
            'context' => $this->masker->keyed($context),
            'exception' => $exception instanceof Throwable ? [
                'class' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 1000),
                'at' => $this->sites->describe($exception->getFile(), $exception->getLine())['label'],
            ] : null,
            'at' => $this->collector->at(),
        ];
    }
}
