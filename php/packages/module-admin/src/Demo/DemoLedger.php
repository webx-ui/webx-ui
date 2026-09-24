<?php

declare(strict_types=1);

namespace WebxUi\Admin\Demo;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use RuntimeException;
use WebxUi\Admin\Versions\EntityVersion;

/**
 * What the demo content did, written down so that it can be undone.
 *
 * A file — `storage/app/webx-demo.json` — and not a table: a migration in every client's
 * database, forever, for content that lives until the first real edit is a price paid in the
 * wrong place. The journal is a list in the order things happened, and `webx:demo --remove`
 * plays it backwards, so a page created under another page is taken out before its parent.
 *
 * Three kinds of entry, because a demo does three kinds of thing:
 *
 *  - `created` — a record that was not there before. Removing it deletes it.
 *  - `changed` — a record that was there and was filled in; the home page is the one that
 *    matters, since it comes from a migration and must survive the removal. The raw values of
 *    the named attributes are kept, and removing puts them back.
 *  - `file` — bytes written to a disk by something other than a model.
 *
 * A module records only what it did. What is not in the journal is not removed, which is the
 * whole of the contract: nothing here guesses by slug.
 */
final class DemoLedger
{
    /** The journal's shape, so an older file can be recognised rather than misread. */
    public const VERSION = 1;

    /** @var list<array<string, mixed>> */
    private array $entries = [];

    /** @var list<string> */
    private array $notes = [];

    /** Whose turn it is. The command sets it before handing the ledger to a module. */
    private string $module = '';

    private bool $loaded = false;

    public function __construct(
        private readonly Filesystem $files,
        private readonly FilesystemFactory $disks,
        private readonly string $path,
    ) {}

    /** Everything after this call is recorded as this module's, until the next one. */
    public function forModule(string $id): void
    {
        $this->module = $id;
    }

    /** A record that did not exist before this run. */
    public function created(Model $model, ?string $label = null): void
    {
        $this->entries[] = [
            'module' => $this->module,
            'kind' => 'created',
            'type' => $model::class,
            'id' => $model->getKey(),
            'label' => $label ?? $this->describe($model),
        ];
    }

    /**
     * A record that already existed and is about to be written into.
     *
     * Called *before* the change, because what is kept is what the row holds now — raw, the way
     * the column holds it, so that a translated attribute comes back as its whole map rather
     * than as one language's string laid over the others (CLAUDE.md §4).
     *
     * @param  list<string>  $attributes
     */
    public function changed(Model $model, array $attributes, ?string $label = null): void
    {
        $before = [];

        foreach ($attributes as $attribute) {
            $before[$attribute] = $model->getRawOriginal($attribute);
        }

        $this->entries[] = [
            'module' => $this->module,
            'kind' => 'changed',
            'type' => $model::class,
            'id' => $model->getKey(),
            'before' => $before,
            'label' => $label ?? $this->describe($model),
        ];
    }

    /**
     * The history an entity picked up while it was being seeded.
     *
     * A version is a row of its own with nothing but a morph pointing at its entity, so
     * deleting the entity leaves it behind — and the promise of `--remove` is a database that
     * holds structure and nothing else. Called after publishing, by whoever published.
     */
    public function createdVersionsOf(Model $entity): void
    {
        $versions = EntityVersion::query()
            ->where('versionable_type', $entity->getMorphClass())
            ->where('versionable_id', $entity->getKey())
            ->get();

        foreach ($versions as $version) {
            $this->created($version, class_basename($entity).' history');
        }
    }

    /**
     * Something the person running the command has to be told.
     *
     * A module cannot print — it is handed a journal, not a console — and there is a whole
     * class of thing a demo has to say rather than throw: a half it deliberately left alone,
     * and why. The command prints these and they are not kept in the file: they are about this
     * run, not about what it created.
     */
    public function note(string $message): void
    {
        $this->notes[] = $message;
    }

    /**
     * @return list<string>
     */
    public function takeNotes(): array
    {
        $notes = $this->notes;
        $this->notes = [];

        return $notes;
    }

