<?php

declare(strict_types=1);

namespace WebxUi\Team\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Team\Models\Member;
use WebxUi\Team\Networks;
use WebxUi\Team\Panel\MemberForm;
use WebxUi\Team\Panel\MemberList;
use WebxUi\Team\Panel\MemberNames;

/**
 * What an agent can do with the team (§5.8).
 *
 * The same doors the panel uses. The list is {@see MemberList}, so the order is the one the editor
 * drags; the values go through {@see MemberForm}, which checks them against the described screen —
 * the social links, the services and a field a project patched onto `team.form` are refused where
 * the panel would refuse them; the order is {@see Ordering}, the code behind the drag.
 *
 * Creating is the form's save of a new person, in its one transaction: a refused value leaves
 * nobody behind.
 *
 * A person is named by their id and nothing else: they have no page and no slug, and names
 * repeat — two Annas are two people.
 */
final class TeamTools
{
    /** The fields that take one language as a string or every language as a map. */
    private const TRANSLATED = ['name', 'job_title', 'text'];

    /** The kind of record the `services` field links to. */
    private const SERVICE = 'service';

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $member = [
            'type' => ['integer', 'string'],
            'description' => 'The person\'s id — team_list and team://catalog have them.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string, or every language as { "en": "…", "ru": "…" }.',
        ];
        $networks = implode(', ', array_keys($this->networks()->all()));
        $fields = [
            'name' => $text + ['description' => 'The person\'s name. Needed in the default language; a language without it shows that one.'],
            'job_title' => $text + ['description' => 'What they do — "Orthodontist". Shown in the default language when not translated.'],
            'text' => $text + ['description' => 'A few lines about them: plain text, lines separated by line breaks — no HTML. Shown only in the languages it is written in; the person is shown everywhere either way.'],
            'photo' => [
                'type' => ['string', 'object', 'null'],
                'description' => 'A picture from the library: its key ("media/ab/cd/anna.jpg", as media_list_files gives it), '
                    .'or { "path": "…", "alt": "…" }. Null takes it away. Without one the site shows the initials.',
            ],
            'socials' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'network' => ['type' => 'string', 'description' => "One of: {$networks}."],
                        'url' => ['type' => 'string', 'description' => 'The address, http(s) only.'],
                    ],
                    'required' => ['network', 'url'],
                ],
                'description' => "Social links in the order the site prints them. The networks this site has: {$networks}. "
                    .'The whole list is replaced: send every link the person keeps. An empty list takes them all away.',
            ],
        ];

        // Decision 3: without services there is no field to write them into, so no argument either.
        if ($this->serviceTarget() instanceof RelationTarget) {
            $fields[Member::SERVICES] = [
                'type' => 'array',
                'items' => ['type' => ['integer', 'string']],
                'description' => 'The services this person provides: ids or addresses ("/services/braces"), as '
                    .'services_list gives them. A team block on a service\'s page can show only its people. The whole '
                    .'list is replaced; an empty one clears it.',
            ];
        }

        return [
            Tool::read(
                'list',
                'The people of the team in the order the site shows them: name, job title, whether each is '
                .'published and the languages their text is written in. Read this (or team://catalog) first: the '
                .'same person typed in twice is a duplicate.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'People whose name or job title contains this, in any language the site has.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first.'],
                ]],
                permission: ['team.view', 'team.manage'],
            ),

            Tool::read(
                'get',
                'One person in full: the values of their editor — name, job title and text in every language, the '
                .'photo, the social links, the services, whether they are published and any field the project '
                .'added.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['member' => $member], 'required' => ['member']],
                permission: ['team.view', 'team.manage'],
            ),

            Tool::mutating(
                'create',
                'Add a person at the end of the team. They are not on the site until published — pass '
                .'published: true only when a person asked for that. Once published, they are in every team block '
                .'that does not narrow to services.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    ...$fields,
                    'published' => ['type' => 'boolean', 'description' => 'On the site at once. False when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'The fields the project added to the editor, as team_get returns them.'],
                ], 'required' => ['name']],
                permission: 'team.manage',
            ),

            Tool::mutating(
                'update',
                'Change the values of a person — any of name, job_title, text, photo, socials, published, '
                .($this->serviceTarget() instanceof RelationTarget ? 'services, ' : '')
                .'and the fields the project added. A field left out keeps what it had, and so does a language '
                .'left out of a translated field: send "" for a language to take it away. It is on the site at '
                .'once: the team has no draft.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'member' => $member,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as team_get returns them. name, job_title and text take a string (the default language) or { "en": "…" }; photo takes a library key; socials a list of { network, url }.'],
                ], 'required' => ['member', 'values']],
                permission: 'team.manage',
            ),

            Tool::mutating(
                'delete',
                'Put a person in the bin. They leave every team block at once; the bin in the panel brings them back to their place.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['member' => $member], 'required' => ['member']],
                permission: 'team.manage',
            ),

            Tool::mutating(
                'reorder',
                'Put people in a new order — the one every team block shows them in. Name them in the order they '
                .'should stand in; the ones you leave out stay where they are.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'members' => ['type' => 'array', 'items' => $member, 'description' => 'The people, first to last.'],
                ], 'required' => ['members']],
                permission: 'team.manage',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = [
            'search' => (string) ($arguments['search'] ?? ''),
            'trashed' => ($arguments['trashed'] ?? false) === true ? '1' : '0',
        ];

        // The panel's own query: no pages, because the order is only an order when the whole of
        // it is in view.
        $members = $this->container->make(MemberList::class)->build(Request::create('/', 'GET', $query))->get();

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'count' => $members->count(),
            'members' => $members->map(fn (Member $member): array => $this->summary($member))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $member = $this->member($arguments['member'] ?? null);

        return [
            'member' => $this->summary($member),
            'values' => $this->form()->values($member),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        // The named arguments win over the same names in `values`: they are what the tool says it
        // takes, and an agent that sent both meant the one it could see.
        $values = [
            ...$values,
            'name' => $this->text($arguments['name'] ?? null, 'name'),
            'published' => ($arguments['published'] ?? false) === true,
        ];

        foreach (['job_title', 'text'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $values[$field] = $this->text($arguments[$field], $field, empty: true);
            }
        }

        foreach (['photo', 'socials', Member::SERVICES] as $field) {
            if (array_key_exists($field, $arguments)) {
                $values[$field] = $arguments[$field];
            }
        }

        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => [
                    'name' => $values['name'],
                    'published' => $values['published'],
                    'socials' => $values['socials'] ?? [],
                    'services' => $values[Member::SERVICES] ?? [],
                ],
            ];
        }

        $member = $this->form()->save(new Member, $values, $this->can($user));

        return $this->get(['member' => $member->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $member = $this->member($arguments['member'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. team_get says what the fields are.');
        }

        // Merged language by language, and a language the site does not have refused — dry run
        // included: `{"slug": {"de": …}}` changes the German address and leaves the others.
        $values = $this->container->make(ScreenValues::class)->patch(Member::SCREEN, $this->form()->values($member), $values);

        if ($member->trashed()) {
            throw new ToolFailure("Person #{$member->getKey()} is in the bin. Bring them back in the panel before editing them.");
        }

        foreach (self::TRANSLATED as $field) {
            // A plain string is the default language, as in team_create — not the language of a
            // request that never chose one.
            if (is_string($values[$field] ?? null)) {
                $values[$field] = [$this->locales()->defaultCode() => $values[$field]];
            }
        }

        $values = $this->prepare($values, $member);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'fields' => array_keys($values), 'member' => $this->reference($member)];
        }

        $this->form()->save($member, $values, $this->can($user));

        return $this->get(['member' => $member->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $member = $this->member($arguments['member'] ?? null);

        if ($member->trashed()) {
            return ['trashed' => true, 'id' => (int) $member->getKey(), 'already' => true];
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($member), 'published' => $member->published];
        }

        $member->delete();

        return ['trashed' => true, 'id' => (int) $member->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['members'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`members` is the list of ids in their new order.');
        }

        $ids = array_values(array_unique(array_map(fn (mixed $one): int => (int) $this->member($one)->getKey(), $given)));

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_order' => $ids];
        }

        Ordering::move(Member::class, $ids);

        return $this->list([]);
    }

    /**
     * What the screen takes, from what an agent is likely to send: a photo named by its key becomes
     * the value `wx-media` stores, services named by address become ids, and a network the site
     * does not have is refused here with the list of those it has — by key, which is what an agent
     * writes, where the form's own refusal names them the way a reader of the panel sees them.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function prepare(array $values, ?Member $member = null): array
    {
        if (is_string($values['photo'] ?? null)) {
            $values['photo'] = trim($values['photo']) === '' ? null : ['path' => trim($values['photo'])];
        }

        $path = is_array($values['photo'] ?? null) ? ($values['photo']['path'] ?? null) : null;

        // `wx-media` lets a key the library does not have through — the panel only offers keys it
        // has. An agent types them, and a typo would leave the initials where it meant a photo
        // without a word.
        if (is_string($path) && $path !== '' && ! MediaFile::query()->where('path', $path)->exists()) {
            throw new ToolFailure("The library has no file [{$path}]. media_search_files finds one by name.");
        }

        if (array_key_exists('socials', $values)) {
            $values['socials'] = $this->socials($values['socials'], $member);
        }

        if (array_key_exists(Member::SERVICES, $values)) {
            $given = $values[Member::SERVICES];

            if (! is_array($given)) {
                throw new ToolFailure('`services` is a list of service ids or addresses. An empty list clears it.');
            }

            $values[Member::SERVICES] = array_values(array_unique(array_map(fn (mixed $one): int => $this->service($one), $given)));
        }

        return $values;
    }

    /**
     * @return list<array{network: string, url: string}>
     */
    private function socials(mixed $given, ?Member $member): array
    {
        if ($given === null) {
            return [];
        }

        if (! is_array($given) || ! array_is_list($given)) {
            throw new ToolFailure('`socials` is a list of { "network": "…", "url": "…" }, in the order the site prints them.');
        }

        $networks = $this->networks()->all();
        // A link the person already has to a network the config has since dropped goes back as it
        // came: team_get hands it out, and sending back what it gave must not be a refusal (§5.2).
        $stored = array_map(static fn (array $link): string => $link['network']."\n".$link['url'], $member?->socialLinks() ?? []);
        $links = [];

        foreach ($given as $index => $row) {
            $network = is_array($row) && is_string($row['network'] ?? null) ? trim($row['network']) : '';
            $url = is_array($row) && is_string($row['url'] ?? null) ? trim($row['url']) : '';

            if ($network === '' || $url === '') {
                throw new ToolFailure(sprintf('Link %d of `socials` needs both a network and an address.', $index + 1));
            }

            if (! array_key_exists($network, $networks) && ! in_array($network."\n".$url, $stored, true)) {
                throw new ToolFailure(sprintf(
                    'This site has no network [%s]. It has: %s. A site adds one in its config (webx-team.networks).',
                    $network,
                    implode(', ', array_keys($networks)),
                ));
            }

            $links[] = ['network' => $network, 'url' => $url];
        }

        return $links;
    }

    /**
     * One person as an agent needs them: every language at once, and where the text is — a
     * person is shown in every language (decision 8), their text only where it is written.
     *
     * @return array<string, mixed>
     */
    private function summary(Member $member): array
    {
        $summary = [
            'id' => (int) $member->getKey(),
            'name' => $member->getTranslations('name'),
            'job_title' => $member->getTranslations('job_title'),
            'photo' => $member->photoPath(),
            'published' => $member->published,
            'written_in' => array_values(array_filter($this->locales()->codes(), $member->writtenIn(...))),
            'socials' => $member->socialLinks(),
            'position' => (int) $member->position,
            'updated_at' => $member->updated_at?->toAtomString(),
        ];

        if ($this->serviceTarget() instanceof RelationTarget) {
            $summary[Member::SERVICES] = $member->relatedIds(Member::SERVICES);
        }

        if ($member->trashed()) {
            $summary['deleted_at'] = $member->deleted_at?->toAtomString();
        }

        return $summary;
    }

    private function reference(Member $member): string
    {
        return sprintf('#%s (%s)', $member->getKey(), MemberNames::of($member, $this->locales()));
    }

    private function member(mixed $reference): Member
    {
        if (! is_int($reference) && ! (is_string($reference) && ctype_digit(ltrim(trim($reference), '#')))) {
            throw new ToolFailure('A person is their id — team_list has them.');
        }

        $id = (int) ltrim(trim((string) $reference), '#');
        $member = Member::withTrashed()->find($id);

        return $member instanceof Member
            ? $member
            : throw new ToolFailure("Nobody on the team has the id [{$id}].");
    }

    /**
     * A service by id or by the address it answers at — the address is what an agent reads off
     * `services://catalog`, as for recipes.
     */
    private function service(mixed $reference): int
    {
        $target = $this->serviceTarget();

        if (! $target instanceof RelationTarget) {
            throw new ToolFailure('This site has no services module, so a person cannot be linked to a service.');
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $exists = ($target->model)::query()->whereKey((int) $reference)->exists();

            return $exists ? (int) $reference : throw new ToolFailure("No service has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('A service in `services` is an id, or the address it answers at.');
        }

        $id = Route::query()
            ->where('path', UrlNormaliser::key($reference))
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', (new ($target->model))->getMorphClass())
            ->value('entity_id');

        return is_numeric($id)
            ? (int) $id
            : throw new ToolFailure('No service answers at [/'.UrlNormaliser::key($reference).'].');
    }

    private function serviceTarget(): ?RelationTarget
    {
        return $this->container->make(RelationTargets::class)->find(self::SERVICE);
    }

    /**
     * Run a change, and turn a refusal into something the agent can read.
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
     * A translated field as the form takes it: a map of languages. A plain string is the default
     * language — the form would read it as the language of the request, and an agent's request
     * has none it chose.
     *
     * @return array<string, string>
     */
    private function text(mixed $value, string $field, bool $empty = false): array
    {
        if (is_string($value)) {
            if (trim($value) !== '') {
                return [$this->locales()->defaultCode() => trim($value)];
            }

            return $empty ? [] : throw new ToolFailure("`{$field}` cannot be empty.");
        }

        if ($value === null && $empty) {
            return [];
        }

        if (! is_array($value)) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            // Words in a language the site is not published in are words nobody reads — and
            // an address in one is an address that answers nowhere.
            if (! $this->locales()->has((string) $locale)) {
                throw new ToolFailure("`{$field}` has a value in [{$locale}], which this site is not published in. It has: ".implode(', ', $this->locales()->codes()).'.');
            }

            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            }
        }

        return $texts === [] && ! $empty ? throw new ToolFailure("`{$field}` cannot be empty.") : $texts;
    }

    /**
     * The permission check the screen asks for a field behind one — the same question the panel
     * asks of its editor, so an agent cannot write a field its administrator could not.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user instanceof HasPermissions
            ? static fn (string $permission): bool => $user->hasPermission($permission)
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function form(): MemberForm
    {
        return $this->container->make(MemberForm::class);
    }

    private function networks(): Networks
    {
        return $this->container->make(Networks::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
