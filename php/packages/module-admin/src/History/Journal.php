<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Writes the journal: what `History::record()` and `History::run()` stand for (§4).
 *
 * The source and the author are never arguments. They are the context's — the doors set it — so
 * that a module cannot write "panel" for what an agent did, and does not have to thread an
 * administrator through every method that might save something.
 *
 * A row is written on the connection it is asked on, inside whatever transaction the caller
 * has open: a save that is rolled back takes its row with it (§4, last point).
 */
final class Journal
{
    public function __construct(
        private readonly HistoryTypes $types,
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('webx-admin.history.enabled', true);
    }

    public function types(): HistoryTypes
    {
        return $this->types;
    }

    /**
     * Write one row about a record, under the type it was registered as.
     *
     * `changes` is `[{ field, label?, from, to }]` or `field => [from, to]`; an `updated` with
     * nothing in it is not written at all — a save that changed nothing is not an event.
     *
     * @param  array<array-key, mixed>  $changes
     */
    public function record(Model $subject, string $event, array $changes = []): ?HistoryEntry
    {
        if (! $this->enabled()) {
            return null;
        }

        $key = $subject->getKey();

        return $this->write(
            $this->types->of($subject)->type,
            is_numeric($key) ? (int) $key : null,
            $event,
            $changes,
        );
    }

    /**
     * The same, for a type without a model behind it or a record known only by its id.
     *
     * @param  array<array-key, mixed>  $changes
     */
    public function recordFor(string $type, ?int $id, string $event, array $changes = []): ?HistoryEntry
    {
        if (! $this->enabled()) {
            return null;
        }

        if ($this->types->find($type) === null) {
            throw new InvalidArgumentException("[{$type}] is not a registered history type.");
        }

        return $this->write($type, $id, $event, $changes);
    }

    /**
     * Open a run — an import, a bulk action — and put everything written inside `$work` under
     * it (§2, point 3). What a row inside says is only what that record really changed; the run
     * says what was done to all of them, and ends up knowing how many rows it has.
     *
     * The run is not rolled back with its rows: an import that fails half way has done half its
     * work, and the run is where the journal says so (`summary.failed`).
     *
     * @template T
     *
     * @param  array<string, mixed>  $summary  what was done, how many, with which profile
     * @param  Closure(HistoryEntry|null): T  $work
     * @param  string|null  $source  `import`, `bulk`… — the door's own when omitted
     * @return T
     */
    public function run(string $type, array $summary, Closure $work, ?string $source = null): mixed
    {
        if (! $this->enabled()) {
            return $work(null);
        }

        if ($this->types->find($type) === null) {
            throw new InvalidArgumentException("[{$type}] is not a registered history type.");
        }

        $context = $this->context();

        // The run row itself stands under the run it was opened in, if any, and in its own source.
        $run = $context->inRun(
            $context->runId(),
            $source,
            fn (): HistoryEntry => $this->entry($type, null, HistoryEntry::RUN, null, $summary),
        );

        try {
            $result = $context->inRun($run->id, $source, static fn (): mixed => $work($run));
        } catch (Throwable $failure) {
            $this->close($run, ['failed' => $failure->getMessage()]);

            throw $failure;
        }

        $this->close($run, []);

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $changes
     */
    private function write(string $type, ?int $id, string $event, array $changes): ?HistoryEntry
    {
        if (! in_array($event, HistoryEntry::EVENTS, true) || $event === HistoryEntry::RUN) {
            throw new InvalidArgumentException("[{$event}] is not an event a record can have.");
        }

        $rows = $this->changes()->normalise($changes);

        if ($event === HistoryEntry::UPDATED && $rows === []) {
            return null;
        }

        return $this->entry($type, $id, $event, $rows === [] ? null : $rows, null);
    }

    /**
     * @param  list<array<string, mixed>>|null  $changes
     * @param  array<string, mixed>|null  $summary
     */
    private function entry(string $type, ?int $id, string $event, ?array $changes, ?array $summary): HistoryEntry
    {
        $context = $this->context();

        return HistoryEntry::query()->create([
            'parent_id' => $context->runId(),
            'subject_type' => $type,
            'subject_id' => $id,
            'event' => $event,
            'source' => $context->source(),
            'admin_id' => $context->adminId(),
            'admin_name' => $context->adminName(),
            'grant_id' => $context->grantId(),
            'changes' => $changes,
            'summary' => $summary,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function close(HistoryEntry $run, array $extra): void
    {
        $run->forceFill([
            'summary' => ($run->summary ?? []) + $extra + ['rows' => $run->rows()->count()],
        ])->save();
    }

    private function changes(): Changes
    {
        return new Changes(max(1, (int) $this->config->get('webx-admin.history.long_value', 500)));
    }

    /**
     * Asked for on every write rather than held: the context belongs to the request, and this
     * outlives one in a worker.
     */
    private function context(): HistoryContext
    {
        return $this->container->make(HistoryContext::class);
    }
}
