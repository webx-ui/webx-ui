<?php

declare(strict_types=1);

namespace WebxUi\Debug;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * What one request did, written down while it runs (§5 of the debug bar spec).
 *
 * One object per process, switched on by {@see Http\StartCollecting} (or late, by
 * {@see Http\CollectLate}) and thrown away when the response is decided. The listeners that
 * fill it are subscribed the first time a request is collected and stay — a worker serving
 * the next request pays a check of {@see $active} per event, a visitor's request that never
 * switched collecting on has no listener at all.
 *
 * It never asks the database anything: whatever needs a query is looked up after collecting
 * stopped, when the report is built.
 */
final class Collector
{
    /** Whether this request is being collected. */
    public bool $active = false;

    /** Switched on after `StartSession` rather than first: the early part is not recorded. */
    public bool $late = false;

    /** The one-time token of `debug_page`, when the request came from the agent's tool. */
    public ?string $token = null;

    /** When the request started (`LARAVEL_START`), in seconds. */
    public float $origin = 0.0;

    /** When collecting started. */
    public float $started = 0.0;

    /** When collecting stopped. */
    public float $stopped = 0.0;

    /** Seconds the collector itself spent. */
    public float $overhead = 0.0;

    public bool $truncated = false;

    /**
     * The statements, in order, and the transactions around them.
     *
     * @var list<array<string, mixed>>
     */
    public array $queries = [];

    public int $queryCount = 0;

    /** Milliseconds, as the connection reported them. */
    public float $queryTime = 0.0;

    /** Open transactions per connection: how far a statement is indented. */
    public int $depth = 0;

    /**
     * Spans of the timeline: kind, label, start, end, parent and the statements inside.
     *
     * @var list<array{kind: string, label: string, start: float, end: float|null, parent: int|null, q0: int, q1: int|null}>
     */
    public array $spans = [];

    /** @var list<int> the spans open now, outermost first */
    private array $open = [];

    /**
     * Regions and blocks as they were drawn, flat, each with its parent.
     *
     * @var list<array<string, mixed>>
     */
    public array $nodes = [];

    /** @var list<int> the regions and blocks being drawn now */
    private array $drawing = [];

    /** @var array<string, array{name: string, path: string, count: int}> */
    public array $views = [];

    /** @var list<array{type: string, key: string, store: string|null}> */
    public array $cache = [];

    /** @var array<string, array{depends: list<string>, memo: int, store: int, miss: int}> */
    public array $groups = [];

    /** @var array<int, array<string, mixed>> */
    public array $http = [];

    /** @var list<array<string, mixed>> */
    public array $jobs = [];

    /** @var list<array<string, mixed>> */
    public array $mail = [];

    /** @var array<string, int> */
    public array $events = [];

    /** @var list<array<string, mixed>> */
    public array $log = [];

    /** The first view of the page: what the handler rendered. */
    public ?string $page = null;

    /** @var array<string, int> `webx-debug.limits`, read once a request */
    private array $limits = [];

    public function __construct(private readonly Config $config) {}

    public function start(bool $late = false, ?string $token = null): void
    {
        $this->reset();

        $limits = $this->config->get('webx-debug.limits');
        $this->limits = is_array($limits) ? array_map(static fn (mixed $limit): int => is_numeric($limit) ? max(0, (int) $limit) : 0, $limits) : [];

        $now = microtime(true);
        $this->active = true;
        $this->late = $late;
        $this->token = $token;
        $this->started = $now;
        $this->origin = defined('LARAVEL_START') ? min($now, (float) LARAVEL_START) : $now;

        if (! $late) {
            $this->spans[] = ['kind' => 'boot', 'label' => 'boot', 'start' => $this->origin, 'end' => $now, 'parent' => null, 'q0' => 0, 'q1' => 0];
            $this->open('middleware', 'middleware');
        } else {
            // The route matched long ago; what is left is the handler.
            $this->open('handler', 'handler');
        }
    }

