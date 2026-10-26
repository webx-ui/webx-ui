<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use WebxUi\Admin\Editing\AgentRevision;
use WebxUi\Admin\Editing\Presence;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Configurator\Panel\Refused;
use WebxUi\Configurator\Panel\Revision;
use WebxUi\Configurator\Pricing\Quoter;
use WebxUi\Configurator\Pricing\Texts;
use WebxUi\Configurator\Schema\Problem;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;

/**
 * What an agent can do with the configurators (§14) — enough to build one end to end: read the
 * schema reference, check a document, create it, price a few combinations, publish it.
 *
 * Writes go through {@see ConfiguratorWriter}, the same rules the panel's editor keeps: a draft is
 * saved with problems and says what they are, a publication is refused with them. Prices go
 * through {@see Quoter}, the same function a request from the site will be priced by.
 *
 * A configurator is named by its id or by its key. A dry run is the write itself, rolled back
 * ({@see Rehearsal}), and answers what the write would answer.
 */
final class ConfiguratorTools
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $configurator = [
            'type' => ['integer', 'string'],
            'description' => 'The configurator: its id, or its key — "shower". configurators_list has both.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string (the default one), or every language as { "en": "…", "uk": "…" }.',
        ];
        $schema = [
            'type' => 'object',
            'description' => 'The whole schema document, as configurators://schema describes it. Not merged: what is sent replaces what was there.',
        ];
        $values = [
            'type' => 'object',
            'description' => 'Step key → option value, size field key → number. What is left out starts as the schema says (initial, then the defaults of the chosen options).',
        ];

        return Arguments::strictAll([
            Tool::read(
                'list',
                'Every configurator: key, title, whether it is on the site (draft, published, modified, unpublished), '
                .'the request form, how many steps. Read it (or configurators://catalog) first: a second '
                .'configurator for the same product is a duplicate, not a variant.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin instead.'],
                ]],
                permission: ['configurators.view', 'configurators.manage'],
            ),

            Tool::read(
                'get',
                'One configurator in full: its schema document, title and form — the draft when there is one '
                .'(which: "draft"), or what the site prices by (which: "live") — the problems the draft '
                .'has, the revision to send back with a write, and who has it open in the panel.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->get($arguments)),
                ['properties' => [
                    'configurator' => $configurator,
                    'which' => ['type' => 'string', 'enum' => ['draft', 'live'], 'description' => 'draft (default): what is being edited; live: what the site shows.'],
                ], 'required' => ['configurator']],
                permission: ['configurators.view', 'configurators.manage'],
            ),

            Tool::mutating(
                'create',
                'Start a configurator. It is a draft: nothing is on the site until configurators_publish. The key '
                .'is what a block names it by and is fixed once it has been published. A schema with problems '
                .'is saved anyway — the answer lists them; it cannot be published until they are gone. '
                .'Check the document with configurators_validate first; a picture is a media library key — the '
                .'path media_get_file answers with, not the id media_upload_from_url gives.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'key' => ['type' => 'string', 'description' => 'A slug: a-z, 0-9 and dashes — "shower".'],
                    'title' => $text,
                    'schema' => ['description' => 'The whole schema document, as configurators://schema describes it; a blank one with no steps when omitted.'] + $schema,
                    'form' => ['type' => ['string', 'null'], 'description' => 'The slug of the inbox form a request goes to (inbox_forms_list). Omit for none.'],
                ], 'required' => ['key', 'title']],
                permission: 'configurators.manage',
            ),

            Tool::mutating(
                'update',
                'Change the draft of a configurator: the schema (the whole document), the title, the form, and '
                .'the key while it has never been published. What is left out is kept. Send the revision '
                .'configurators_get gave you. Saved with problems, as the panel saves; the answer lists them.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'configurator' => $configurator,
                    'schema' => $schema,
                    'title' => $text,
                    'form' => ['type' => ['string', 'null'], 'description' => 'The slug of the inbox form; null to send no requests.'],
                    'key' => ['type' => 'string', 'description' => 'A new key — only while it has never been on the site.'],
                    ...AgentRevision::properties('configurators_get'),
                ], 'required' => ['configurator']],
                permission: 'configurators.manage',
            ),

            Tool::mutating(
                'publish',
                'Put the draft on the site: the site and the requests it sends are priced by it from now on, '
                .'and a version is written. Refused while the schema has problems — the refusal lists them. '
                .'Ask a person first unless they asked you to publish.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->publish($arguments, $user)),
                ['properties' => ['configurator' => $configurator, ...AgentRevision::stateProperties('configurators_get')], 'required' => ['configurator']],
                permission: 'configurators.manage',
            ),

            Tool::mutating(
                'unpublish',
                'Take a configurator off the site: its blocks show nothing and no request is priced by it. The '
                .'draft and the history stay.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->unpublish($arguments)),
                ['properties' => ['configurator' => $configurator, ...AgentRevision::stateProperties('configurators_get')], 'required' => ['configurator']],
                permission: 'configurators.manage',
            ),

            Tool::mutating(
                'discard',
                'Throw away the draft of a configurator that is on the site and go back to what the site shows.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->discard($arguments)),
                ['properties' => ['configurator' => $configurator, ...AgentRevision::stateProperties('configurators_get')], 'required' => ['configurator']],
                permission: 'configurators.manage',
            ),

            Tool::read(
                'versions',
                'The history of a configurator: every publication, newest first — number, date, who — and which '
                .'one the site shows. With configurators_version_restore, the way to undo a bad edit.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->versions($arguments, $user)),
                ['properties' => ['configurator' => $configurator], 'required' => ['configurator']],
                permission: ['configurators.view', 'configurators.manage'],
            ),

            Tool::mutating(
                'version_restore',
                'Put an old publication back into the draft. The site does not change until somebody publishes.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->versionRestore($arguments)),
                ['properties' => [
                    'configurator' => $configurator,
                    'number' => ['type' => 'integer', 'description' => 'A version number, as configurators_versions lists it.'],
                    ...AgentRevision::stateProperties('configurators_get'),
                ], 'required' => ['configurator', 'number']],
                permission: 'configurators.manage',
            ),

            Tool::mutating(
                'delete',
                'Put a configurator in the bin: it leaves the site at once, and its key stays taken so that the '
                .'bin in the panel can bring it back as it was.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['configurator' => $configurator, ...AgentRevision::stateProperties('configurators_get')], 'required' => ['configurator']],
                permission: 'configurators.manage',
            ),

            Tool::read(
                'quote',
                'The price of a combination, counted by the server exactly as a request from the site is: the '
                .'values as they resolve (normalized — a hidden step drops out, a choice no longer offered moves '
                .'to the first one that is), what is hidden, the area, the total before and after the discount, '
                .'and the breakdown line by line. Of a configurator (the live schema, or draft: true), or of a '
                .'schema sent here and written nowhere. actions replay a visitor: {"select": [step, value]} puts '
                .'the option\'s defaults into the sizes, {"set": [field, number]} pulls a size into its limits. '
                .'A size sent outside its limits is an error, not clamped. values are resolved before actions: a value '
                .'of a step hidden at that point drops out — name the step it depends on in values too.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->quote($arguments)),
                ['properties' => [
                    'configurator' => ['description' => 'The configurator to price: its id, or its key — "shower". Or send schema instead.'] + $configurator,
                    'schema' => ['type' => 'object', 'description' => 'A schema document to price without writing it anywhere.'],
                    'draft' => ['type' => 'boolean', 'description' => 'Price the draft rather than what the site shows.'],
                    'values' => $values,
                    'actions' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Visitor actions after the values are resolved, in order: {"select": ["glass", "graphite"]}, {"set": ["h", 1900]}.'],
                    'today' => ['type' => 'string', 'description' => 'The day to check the discount against, YYYY-MM-DD; today when omitted.'],
                ]],
                permission: ['configurators.view', 'configurators.manage'],
            ),

            Tool::read(
                'validate',
                'Check a schema document without writing it: every problem with its path (steps.2.options.0.area), '
                .'code and words. The same check a publication is held to; configurators://schema lists the codes.',
                fn (array $arguments): array => $this->validate($arguments),
                ['properties' => ['schema' => ['type' => 'object', 'description' => 'The schema document.']], 'required' => ['schema']],
                permission: ['configurators.view', 'configurators.manage'],
            ),
        ], 'configurators_');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = Configurator::query()->orderBy('id');

        if (($arguments['trashed'] ?? false) === true) {
            $query->onlyTrashed();
        }

        $configurators = $query->get();

        return [
            'count' => $configurators->count(),
            'configurators' => $configurators->map(fn (Configurator $one): array => $this->summary($one))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $configurator = $this->configurator($arguments['configurator'] ?? null);
        $which = $arguments['which'] ?? 'draft';

        if ($which !== 'draft' && $which !== 'live') {
            throw new ToolFailure('`which` is draft or live.');
        }

        $shown = $which === 'live'
            ? ['title' => $configurator->getTranslations('title'), 'schema' => $configurator->schema, 'form' => $configurator->form]
            : $configurator->editing();

        return [
            'configurator' => $this->summary($configurator),
            'which' => $which,
            ...$shown,
            // The problems of the draft — what a publication would refuse.
            'errors' => $this->problems($this->writer()->problems($configurator)),
            // Send it back with configurators_update, and a write over somebody else's is refused.
            'revision' => Revision::of($configurator),
            'being_edited_by' => $this->container->make(Presence::class)->of($configurator),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $key = $arguments['key'] ?? null;
        $schema = $this->schema($arguments, required: false);
        $form = $this->form($arguments['form'] ?? null);

        if (! is_string($key)) {
            throw new ToolFailure('`key` is a slug like "shower".');
        }

        $title = $this->text($arguments['title'] ?? null, 'title');

        $work = function () use ($key, $title, $schema, $form, $user): array {
            $configurator = $this->writer()->create($key, $title, $schema, $form, $this->authorId($user), EntityVersion::SOURCE_MCP);

            return $this->written($configurator);
        };

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        $would = $this->rehearse($work);
        // The id the rehearsal was given is nobody's once it is rolled back.
        $would['configurator']['id'] = null;
        unset($would['revision']);

        return ['dry_run' => true] + $would;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $configurator = $this->configurator($arguments['configurator'] ?? null);

        if ($configurator->trashed()) {
            throw new ToolFailure("Configurator {$this->reference($configurator)} is in the bin. Bring it back in the panel before editing it.");
        }

        $changes = [];

        if (array_key_exists('schema', $arguments)) {
            $changes['schema'] = $this->schema($arguments, required: true);
        }

        if (array_key_exists('title', $arguments)) {
            $changes['title'] = $this->text($arguments['title'], 'title');
        }

        if (array_key_exists('form', $arguments)) {
            $changes['form'] = $this->form($arguments['form']);
        }

        if (array_key_exists('key', $arguments)) {
            $changes['key'] = is_string($arguments['key']) ? $arguments['key'] : throw new ToolFailure('`key` is a slug like "shower".');
        }

        if ($changes === []) {
            throw new ToolFailure('Nothing to change: send schema, title, form or key.');
        }

        AgentRevision::check($arguments, Revision::of($configurator), 'configurator', 'configurators_get');

        $work = function () use ($configurator, $changes, $user): array {
            return $this->written($this->writer()->update($configurator, $changes, $this->authorId($user), EntityVersion::SOURCE_MCP));
        };

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        $would = $this->rehearse($work);
        unset($would['revision']);

        return ['dry_run' => true, 'would_write' => 'draft', 'fields' => array_keys($changes)] + $would;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publish(array $arguments, ?Authenticatable $user): array
    {
        $configurator = $this->live($arguments);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_publish' => $this->reference($configurator),
                'status' => $configurator->status(),
                'has_waiting_edits' => $configurator->hasDraft(),
                // What the publication itself would refuse.
                'refused' => $this->problems($this->writer()->problems($configurator)),
            ];
        }

        $this->writer()->publish($configurator, $this->authorId($user), EntityVersion::SOURCE_MCP);

        return ['configurator' => $this->summary($configurator->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function unpublish(array $arguments): array
    {
        $configurator = $this->live($arguments);

        if (! $configurator->isPublished()) {
            throw new ToolFailure("Configurator {$this->reference($configurator)} is not on the site.");
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_unpublish' => $this->reference($configurator), 'status' => $configurator->status()];
        }

        $configurator->unpublish();

        return ['configurator' => $this->summary($configurator->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function discard(array $arguments): array
    {
        $configurator = $this->live($arguments);

        if (! $configurator->hasDraft()) {
            throw new ToolFailure("Configurator {$this->reference($configurator)} has no draft: the site already shows what it holds.");
        }

        if (! $configurator->isPublished() && ! $configurator->versions()->published()->exists()) {
            throw new ToolFailure("Configurator {$this->reference($configurator)} has never been published: its draft is all there is. configurators_delete puts it in the bin.");
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_discard' => $configurator->changedFields(), 'configurator' => $this->reference($configurator)];
        }

        $configurator->discardDraft();

        return ['configurator' => $this->summary($configurator->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function versions(array $arguments, ?Authenticatable $user): array
    {
        $configurator = $this->configurator($arguments['configurator'] ?? null);
        $versions = $configurator->publishedVersions()->get();
        $authors = Authors::names($user, $versions->map(static fn (EntityVersion $version): ?int => $version->author_id));
        $live = $configurator->isPublished() ? $versions->first()?->number : null;

        return [
            'configurator' => $this->reference($configurator),
            'has_draft' => $configurator->hasDraft(),
            'versions' => $versions->map(static fn (EntityVersion $version): array => [
                'number' => $version->number,
                'created_at' => $version->created_at?->toAtomString(),
                'author' => $version->author_id === null ? null : ($authors[$version->author_id] ?? null),
                'source' => $version->source,
                'comment' => $version->comment,
                'on_site' => $version->number === $live,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function versionRestore(array $arguments): array
    {
        $configurator = $this->live($arguments);
        $number = $arguments['number'] ?? null;

        if (! is_int($number) && ! (is_string($number) && ctype_digit($number))) {
            throw new ToolFailure('`number` is required: a version number from configurators_versions.');
        }

        $version = $configurator->publishedVersions()->where('number', (int) $number)->first();

        if (! $version instanceof EntityVersion) {
            throw new ToolFailure("Configurator {$this->reference($configurator)} has no version {$number}. configurators_versions lists them.");
        }

        $payload = is_array($version->payload) ? $version->payload : [];

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_restore' => $version->number, 'into' => 'draft', 'changes' => $configurator->changedFields($payload)];
        }

        $configurator->restoreVersion($version);

        return ['restored' => $version->number, 'into' => 'draft'] + $this->get(['configurator' => $configurator->refresh()->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $configurator = $this->configurator($arguments['configurator'] ?? null);

        if ($configurator->trashed()) {
            return ['trashed' => true, 'id' => (int) $configurator->getKey(), 'already' => true];
        }

        $this->guard($arguments, $configurator);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($configurator), 'status' => $configurator->status()];
        }

        $configurator->delete();

        return ['trashed' => true, 'id' => (int) $configurator->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function quote(array $arguments): array
    {
        $values = $arguments['values'] ?? [];
        $actions = $arguments['actions'] ?? [];
        $today = $arguments['today'] ?? null;

        if (! is_array($values) || ($values !== [] && array_is_list($values))) {
            throw new ToolFailure('`values` is an object: step key → option value, field key → number.');
        }

        if (! is_array($actions) || ! array_is_list($actions)) {
            throw new ToolFailure('`actions` is a list: [{"select": ["glass", "graphite"]}, {"set": ["h", 1900]}].');
        }

        if ($today !== null && (! is_string($today) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $today) !== 1)) {
            throw new ToolFailure('`today` is a date YYYY-MM-DD.');
        }

        if (array_key_exists('schema', $arguments)) {
            if (array_key_exists('configurator', $arguments)) {
                throw new ToolFailure('Send configurator or schema, not both.');
            }

            $schema = $this->schema($arguments, required: true);
            $priced = ['schema' => 'sent'];
        } else {
            $configurator = $this->configurator($arguments['configurator'] ?? throw new ToolFailure('Send configurator (id or key) or a schema to price.'));
            $draft = ($arguments['draft'] ?? false) === true;
            $schema = $draft ? $configurator->editing()['schema'] : $configurator->liveSchema();

            if ($schema === null) {
                throw new ToolFailure("Configurator {$this->reference($configurator)} is not on the site. Pass draft: true to price what is being edited.");
            }

            $priced = ['configurator' => $this->reference($configurator), 'schema' => $draft ? 'draft' : 'live'];
        }

        try {
            return $priced + $this->quoter()->quote($schema, $values, $actions, today: $today);
        } catch (InvalidArgumentException $invalid) {
            throw new ToolFailure($invalid->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function validate(array $arguments): array
    {
        $problems = $this->quoter()->problems($arguments['schema'] ?? null);

        return ['valid' => $problems === [], 'errors' => $this->problems($problems)];
    }

    /**
     * What a create or an update answers: the configurator, what the draft still lacks before it
     * can be published, and — when it lacks nothing — the price of its initial state.
     *
     * @return array<string, mixed>
     */
    private function written(Configurator $configurator): array
    {
        $configurator->refresh();
        $problems = $this->writer()->problems($configurator);
        $schema = $configurator->editing()['schema'];

        return [
            'configurator' => $this->summary($configurator),
            'errors' => $this->problems($problems),
            'revision' => Revision::of($configurator),
            'initial_quote' => $problems === [] && $schema !== null ? $this->quoter()->quote($schema, []) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Configurator $configurator): array
    {
        $editing = $configurator->editing();
        $locales = $this->locales();
        $summary = [
            'id' => (int) $configurator->getKey(),
            'key' => $configurator->key,
            'title' => Texts::pick($editing['title'], $locales->content(), $locales->defaultCode()),
            'status' => $configurator->status(),
            'form' => $editing['form'],
            'steps' => count(is_array($editing['schema']['steps'] ?? null) ? $editing['schema']['steps'] : []),
            'currency' => is_string($editing['schema']['currency'] ?? null) ? $editing['schema']['currency'] : null,
            'updated_at' => $configurator->updated_at?->toAtomString(),
        ];

        if ($configurator->trashed()) {
            $summary['deleted_at'] = $configurator->deleted_at?->toAtomString();
        }

        return $summary;
    }

    /**
     * A configurator for a change of state, guarded like a page's: the revision read, and one
     * sent while somebody has it open in the panel.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function live(array $arguments): Configurator
    {
        $configurator = $this->configurator($arguments['configurator'] ?? null);

        if ($configurator->trashed()) {
            throw new ToolFailure("Configurator {$this->reference($configurator)} is in the bin.");
        }

        $this->guard($arguments, $configurator);

        return $configurator;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function guard(array $arguments, Configurator $configurator): void
    {
        AgentRevision::guard($arguments, Revision::of($configurator), $configurator, 'configurator', 'configurators_get');
    }

    private function configurator(mixed $reference): Configurator
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            return Configurator::withTrashed()->find((int) $reference)
                ?? throw new ToolFailure("No configurator has the id [{$reference}]. configurators_list has them all.");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('A configurator is an id, or a key like "shower".');
        }

        $found = Configurator::withTrashed()->where('key', trim($reference))->first();

        if ($found instanceof Configurator) {
            return $found;
        }

        $keys = Configurator::query()->orderBy('key')->pluck('key')->map(strval(...))->all();

        throw new ToolFailure("No configurator has the key [{$reference}]. It has: ".($keys === [] ? 'none' : implode(', ', $keys)).'.');
    }

    private function reference(Configurator $configurator): string
    {
        return sprintf('#%s (%s)', $configurator->getKey(), $configurator->key);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    private function schema(array $arguments, bool $required): ?array
    {
        $schema = $arguments['schema'] ?? null;

        if ($schema === null && ! $required) {
            return null;
        }

        if (! is_array($schema) || ($schema !== [] && array_is_list($schema))) {
            throw new ToolFailure('`schema` is the schema document, a JSON object. configurators://schema describes it.');
        }

        return $schema;
    }

    private function form(mixed $form): ?string
    {
        if ($form === null || $form === '') {
            return null;
        }

        return is_string($form) ? $form : throw new ToolFailure('`form` is the slug of an inbox form, or null.');
    }

    /**
     * @return array<string, string>
     */
    private function text(mixed $value, string $field): array
    {
        if (is_string($value)) {
            return trim($value) === ''
                ? throw new ToolFailure("`{$field}` cannot be empty.")
                : [$this->locales()->defaultCode() => trim($value)];
        }

        if (! is_array($value) || $value === []) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            }
        }

        return $texts;
    }

    /**
     * @param  list<Problem>  $problems
     * @return list<array{path: string, code: string, message: string}>
     */
    private function problems(array $problems): array
    {
        return array_map(static fn (Problem $problem): array => $problem->toArray(), $problems);
    }

    /**
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (Refused $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function rehearse(Closure $work): mixed
    {
        return Rehearsal::run($work, (new Configurator)->getConnectionName());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function authorId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private function writer(): ConfiguratorWriter
    {
        return $this->container->make(ConfiguratorWriter::class);
    }

    private function quoter(): Quoter
    {
        return $this->container->make(Quoter::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
