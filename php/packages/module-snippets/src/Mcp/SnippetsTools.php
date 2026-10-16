<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Mcp;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Output\Codes;
use WebxUi\Snippets\Panel\SnippetForm;
use WebxUi\Snippets\Panel\SnippetOrder;
use WebxUi\Snippets\Panel\SnippetsModule;
use WebxUi\Snippets\Places;
use WebxUi\Snippets\SnippetValues;

/**
 * What an agent can do with the codes of outside services (§9 of the snippets spec).
 *
 * The same doors the panel uses: values go through {@see SnippetForm} — the rules of
 * {@see SnippetValues} on every path — and the order through {@see SnippetOrder}. Every tool is
 * behind the one permission of the section, `snippets.manage`: reading a code is reading the keys
 * of somebody's services, and writing one is running JavaScript on every page.
 *
 * A place is checked here before the form sees it, so the refusal names the places the site has —
 * the form's own words say only that the one given is not declared.
 *
 * A dry run is the write itself, rolled back ({@see Rehearsal}). An argument the tool does not take
 * is refused rather than dropped ({@see Arguments}).
 */
final class SnippetsTools
{
    /** The opening of every write's description (§9.1): the agent reads this before it decides. */
    private const WARNING = 'Writing a snippet runs its JavaScript on every page of the site it covers, for every visitor — '
        .'treat it as deploying code. Use dry_run first; never paste code the person did not give you or a service did not issue. ';

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $snippet = ['type' => 'integer', 'description' => 'The snippet id; snippets_list has them all.'];
        $fields = [
            'name' => ['type' => 'string', 'description' => 'What the administrators call it — the service, not the code: "Tag manager", "Chat".'],
            'place' => ['type' => 'string', 'description' => 'A key of a declared place: head, body_start, body_end, or one of the site\'s own. snippets://places lists them.'],
            'code' => ['type' => 'string', 'description' => 'The HTML and JavaScript as the service gave it, up to 65536 bytes. No <html>, <head> or <body>: the answer warns about them.'],
            'consent' => ['type' => 'string', 'enum' => Snippet::CATEGORIES, 'description' => 'The cookie category it waits for. necessary is printed at once; the others only after the visitor agrees to theirs. necessary when omitted.'],
            'only_paths' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Only on these paths, from the root without the language prefix, * for any run: ["/blog/*"]. Empty is the whole site.'],
            'except_paths' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Never on these paths, in the same form.'],
            'locales' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Only on pages in these languages of the site. Empty is every language.'],
            'on_stage' => ['type' => 'boolean', 'description' => 'Printed on dev and stage copies too. False when omitted: a counter there counts visits that never happened.'],
            'enabled' => ['type' => 'boolean', 'description' => 'On the site at once. False when omitted — pass true only when a person asked for that.'],
        ];

        return Arguments::strictAll([
            Tool::read(
                'list',
                'The snippets by place, in the order each place prints them: name, place, whether the place is still '
                .'declared, consent category, paths, languages, on_stage, enabled and the size of the code — not the '
                .'code itself (snippets_get has it). Places nobody declares any more come last, their snippets '
                .'printed nowhere. Read this (or snippets://catalog) first: a second counter of the same service '
                .'counts every visit twice.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'place' => ['type' => 'string', 'description' => 'Only this place.'],
                    'search' => ['type' => 'string', 'description' => 'Snippets whose name or code contains this — an id of a service, a domain.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first.'],
                ]],
                permission: SnippetsModule::PERMISSION,
            ),

            Tool::read(
                'get',
                'One snippet in full: its code, where it is printed and to whom, and warnings about the code.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['snippet' => $snippet], 'required' => ['snippet']],
                permission: SnippetsModule::PERMISSION,
            ),

            Tool::mutating(
                'create',
                self::WARNING.'Add a snippet at the end of its place. Switched off unless enabled: true is passed. The '
                .'answer carries warnings that do not stop the save — read them.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->create($arguments)),
                ['properties' => $fields, 'required' => ['name', 'place', 'code']],
                permission: SnippetsModule::PERMISSION,
            ),

