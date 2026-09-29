<?php

declare(strict_types=1);

namespace WebxUi\Admin\History\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\HistoryPresenter;
use WebxUi\Admin\History\HistoryReader;
use WebxUi\Admin\History\HistoryType;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The journal for an agent: `history_get`, `history_runs` and the list of types (§6).
 *
 * A module of its own so that the tools are named `history_*` and carry the `history:read`
 * scope; it has no page, and the panel's menu leaves out a module the front end has no routes
 * for. Registered by the frame only once some module registered a type, and only where
 * `webx-ui/mcp` is installed — the frame does not require it, and nothing loads this class
 * otherwise.
 *
 * The tool is behind any permission some type is read behind; which record may be read is the
 * type's own answer, asked again inside the handler — an agent of the catalogue's editor sees a
 * product's history and not an administrator's.
 */
final class HistoryModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(
        private readonly HistoryTypes $types,
        private readonly HistoryReader $reader,
        private readonly HistoryPresenter $presenter,
    ) {}

    public function id(): string
    {
        return 'history';
    }

    public function title(): string
    {
        return 'History';
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        $permissions = $this->readPermissions();

        if ($permissions === []) {
            return [];
        }

        return [
            Tool::read(
                'get',
                'Who changed a record, when, through which door (panel, mcp, import, bulk, api, console) and '
                .'what exactly: each save is one entry with its changes as field, label, from, to. Newest first. '
                .'A long value (rich text) says only that it changed and its length. An entry made inside an '
                .'import or a bulk action names its run — `history_runs` with that `run_id` shows the whole of it. '
                .'The types and their field names are in the history://types resource.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->get($arguments, $user),
                ['properties' => [
                    'subject_type' => ['type' => 'string', 'description' => 'The type, as history://types lists it: `catalog.product`.'],
                    'subject_id' => ['type' => 'integer', 'description' => 'The id of the record.'],
                    'since' => ['type' => 'string', 'description' => 'Only entries from this moment on, ISO 8601 or a date.'],
                    'field' => ['type' => 'string', 'description' => 'Only entries that changed this field (`price`; `name` covers every language of it).'],
                    'page' => ['type' => 'integer', 'description' => 'Twenty entries a page; 1 by default.'],
                ], 'required' => ['subject_type', 'subject_id']],
                permission: $permissions,
            ),

            Tool::read(
                'runs',
                'Imports and bulk actions: one run each, with what was done and how many rows it has, newest first. '
                .'With `run_id`, that run and its rows — one per record it really changed — optionally only the rows '
                .'of one record. Only runs over types you may read are shown.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->runs($arguments, $user),
                ['properties' => [
                    'run_id' => ['type' => 'integer', 'description' => 'One run and its rows instead of the list.'],
                    'subject_id' => ['type' => 'integer', 'description' => 'With `run_id`: only the rows about this record.'],
                    'module' => ['type' => 'string', 'description' => 'Only runs of this module (`catalog`).'],
                    'source' => ['type' => 'string', 'enum' => HistoryContext::SOURCES, 'description' => 'Only runs that came through this door.'],
                    'since' => ['type' => 'string', 'description' => 'Only runs from this moment on.'],
                    'until' => ['type' => 'string', 'description' => 'Only runs up to this moment.'],
                    'page' => ['type' => 'integer', 'description' => 'Twenty a page; 1 by default.'],
                ]],
                permission: $permissions,
            ),
        ];
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        if ($this->types->all() === []) {
            return [];
        }

        return [
            new McpResource(
                'history://types',
                'History types',
                'Every kind of record the journal is kept for: its type, its module, its fields with their '
                .'labels, and the permission its history is read behind. What `history_get` can be asked about.',
                fn (): array => [
                    'types' => array_values(array_map(
                        static fn (HistoryType $type): array => $type->describe(),
                        $this->types->all(),
                    )),
                ],
            ),
        ];
    }

    /**
     * Every permission some type's history is behind: the door the tool stands at.
     *
     * @return list<string>
     */
    private function readPermissions(): array
    {
        $all = [];

        foreach ($this->types->all() as $type) {
            array_push($all, ...$type->permissions);
        }

        return array_values(array_unique($all));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments, ?Authenticatable $user): array
    {
        $type = $this->type($arguments['subject_type'] ?? null, $user);
        $id = $arguments['subject_id'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw new ToolFailure('`subject_id` must be the id of the record.');
        }

        $field = $arguments['field'] ?? null;

        $page = $this->reader->forSubject(
            $type,
            (int) $id,
            $this->moment($arguments['since'] ?? null, 'since'),
            is_string($field) ? $field : null,
            $this->page($arguments),
        );

        return [
            'subject_type' => $type->type,
            'subject_id' => (int) $id,
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'entries' => array_map(fn (HistoryEntry $entry): array => $this->presenter->row($entry), $page->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runs(array $arguments, ?Authenticatable $user): array
    {
        $runId = $arguments['run_id'] ?? null;

        if ($runId !== null) {
            return $this->run($runId, $arguments, $user);
        }

        $module = $arguments['module'] ?? null;
        $types = array_keys(array_filter(
            $this->types->all(),
            fn (HistoryType $type): bool => $this->reads($type, $user)
                && (! is_string($module) || $module === '' || $type->module === $module),
        ));

        $source = $arguments['source'] ?? null;

        $page = $this->reader->runs(
            $types,
            is_string($source) ? $source : null,
            $this->moment($arguments['since'] ?? null, 'since'),
            $this->moment($arguments['until'] ?? null, 'until'),
            $this->page($arguments),
        );

        return [
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'runs' => array_map(fn (HistoryEntry $run): array => $this->presenter->row($run), $page->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function run(mixed $runId, array $arguments, ?Authenticatable $user): array
    {
        $run = is_int($runId) || (is_string($runId) && ctype_digit($runId))
            ? HistoryEntry::query()->where('event', HistoryEntry::RUN)->find((int) $runId)
            : null;

        if (! $run instanceof HistoryEntry) {
            throw new ToolFailure('There is no run '.(is_scalar($runId) ? '"'.$runId.'"' : '(none given)').'. `history_runs` without `run_id` lists them.');
        }

        $this->type($run->subject_type, $user);

        $subject = $arguments['subject_id'] ?? null;
        $rows = $this->reader->rowsOf(
            $run,
            is_int($subject) || (is_string($subject) && ctype_digit($subject)) ? (int) $subject : null,
            $this->page($arguments),
        );

        return [
            'run' => $this->presenter->row($run) + ['rows' => $run->rows()->count()],
            'page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
            'entries' => array_map(fn (HistoryEntry $entry): array => $this->presenter->row($entry), $rows->items()),
        ];
    }

    /** A registered type this caller may read, or the refusal that says which it is not. */
    private function type(mixed $name, ?Authenticatable $user): HistoryType
    {
        $type = is_string($name) ? $this->types->find($name) : null;

        if ($type === null) {
            throw new ToolFailure(
                'There is no history type '.(is_scalar($name) ? '"'.$name.'"' : '(none given)').'. The history://types resource lists them.'
            );
        }

        if (! $this->reads($type, $user)) {
            throw new ToolFailure(sprintf(
                'The administrator this call acts as may not read the history of %s: it needs [%s].',
                $type->type,
                implode('] or [', $type->permissions),
            ));
        }

        return $type;
    }

    /**
     * The type's permission, asked of an administrator who has permissions at all — the rule of
     * `webx-ui/mcp`: the local stdio server has nobody behind it and is not refused.
     */
    private function reads(HistoryType $type, ?Authenticatable $user): bool
    {
        return ! $user instanceof HasPermissions || $type->allows($user);
    }

    private function moment(mixed $value, string $name): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse(is_string($value) ? $value : '');
        } catch (Throwable) {
            throw new ToolFailure("`{$name}` must be a moment: ISO 8601 or a date.");
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function page(array $arguments): int
    {
        $page = $arguments['page'] ?? 1;

        return is_numeric($page) ? max(1, (int) $page) : 1;
    }
}
