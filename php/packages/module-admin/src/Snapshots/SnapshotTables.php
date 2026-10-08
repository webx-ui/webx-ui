<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

use Closure;
use Illuminate\Contracts\Config\Repository;

/**
 * Which table belongs to which group (§ "moving content between stands" in the guide).
 *
 * Every package declares its own tables from its service provider:
 *
 *     $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
 *         $tables->content('faq_questions', 'faq_categories', 'faq_category_question');
 *     });
 *
 * The libraries that do not depend on this package (`routing`, `localization`) are declared
 * here on their behalf, and so are Laravel's own tables. A site adds or overrides with
 * `webx-admin.snapshot.tables` — its own tables are the ones nobody else can know about.
 *
 * A table nobody declared travels only with `--all`, and both commands name it: guessing would
 * either carry a site's customer list to a laptop or leave its own content behind, and either is
 * worse than a line in the output.
 */
final class SnapshotTables
{
    /** @var array<string, TableGroup> Exact names; a trailing `*` is a prefix. */
    private array $tables = [];

    /** @var array<string, array{column: string, values: Closure(): list<int|string>}> */
    private array $preserved = [];

    /** @var array<string, array{command: string, parameters: array<string, mixed>}> */
    private array $afterRestore = [];

    public function __construct(private readonly Repository $config)
    {
        $this->transient('sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', $this->migrationsTable());
        $this->stand('users', 'password_reset_tokens', 'password_resets', 'personal_access_tokens', 'oauth_*');

        // The frame's own: versions, notes and relations hang off content and move with it; the
        // journal and the half-finished uploads are what happened on this stand.
        $this->content('cms_versions', 'cms_notes', 'cms_relations');
        $this->stand('cms_history', 'cms_uploads');

        // `routing` and `localization` are libraries and do not know this package exists.
        $this->content('routes', 'routes_trashed', 'locales');
        $this->afterRestore('webx:locales:clear');
    }

    public function content(string ...$tables): self
    {
        return $this->declare(TableGroup::Content, ...$tables);
    }

    public function admins(string ...$tables): self
    {
        return $this->declare(TableGroup::Admins, ...$tables);
    }

    public function stand(string ...$tables): self
    {
        return $this->declare(TableGroup::Stand, ...$tables);
    }

    public function derived(string ...$tables): self
    {
        return $this->declare(TableGroup::Derived, ...$tables);
    }

    public function transient(string ...$tables): self
    {
        return $this->declare(TableGroup::Transient, ...$tables);
    }

    public function declare(TableGroup $group, string ...$tables): self
    {
        foreach ($tables as $table) {
            $this->tables[$table] = $group;
        }

        return $this;
    }

    /**
     * Rows of a travelling table that stay as they are on the target: the stand's own values
     * inside a content table. `values` is asked at restore time, on the target, and answers the
     * values of `column` whose rows are kept — the archive's rows with those values are dropped.
     *
     * @param  Closure(): list<int|string>  $values
     */
    public function preserve(string $table, string $column, Closure $values): self
    {
        $this->preserved[$table] = ['column' => $column, 'values' => $values];

        return $this;
    }

    /**
     * @return array{column: string, values: list<int|string>}|null
     */
    public function preservedRows(string $table): ?array
    {
        $rule = $this->preserved[$table] ?? null;

        if ($rule === null) {
            return null;
        }

        $values = array_values(array_unique(($rule['values'])()));

        return $values === [] ? null : ['column' => $rule['column'], 'values' => $values];
    }

    /**
     * A command a restore runs once the content is in: a cache only the package that keeps it
     * knows how to drop, a sitemap built from the pages that just arrived. A command the
     * application does not have is skipped.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function afterRestore(string $command, array $parameters = []): self
    {
        $this->afterRestore[$command.json_encode($parameters)] = ['command' => $command, 'parameters' => $parameters];

        return $this;
    }

    /**
     * @return list<array{command: string, parameters: array<string, mixed>}>
     */
    public function afterRestoreCommands(): array
    {
        return array_values($this->afterRestore);
    }

    /**
     * The group of a table, or null when nobody declared it. The site's config wins, then an
     * exact name, then the longest prefix.
     */
    public function groupOf(string $table): ?TableGroup
    {
        if ($table === $this->migrationsTable()) {
            return TableGroup::Transient;
        }

        $configured = $this->config->get('webx-admin.snapshot.tables', []);

        foreach (is_array($configured) ? $configured : [] as $group => $names) {
            $group = TableGroup::tryFrom((string) $group);

            if ($group !== null && is_array($names) && $this->matches($table, array_map(strval(...), $names))) {
                return $group;
            }
        }

        if (isset($this->tables[$table])) {
            return $this->tables[$table];
        }

        $best = null;
        $length = -1;

        foreach ($this->tables as $name => $group) {
            if (str_ends_with($name, '*') && str_starts_with($table, substr($name, 0, -1)) && strlen($name) > $length) {
                $best = $group;
                $length = strlen($name);
            }
        }

        return $best;
    }

    /**
     * Whether a table goes into an archive or comes out of one under these options. A table
     * nobody declared goes only with `--all`.
     */
    public function travels(string $table, bool $all, bool $withAdmins): bool
    {
        $group = $this->groupOf($table);

        if ($group === null) {
            return $all;
        }

        return $group->travels($all, $withAdmins);
    }

    public function migrationsTable(): string
    {
        $table = $this->config->get('database.migrations', 'migrations');

        // Laravel 11 made it `['table' => …, 'update_date_on_publish' => …]`.
        if (is_array($table)) {
            $table = $table['table'] ?? 'migrations';
        }

        return (string) $table;
    }

    /**
     * @param  list<string>  $names
     */
    private function matches(string $table, array $names): bool
    {
        foreach ($names as $name) {
            if ($name === $table || (str_ends_with($name, '*') && str_starts_with($table, substr($name, 0, -1)))) {
                return true;
            }
        }

        return false;
    }
}