            Tool::mutating(
                'update',
                self::WARNING.'Change a snippet: any field of snippets_create, the ones left out keep what they had. It is '
                .'on the site at once — snippets have no draft; switching one on or off is enabled. Moved to another '
                .'place, it goes to the end of that place.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->update($arguments)),
                ['properties' => ['snippet' => $snippet] + $fields, 'required' => ['snippet']],
                permission: SnippetsModule::PERMISSION,
            ),

            Tool::mutating(
                'delete',
                self::WARNING.'Put a snippet in the bin: off the site at once. The bin in the panel brings it back with '
                .'everything it had; deleting for good is the panel\'s too.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['snippet' => $snippet], 'required' => ['snippet']],
                permission: SnippetsModule::PERMISSION,
            ),

            Tool::mutating(
                'reorder',
                self::WARNING.'Put the snippets of one place in a new order — the order the page runs them in. Name '
                .'every snippet of the place, each once, first to last; moving one to another place is snippets_update '
                .'with place.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'place' => ['type' => 'string', 'description' => 'The place whose order this is.'],
                    'snippets' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Every snippet id of the place, first to last.'],
                ], 'required' => ['place', 'snippets']],
                permission: SnippetsModule::PERMISSION,
            ),
        ], 'snippets_');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $search = trim((string) ($arguments['search'] ?? ''));
        $only = isset($arguments['place']) && $arguments['place'] !== '' ? (string) $arguments['place'] : null;

        $query = Snippet::query();

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static fn ($where) => $where->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        if ($only !== null) {
            $query->where('place', $only);
        }

        if (($arguments['trashed'] ?? false) === true) {
            return [
                'trashed' => true,
                'snippets' => $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id')->get()
                    ->map(fn (Snippet $one): array => $this->form()->row($one))
                    ->values()
                    ->all(),
            ];
        }

        $byPlace = $query->orderBy('position')->orderBy('id')->get()->groupBy('place');
        $keys = $this->places()->keys();

        // Declared places in the order of the config, empty ones too — an agent choosing where to
        // put a code sees every choice; then the undeclared ones that still hold codes.
        foreach ($byPlace->keys() as $key) {
            if (! in_array((string) $key, $keys, true)) {
                $keys[] = (string) $key;
            }
        }

        if ($only !== null) {
            $keys = array_values(array_filter($keys, static fn (string $key): bool => $key === $only));
        }

        $places = [];

        foreach ($keys as $key) {
            $places[] = [
                'key' => $key,
                'label' => $this->places()->label($key),
                'declared' => $this->places()->has($key),
                'snippets' => array_map(
                    fn (Snippet $one): array => $this->form()->row($one),
                    $byPlace->get($key)?->all() ?? [],
                ),
            ];
        }

        return [
            'environment' => app()->environment(),
            'everything_printed' => app()->environment($this->environments()),
            'places' => $places,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $snippet = $this->snippet($arguments['snippet'] ?? null);

        return $this->form()->describe($snippet, $this->values()->warnings($snippet->code));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments): array
    {
        $values = $this->fields($arguments);
        $this->declared((string) ($values['place'] ?? ''));

        $work = function () use ($values): array {
            $saved = $this->form()->save(new Snippet, $values);

            return $this->form()->describe($saved['snippet'], $saved['warnings']);
        };

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        $would = $this->rehearse($work);
        // The id the rehearsal was given is nobody's once it is rolled back.
        $would['snippet']['id'] = null;

        return ['dry_run' => true] + $would;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments): array
    {
        $snippet = $this->snippet($arguments['snippet'] ?? null);
        $values = $this->fields($arguments);

        if ($values === []) {
            throw new ToolFailure('Nothing to change: pass at least one field of snippets_create. snippets_get says what the snippet has.');
        }

        if ($snippet->trashed()) {
            throw new ToolFailure("Snippet {$snippet->id} is in the bin. Bring it back in the panel before editing it.");
        }

        $place = array_key_exists('place', $values) ? (string) $values['place'] : $snippet->place;

        if ($place !== $snippet->place) {
            $this->declared($place);
        } elseif (! $this->places()->has($place) && (bool) ($values['enabled'] ?? $snippet->enabled)) {
            throw new ToolFailure("Snippet {$snippet->id} is in place [{$place}], which is no longer declared; move it with snippets_update {place} before enabling it. {$this->declaredList()}");
        }

        $work = function () use ($snippet, $values): array {
            $saved = $this->form()->save($snippet, $values);

            return $this->form()->describe($saved['snippet'], $saved['warnings']);
        };

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        return ['dry_run' => true, 'fields' => array_keys($values)] + $this->rehearse($work);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $snippet = $this->snippet($arguments['snippet'] ?? null);

        if ($snippet->trashed()) {
            return ['trashed' => true, 'id' => $snippet->id, 'already' => true];
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->form()->row($snippet)];
        }

        $snippet->delete();

        return ['trashed' => true, 'id' => $snippet->id];
    }

    /**
     * The whole place, each snippet once: a partial list would leave the rest to be numbered by
     * a guess, and the order of a place is the order the page runs its scripts in.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $place = is_string($arguments['place'] ?? null) ? trim($arguments['place']) : '';
        $given = $arguments['snippets'] ?? null;

        $this->declared($place);

        if (! is_array($given) || $given === [] || ! array_is_list($given)) {
            throw new ToolFailure('`snippets` is the list of every snippet id of the place, first to last.');
        }

        $ids = [];

        foreach ($given as $one) {
            $snippet = $this->snippet($one);

            if ($snippet->trashed()) {
                throw new ToolFailure("Snippet {$snippet->id} is in the bin; leave it out of the order.");
            }

            if ($snippet->place !== $place) {
                throw new ToolFailure("Snippet {$snippet->id} is in place [{$snippet->place}], not [{$place}]. Move it with snippets_update {place} first.");
            }

            $ids[] = $snippet->id;
        }

        $all = Snippet::query()->where('place', $place)->pluck('id')->map(intval(...))->all();
        $missing = array_values(array_diff($all, $ids));
        $twice = array_values(array_unique(array_diff_assoc($ids, array_unique($ids))));

        $wrong = array_filter([
            $missing === [] ? null : 'missing '.implode(', ', $missing),
            $twice === [] ? null : 'twice '.implode(', ', $twice),
        ]);

        if ($wrong !== []) {
            throw new ToolFailure("`snippets` must list every snippet of place [{$place}], each once: ".implode('; ', $wrong).'.');
        }

        $work = function () use ($place, $ids): array {
            $this->container->make(SnippetOrder::class)->reorder($place, $ids);

            return $this->list(['place' => $place]);
        };

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        return ['dry_run' => true, 'would_order' => $ids] + $this->rehearse($work);
    }

    /**
     * The fields of a snippet among the arguments, as the form takes them.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function fields(array $arguments): array
    {
        return array_intersect_key($arguments, array_flip(SnippetValues::FIELDS));
    }

    /**
     * A place a snippet may go to: refused with the list of the ones there are.
     */
    private function declared(string $place): void
    {
        if ($place === '') {
            throw new ToolFailure('`place` cannot be empty. '.$this->declaredList());
        }

        if (! $this->places()->has($place)) {
            throw new ToolFailure("Place [{$place}] is not declared. {$this->declaredList()}");
        }
    }

    private function declaredList(): string
    {
        return 'The places this site has: '.implode(', ', $this->places()->keys()).' — snippets://places describes them.';
    }

    private function snippet(mixed $reference): Snippet
    {
        if (! is_int($reference) && ! (is_string($reference) && ctype_digit($reference))) {
            throw new ToolFailure('A snippet is its id, a number. snippets_list has them all.');
        }

        $snippet = Snippet::withTrashed()->find((int) $reference);

        return $snippet instanceof Snippet
            ? $snippet
            : throw new ToolFailure("No snippet has the id [{$reference}]. snippets_list has them all.");
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function rehearse(Closure $work): mixed
    {
        try {
            return Rehearsal::run($work, (new Snippet)->getConnectionName());
        } finally {
            // Whatever the rehearsal saw cached is not what the table holds once it is rolled back.
            $this->container->make(Codes::class)->forget();
        }
    }

    /**
     * Run a change, and turn a refusal of the form into something the agent can read.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }
    }

    /**
     * @return list<string>
     */
    private function environments(): array
    {
        return array_values(array_filter((array) config('webx-snippets.environments', ['production']), is_string(...)));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function form(): SnippetForm
    {
        return $this->container->make(SnippetForm::class);
    }

    private function values(): SnippetValues
    {
        return $this->container->make(SnippetValues::class);
    }

    private function places(): Places
    {
        return $this->container->make(Places::class);
    }
}