    /** Bytes put on a disk by hand — what a model wrote goes with the model instead. */
    public function wrote(string $disk, string $path, ?string $label = null): void
    {
        $this->entries[] = [
            'module' => $this->module,
            'kind' => 'file',
            'disk' => $disk,
            'path' => $path,
            'label' => $label ?? $path,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /** How many entries this module has put in so far. */
    public function countFor(string $module): int
    {
        return count(array_filter($this->entries, static fn (array $entry): bool => ($entry['module'] ?? null) === $module));
    }

    /**
     * The modules that have something in the journal, in the order they first wrote — what
     * `webx:demo --module` asks before seeding one of them a second time.
     *
     * @return list<string>
     */
    public function modules(): array
    {
        $modules = [];

        foreach ($this->entries as $entry) {
            $module = $entry['module'] ?? null;

            if (is_string($module) && $module !== '' && ! in_array($module, $modules, true)) {
                $modules[] = $module;
            }
        }

        return $modules;
    }

    /**
     * What another module created in this run — how a demo hangs an article on a picture
     * without guessing at a name, and the reason `requires()` is an order and not only a
     * condition.
     *
     * @param  class-string<Model>  $type
     * @return list<int|string>
     */
    public function idsOf(string $module, string $type): array
    {
        $ids = [];

        foreach ($this->entries as $entry) {
            $id = $entry['id'] ?? null;

            if (($entry['kind'] ?? null) === 'created'
                && ($entry['module'] ?? null) === $module
                && ($entry['type'] ?? null) === $type
                && (is_int($id) || is_string($id))) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function exists(): bool
    {
        return $this->files->exists($this->path);
    }

    public function path(): string
    {
        return $this->path;
    }

    /** Read the journal of an earlier run. Absent is not an error — there is simply nothing. */
    public function load(): void
    {
        $this->entries = [];
        $this->loaded = true;

        if (! $this->files->exists($this->path)) {
            return;
        }

        $document = json_decode((string) $this->files->get($this->path), true);

        if (! is_array($document) || ! is_array($document['entries'] ?? null)) {
            throw new RuntimeException("{$this->path} is not a demo journal.");
        }

        $this->entries = array_values(array_filter($document['entries'], 'is_array'));
    }

    /**
     * Write it out, and do it after every module rather than at the end: a run that fails
     * halfway has to leave behind a journal for what it did manage to create.
     */
    public function save(): void
    {
        $this->files->ensureDirectoryExists(dirname($this->path));
        $this->files->put($this->path, json_encode([
            'version' => self::VERSION,
            'seeded_at' => Carbon::now()->toAtomString(),
            'entries' => $this->entries,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    public function discard(): void
    {
        $this->files->delete($this->path);
        $this->entries = [];
    }

    /**
     * Keep only these — what a removal could not undo, so that trying again still works.
     *
     * @param  list<array<string, mixed>>  $entries
     */
    public function retain(array $entries): void
    {
        $this->entries = $entries;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Undo one entry, and say in words what was undone.
     *
     * A record somebody has already deleted by hand is not a failure: the demo is gone, which
     * is what was being asked for. Anything that refuses — a form with real submissions behind
     * it — throws, and the caller decides what to say about it.
     *
     * @param  array<string, mixed>  $entry
     */
    public function undo(array $entry): void
    {
        match ($entry['kind'] ?? null) {
            'created' => $this->undoCreated($entry),
            'changed' => $this->undoChanged($entry),
            'file' => $this->undoFile($entry),
            default => throw new RuntimeException('Unknown journal entry: '.json_encode($entry)),
        };
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function undoCreated(array $entry): void
    {
        $model = $this->find($entry);

        if ($model === null) {
            return;
        }

        // Force, because a soft delete would leave the row in the table and the point of the
        // removal is that the database holds nothing but structure afterwards. On a model
        // that does not delete softly it is the ordinary delete.
        $model->forceDelete();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function undoChanged(array $entry): void
    {
        $model = $this->find($entry);
        $before = $entry['before'] ?? null;

        if ($model === null || ! is_array($before)) {
            return;
        }

        // Raw, and merged into what is there rather than replacing it: `save()` compares
        // against the original it was loaded with, so only these columns are written.
        $model->setRawAttributes([...$model->getAttributes(), ...$before]);
        $model->save();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function undoFile(array $entry): void
    {
        $disk = $entry['disk'] ?? null;
        $path = $entry['path'] ?? null;

        if (! is_string($disk) || ! is_string($path) || $path === '') {
            return;
        }

        $this->disks->disk($disk)->delete($path);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function find(array $entry): ?Model
    {
        $class = $entry['type'] ?? null;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            // A package that has since been uninstalled. Its tables went with it, so there is
            // nothing left to remove and nothing to complain about either.
            return null;
        }

        /** @var Model $instance */
        $instance = new $class;

        // The scope by name rather than `withTrashed()`, which lives on the trait and not on
        // the builder: a record in the bin is still a record the demo put there.
        return $instance->newQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->find($entry['id'] ?? null);
    }

    private function describe(Model $model): string
    {
        foreach (['slug', 'name', 'key', 'title'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