    /** Collecting stops: whatever is still open ends here. */
    public function stop(): void
    {
        if (! $this->active) {
            return;
        }

        $this->stopped = microtime(true);

        while ($this->open !== []) {
            $this->closeTop($this->stopped);
        }

        $this->active = false;
    }

    public function reset(): void
    {
        $this->active = false;
        $this->late = false;
        $this->token = null;
        $this->truncated = false;
        $this->overhead = 0.0;
        $this->stopped = 0.0;
        $this->queries = [];
        $this->queryCount = 0;
        $this->queryTime = 0.0;
        $this->depth = 0;
        $this->spans = [];
        $this->open = [];
        $this->nodes = [];
        $this->drawing = [];
        $this->views = [];
        $this->cache = [];
        $this->groups = [];
        $this->http = [];
        $this->jobs = [];
        $this->mail = [];
        $this->events = [];
        $this->log = [];
        $this->page = null;
    }

    /** Milliseconds since the request started. */
    public function at(?float $time = null): float
    {
        return round((($time ?? microtime(true)) - $this->origin) * 1000, 2);
    }

    public function open(string $kind, string $label): void
    {
        $parent = $this->open === [] ? null : $this->open[array_key_last($this->open)];

        $this->spans[] = ['kind' => $kind, 'label' => $label, 'start' => microtime(true), 'end' => null, 'parent' => $parent, 'q0' => $this->queryCount, 'q1' => null];
        $this->open[] = array_key_last($this->spans);
    }

    /** Close the innermost open span of this kind, and whatever was opened inside it and left. */
    public function close(string $kind): void
    {
        $now = microtime(true);

        for ($i = count($this->open) - 1; $i >= 0; $i--) {
            if ($this->spans[$this->open[$i]]['kind'] !== $kind) {
                continue;
            }

            while (count($this->open) > $i) {
                $this->closeTop($now);
            }

            return;
        }
    }

    public function isOpen(string $kind): bool
    {
        foreach ($this->open as $index) {
            if ($this->spans[$index]['kind'] === $kind) {
                return true;
            }
        }

        return false;
    }

    private function closeTop(float $now): void
    {
        $index = array_pop($this->open);

        if ($index !== null) {
            $this->spans[$index]['end'] = $now;
            $this->spans[$index]['q1'] = $this->queryCount;
        }
    }

    /**
     * A region or a block starts drawing.
     *
     * @param  array<string, mixed>  $node
     */
    public function enter(array $node): void
    {
        $limit = $this->limit('blocks', 2000);

        if (count($this->nodes) >= $limit) {
            $this->truncated = true;
            $this->drawing[] = -1;

            return;
        }

        $node['parent'] = $this->currentNode();
        $node['start'] = microtime(true);
        $node['q0'] = $this->queryCount;
        $this->nodes[] = $node;
        $this->drawing[] = (int) array_key_last($this->nodes);
    }

    /**
     * The innermost region or block is done.
     *
     * @param  array<string, mixed>  $finish
     */
    public function leave(array $finish = []): void
    {
        $index = array_pop($this->drawing);

        if ($index === null || $index < 0) {
            return;
        }

        $this->nodes[$index] = [...$this->nodes[$index], ...$finish, 'end' => microtime(true), 'q1' => $this->queryCount];
    }

    /** The region or block being drawn now — what a statement is said to be «in». */
    public function currentNode(): ?int
    {
        for ($i = count($this->drawing) - 1; $i >= 0; $i--) {
            if ($this->drawing[$i] >= 0) {
                return $this->drawing[$i];
            }
        }

        return null;
    }

    /** How a region or block is named beside a statement: `block cards (k3)`. */
    public function nodeLabel(?int $index): ?string
    {
        if ($index === null || ! isset($this->nodes[$index])) {
            return null;
        }

        $node = $this->nodes[$index];

        return $node['kind'] === 'region'
            ? 'region '.$node['name']
            : 'block '.$node['type'].' ('.$node['key'].')';
    }

    public function limit(string $name, int $default): int
    {
        return $this->limits[$name] ?? $default;
    }
}
