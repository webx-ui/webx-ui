<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;
use WebxUi\Vacancies\Panel\Closing;
use WebxUi\Vacancies\Panel\Duplicate;
use WebxUi\Vacancies\Panel\Reorder;
use WebxUi\Vacancies\Panel\Revision;
use WebxUi\Vacancies\Panel\VacancyForm;
use WebxUi\Vacancies\Panel\VacancyList;
use WebxUi\Vacancies\Support\Day;
use WebxUi\Vacancies\Support\Salary;

/**
 * What an agent can do with the vacancies (§4.12).
 *
 * The same doors the panel uses: the list is {@see VacancyList}, the values go through
 * {@see VacancyForm} — so the screen checks them, a project's field lands in `extra`, and the
 * categories and the application form wait in the draft with the text until somebody publishes.
 * A copy is {@see Duplicate}, closing and opening the hiring is {@see Closing}, the order is
 * {@see Reorder} — the code behind the panel's buttons, each one transaction.
 *
 * Creating is the row and its values in one transaction, and so is a save: a value the screen
 * refuses must not leave a bare vacancy behind with its address already taken (the lesson of
 * `services_create`).
 *
 * What an agent gets wrong untold is said in the descriptions, not left to a 422: the days are
 * `YYYY-MM-DD`, the kinds of employment and the unit are schema.org's codes, the currency is one
 * of the site's list — and a value outside those lists is refused with the list, before the screen
 * would say it in its own words. The application form is named by its slug, and only where
 * `module-inbox` is installed is there a form to name at all.
 */
final class VacancyTools
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $vacancy = [
            'type' => ['integer', 'string'],
            'description' => 'The vacancy: its id, or the address it answers at — "/careers/senior-php-developer".',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string (the default language), or every language as { "en": "…", "ru": "…" }.',
        ];
        $fields = $this->fields();

        return [
            Tool::read(
                'list',
                'The vacancies in the one order they have — the order of the careers page: the open ones by default, '
                .'or the closed ones (closed by hand or past their last day), or all. The whole list, no pages. Each '
                .'with what it is called in every language, the address it answers at, whether it is on the site, '
                .'where the work is, whether and why it is closed, its categories and its application form. Read this '
                .'(or vacancies://catalog) first: "the same position in another city" is vacancies_duplicate, not a '
                .'new vacancy.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'state' => ['type' => 'string', 'enum' => ['open', 'closed', 'all'], 'description' => 'The open ones (the default), the closed ones, or every one.'],
                    'search' => ['type' => 'string', 'description' => 'Vacancies whose title or address contains this, in any language the site has.'],
                    'status' => ['type' => 'string', 'enum' => [
                        Vacancy::STATUS_DRAFT,
                        Vacancy::STATUS_PUBLISHED,
                        Vacancy::STATUS_MODIFIED,
                        Vacancy::STATUS_UNPUBLISHED,
                    ], 'description' => 'Never published · on the site (edits waiting or not) · on the site with edits waiting · taken off it.'],
                    'category' => ['type' => ['integer', 'string'], 'description' => 'Only the vacancies in this category: its id or its slug.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first. A vacancy in the bin has no address.'],
                ]],
                permission: ['vacancies.view', 'vacancies.manage'],
            ),

            Tool::read(
                'get',
                'One vacancy in full: the values of its editor — position, address, lead, where the work is, the kinds '
                .'of employment, the salary in words and in numbers, the description, duties, requirements and what is '
                .'offered in every language, whether the hiring is closed, the last day and the day it was put up, the '
                .'categories, the application form, the SEO card, any field the project added — the revision those '
                .'values are, and a link to the draft as the site would print it.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->get($arguments, $user),
                ['properties' => ['vacancy' => $vacancy], 'required' => ['vacancy']],
                permission: ['vacancies.view', 'vacancies.manage'],
            ),

            Tool::mutating(
                'create',
                'Start a vacancy. It is a draft: nothing is on the site until somebody publishes it. The address is '
                .'made from the title when you do not write one, and an address another vacancy or a page already '
                .'answers at is refused rather than given a suffix. It starts on site, full time, in the site\'s first '
                .'currency. For the same position somewhere else, use vacancies_duplicate instead. '.$fields,
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'title' => $text + ['description' => 'The position. '.$text['description']],
                    'slug' => $text + ['description' => 'The last segment of the address, per language; made from the title when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'The rest of the editor\'s fields as vacancies_get returns them. '.$fields],
                ], 'required' => ['title']],
                permission: 'vacancies.manage',
            ),

            Tool::mutating(
                'update',
                'Change the values of a vacancy into its draft. A field left out keeps what it had; a localized field '
                .'sent as { "en": "…" } changes that language only. Send the revision vacancies_get gave you and the '
                .'write is refused if somebody saved in between. Everything — the categories, the application form and '
                .'is_closed included — reaches the site when the vacancy is published. '.$fields,
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'vacancy' => $vacancy,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as vacancies_get returns them. '.$fields],
                    'revision' => ['type' => 'string', 'description' => 'The revision vacancies_get returned. Left out, the write goes in over whatever happened since.'],
                ], 'required' => ['vacancy', 'values']],
                permission: 'vacancies.manage',
            ),

            Tool::mutating(
                'duplicate',
                'Copy a vacancy into a new draft — "the same position in Lviv": every field, its categories, the '
                .'application form and the SEO card, the same title, an address with the next free suffix ("-2", '
                .'"-3") in every language, placed right after the original. Not closed, with no day it was put up and '
                .'no history. Change what differs with vacancies_update, then publish it.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->duplicate($arguments, $user)),
                ['properties' => ['vacancy' => $vacancy], 'required' => ['vacancy']],
                permission: 'vacancies.manage',
            ),

            Tool::mutating(
                'publish',
                'Put the draft on the site: its values, categories and application form become the vacancy and a '
                .'version is written. The first publication sets the day it was put up, when nobody has. Ask a person '
                .'first unless they asked you to publish.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->publish($arguments, $user)),
                ['properties' => ['vacancy' => $vacancy], 'required' => ['vacancy']],
                permission: 'vacancies.manage',
            ),

            Tool::mutating(
                'unpublish',
                'Take a vacancy off the site: it answers 404 from then on. Its address stays reserved and whatever was '
                .'being prepared is still there. A position that has been filled is not this — that is vacancies_close, '
                .'which keeps the page for the links that lead to it.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->unpublish($arguments)),
                ['properties' => ['vacancy' => $vacancy], 'required' => ['vacancy']],
                permission: 'vacancies.manage',
            ),

            Tool::mutating(
                'discard',
                'Throw away the draft of a vacancy that is on the site and go back to what the site shows. The draft '
                .'is all that changes. dry_run names the fields that differ from the published ones.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->discard($arguments)),
                ['properties' => ['vacancy' => $vacancy], 'required' => ['vacancy']],
                permission: 'vacancies.manage',
            ),

            Tool::mutating(
                'close',
                'Close the hiring: the vacancy leaves the lists, its page stays with the note "This vacancy is closed", '
                .'without the markup search engines read and out of their index. A save and a publication in one step, '
                .'so only for a vacancy on the site with no edits waiting — publish or discard those first.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->close($arguments, $user, reopen: false)),
                ['properties' => ['vacancy' => $vacancy], 'required' => ['vacancy']],
                permission: 'vacancies.manage',
            ),

            Tool::mutating(
                'reopen',
                'Open the hiring again — one closed by hand, or one past its last day, whose last day is then cleared '
                .'(set a new one with vacancies_update). Like vacancies_close, only for a vacancy on the site with no '
                .'edits waiting.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->close($arguments, $user, reopen: true)),
                ['properties' => ['vacancy' => $vacancy], 'required' => ['vacancy']],
                permission: 'vacancies.manage',
            ),

            Tool::mutating(
                'delete',
                'Put a vacancy in the bin. Its address is released, so afterwards it can only be named by its id. '
                .'Nothing is destroyed: the bin in the panel puts it back, as long as nobody has taken its address '
                .'in the meantime.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['vacancy' => $vacancy], 'required' => ['vacancy']],
                permission: 'vacancies.manage',
            ),

            Tool::mutating(
                'reorder',
                'Put vacancies in a new order. Vacancies have one order — the careers page and every group on it '
                .'show them in it. Name them first to last: they take the places they hold now, in your order, and '
                .'the ones you leave out (the closed ones, say) stay where they are.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'vacancies' => ['type' => 'array', 'items' => $vacancy, 'description' => 'The vacancies, first to last: ids or addresses.'],
                ], 'required' => ['vacancies']],
                permission: 'vacancies.manage',
            ),
        ];
    }

    /**
     * What the fields are and how they are written — told once, in every tool that writes them.
     * The application form is mentioned only where there is one to choose.
     */
    private function fields(): string
    {
        $currencies = implode(', ', array_keys($this->salary()->currencies()));

        return 'workplace is "onsite", "remote" or "hybrid" — city and address (localized) are printed only when '
            .'people come to an office; country is an ISO code ("UA"), only for search engines. employment_types is '
            .'a list of '.implode(', ', Vacancy::EMPLOYMENT).'. salary is the words the page prints ("from 60 000 ₴", '
            .'"after the interview"); salary_min and salary_max are optional numbers for search engines and the '
            .'cards, and a number needs salary_unit ('.implode(', ', Vacancy::UNITS).') and salary_currency, one of '
            .'the site\'s: '.($currencies === '' ? 'none' : $currencies).'. description is HTML. duties, requirements '
            .'and benefits are lists of lines, each a string or { "text": … } with a map of languages; an empty '
            .'line is dropped. valid_through (the last day it is open, included) and posted_at (the day it was put '
            .'up) are days, "YYYY-MM-DD". is_closed true closes the hiring early. categories — ids or slugs.'
            .($this->formTarget() !== null
                ? ' form is the application form a candidate answers with: the slug or id of a form of the inbox '
                    .'(inbox_forms_list names them), or null for none.'
                : '');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $state = (string) ($arguments['state'] ?? 'open');

        if (! in_array($state, ['open', 'closed', 'all'], true)) {
            throw new ToolFailure('`state` is "open", "closed" or "all".');
        }

        $query = [
            'state' => $state,
            'q' => (string) ($arguments['search'] ?? ''),
            'status' => (string) ($arguments['status'] ?? ''),
            'trashed' => ($arguments['trashed'] ?? false) === true ? '1' : '0',
        ];

        $category = $this->category($arguments['category'] ?? null);

        if ($category !== null) {
            $query['category'] = (string) $category->getKey();
        }

        $vacancies = $this->container->make(VacancyList::class)->build(Request::create('/', 'GET', $query))->get();

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'state' => $query['trashed'] === '1' ? 'bin' : $state,
            'count' => $vacancies->count(),
            'vacancies' => $vacancies->map(fn (Vacancy $vacancy): array => $this->summary($vacancy))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments, ?Authenticatable $user = null): array
    {
        $vacancy = $this->vacancy($arguments['vacancy'] ?? null);

        return [
            'vacancy' => $this->summary($vacancy),
            'values' => $this->form()->values($vacancy),
            // Send it back with vacancies_update, and a write over somebody else's is refused.
            'revision' => Revision::of($vacancy),
            'preview_url' => $this->preview($vacancy, $user),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $title = $this->text($arguments['title'] ?? null, 'title');
        $slug = isset($arguments['slug']) ? $this->text($arguments['slug'], 'slug') : $this->slugFrom($title);
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => ['title' => $title, 'slug' => $slug, 'fields' => array_keys($values)],
                'would_answer_at' => $this->addresses($slug),
            ];
        }

        $vacancy = $this->form()->blank($title, $slug);

        // One transaction for the row and its values: the routing observer writes the address on
        // `created`, inside this same transaction, so a refused value takes the address with it.
        $vacancy->getConnection()->transaction(function () use ($vacancy, $values, $user): void {
            $vacancy->save();

            if ($values !== []) {
                $this->form()->save($vacancy, $values, $this->can($user), $this->authorId($user));
            }
        });

        return $this->get(['vacancy' => $vacancy->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $vacancy = $this->vacancy($arguments['vacancy'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. vacancies_get says what the fields are.');
        }

        // Merged language by language, and a language the site does not have refused — dry run
        // included: `{"slug": {"de": …}}` changes the German address and leaves the others.
        $values = $this->container->make(ScreenValues::class)->patch(Vacancy::SCREEN, $this->form()->values($vacancy), $values);

        if ($vacancy->trashed()) {
            throw new ToolFailure("Vacancy #{$vacancy->getKey()} is in the bin. Bring it back in the panel before editing it.");
        }

        $values = $this->prepare($values, $vacancy);
        $this->sameRevision($arguments, $vacancy);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_write' => 'draft',
                'fields' => array_keys($values),
                'vacancy' => $this->reference($vacancy),
            ];
        }

        $vacancy->getConnection()->transaction(
            fn () => $this->form()->save($vacancy, $values, $this->can($user), $this->authorId($user)),
        );

        return $this->get(['vacancy' => $vacancy->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function duplicate(array $arguments, ?Authenticatable $user): array
    {
        $vacancy = $this->vacancy($arguments['vacancy'] ?? null);

        if ($vacancy->trashed()) {
            throw new ToolFailure("Vacancy #{$vacancy->getKey()} is in the bin. Bring it back in the panel before copying it.");
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_copy' => $this->reference($vacancy), 'as' => 'draft'];
        }

        $copy = $this->container->make(Duplicate::class)->of($vacancy);

        return ['copied_from' => (int) $vacancy->getKey(), ...$this->get(['vacancy' => $copy->getKey()], $user)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publish(array $arguments, ?Authenticatable $user): array
    {
        $vacancy = $this->vacancy($arguments['vacancy'] ?? null);

        if ($vacancy->trashed()) {
            throw new ToolFailure("Vacancy #{$vacancy->getKey()} is in the bin. Bring it back in the panel before publishing it.");
        }

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_publish' => $this->reference($vacancy),
                'status' => $vacancy->status(),
                'has_waiting_edits' => $vacancy->hasDraft(),
            ];
        }

        $vacancy->getConnection()->transaction(
            fn () => $vacancy->publish($this->authorId($user), EntityVersion::SOURCE_MCP),
        );

        return ['vacancy' => $this->summary($vacancy->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function unpublish(array $arguments): array
    {
        $vacancy = $this->vacancy($arguments['vacancy'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_unpublish' => $this->reference($vacancy), 'status' => $vacancy->status()];
        }

        $vacancy->unpublish();

        return ['vacancy' => $this->summary($vacancy->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function discard(array $arguments): array
    {
        $vacancy = $this->vacancy($arguments['vacancy'] ?? null);

        if (! $vacancy->hasDraft()) {
            throw new ToolFailure("Vacancy [{$vacancy->getKey()}] has no draft: the site already shows what it holds.");
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_discard' => $vacancy->changedFields(), 'vacancy' => $this->reference($vacancy)];
        }

        $vacancy->discardDraft();

        return ['vacancy' => $this->summary($vacancy->refresh())];
    }

    /**
     * Both buttons of the row's menu: refused the way the panel refuses them, with the same words.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function close(array $arguments, ?Authenticatable $user, bool $reopen): array
    {
        $vacancy = $this->vacancy($arguments['vacancy'] ?? null);
        $closing = $this->container->make(Closing::class);
        $refusal = $vacancy->trashed()
            ? "Vacancy #{$vacancy->getKey()} is in the bin."
            : $closing->refusal($vacancy);

        if ($refusal !== null) {
            throw new ToolFailure($refusal);
        }

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                ($reopen ? 'would_reopen' : 'would_close') => $this->reference($vacancy),
                'closed' => $vacancy->isClosed(),
                'closed_reason' => $vacancy->closedReason(),
            ];
        }

        $reopen
            ? $closing->reopen($vacancy, $this->authorId($user), EntityVersion::SOURCE_MCP)
            : $closing->close($vacancy, $this->authorId($user), EntityVersion::SOURCE_MCP);

        return ['vacancy' => $this->summary($vacancy->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $vacancy = $this->vacancy($arguments['vacancy'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($vacancy), 'status' => $vacancy->status()];
        }

        $vacancy->delete();

        return ['trashed' => true, 'id' => (int) $vacancy->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['vacancies'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`vacancies` is the list of vacancies in their new order: ids or addresses.');
        }

        $ids = array_values(array_map(fn (mixed $one): int => (int) $this->vacancy($one)->getKey(), $given));

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_order' => $ids];
        }

        $this->container->make(Reorder::class)->move($ids);

        return $this->list(['state' => 'all']);
    }

    /**
     * The values as the screen wants them. A plain string in a translated field is the default
     * language, as in vacancies_create — not the language of a request that never chose one; the
     * same inside each line of the lists. The categories may be named by slug and the form by its
     * slug; they reach the screen as ids. A code outside its list is refused here, with the list,
     * rather than by the screen in words that do not say what would have been taken.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function prepare(array $values, ?Vacancy $vacancy = null): array
    {
        if (array_key_exists('blocks', $values)) {
            throw new ToolFailure('A vacancy has no blocks: its page is drawn by the module\'s view. Write its fields instead — vacancies_get lists them.');
        }

        $default = $this->locales()->defaultCode();
        $inDefault = static fn (mixed $value): mixed => is_string($value) ? [$default => $value] : $value;

        foreach ($values as $field => $value) {
            if (in_array($field, Vacancy::TRANSLATED, true)) {
                $values[$field] = $inDefault($value);
            }
        }

        foreach (Vacancy::LISTS as $list) {
            if (! array_key_exists($list, $values)) {
                continue;
            }

            if ($values[$list] === null) {
                $values[$list] = [];
            }

            if (! is_array($values[$list])) {
                throw new ToolFailure("`{$list}` is a list of lines: strings, or { \"text\": … }. An empty list clears it.");
            }

            $values[$list] = array_values(array_map(static function (mixed $line) use ($inDefault, $list): array {
                if (is_array($line) && array_key_exists('text', $line)) {
                    $line = $line['text'];
                }

                if (! is_string($line) && ! is_array($line)) {
                    throw new ToolFailure("Each line of `{$list}` is a string, a map of languages, or { \"text\": … }.");
                }

                return ['text' => $inDefault($line)];
            }, $values[$list]));
        }

        if (array_key_exists('workplace', $values) && ! in_array($values['workplace'], Vacancy::WORKPLACES, true)) {
            throw new ToolFailure('`workplace` is "onsite", "remote" or "hybrid".');
        }

        if (array_key_exists('employment_types', $values)) {
            $kinds = $values['employment_types'];
            $kinds = is_string($kinds) ? [$kinds] : $kinds;

            if (! is_array($kinds) || array_diff($kinds, Vacancy::EMPLOYMENT) !== []) {
                throw new ToolFailure('`employment_types` is a list of '.implode(', ', Vacancy::EMPLOYMENT).'.');
            }

            $values['employment_types'] = array_values($kinds);
        }

        if (($values['salary_unit'] ?? null) !== null && ! in_array($values['salary_unit'], Vacancy::UNITS, true)) {
            throw new ToolFailure('`salary_unit` is one of '.implode(', ', Vacancy::UNITS).', or null.');
        }

        foreach (['salary_min', 'salary_max'] as $number) {
            if (($values[$number] ?? null) !== null && $values[$number] !== '' && ! is_numeric($values[$number])) {
                throw new ToolFailure("`{$number}` is a number, or null.");
            }
        }

        if (is_string($values['salary_currency'] ?? null)) {
            $currency = strtoupper(trim($values['salary_currency']));
            $allowed = array_keys($this->salary()->currencies());
            $saved = $vacancy === null ? null : $vacancy->salary_currency;

            if (! in_array($currency, $allowed, true) && $currency !== $saved) {
                throw new ToolFailure(
                    "The site has no currency [{$currency}]. It has: ".($allowed === [] ? 'none' : implode(', ', $allowed)).'.'
                );
            }

            $values['salary_currency'] = $currency;
        }

        foreach (['valid_through', 'posted_at'] as $day) {
            if (($values[$day] ?? null) === null || $values[$day] === '') {
                continue;
            }

            if (! is_string($values[$day]) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $values[$day]) !== 1 || Day::from($values[$day]) === null) {
                throw new ToolFailure("`{$day}` is a day, \"YYYY-MM-DD\", or null.");
            }
        }

        if (array_key_exists('categories', $values)) {
            if (! is_array($values['categories'])) {
                throw new ToolFailure('`categories` is a list of ids or slugs. An empty list clears it.');
            }

            $values['categories'] = array_values(array_map(
                fn (mixed $one): int => (int) $this->category($one)?->getKey(),
                $values['categories'],
            ));
        }

        if (array_key_exists(Vacancy::FORM, $values)) {
            $values[Vacancy::FORM] = $this->applicationForm($values[Vacancy::FORM]);
        }

        return $values;
    }

    /**
     * The application form as the screen holds it — a list of one id, or none — out of a slug, an
     * id, null, or a list of one of those.
     *
     * @return list<int>
     */
    private function applicationForm(mixed $reference): array
    {
        $target = $this->formTarget();

        if ($target === null) {
            throw new ToolFailure('This site has no inbox module, so a vacancy has no application form to choose.');
        }

        if (is_array($reference)) {
            if (count($reference) > 1) {
                throw new ToolFailure('A vacancy has one application form: its slug or id, or null for none.');
            }

            $reference = $reference === [] ? null : reset($reference);
        }

        if ($reference === null || $reference === '') {
            return [];
        }

        $query = $target->query();

        $id = match (true) {
            is_int($reference), is_string($reference) && ctype_digit($reference) => $query->whereKey((int) $reference)->value('id'),
            is_string($reference) => $query->where('slug', trim($reference))->value('id'),
            default => null,
        };

        if (! is_numeric($id)) {
            $printable = is_scalar($reference) ? (string) $reference : '?';

            throw new ToolFailure("No form of the inbox is [{$printable}]. inbox_forms_list names them.");
        }

        return [(int) $id];
    }

    /**
     * A vacancy by id or by address.
     */
    private function vacancy(mixed $reference): Vacancy
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $vacancy = Vacancy::withTrashed()->find((int) $reference);

            return $vacancy instanceof Vacancy
                ? $vacancy
                : throw new ToolFailure("No vacancy has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('A vacancy is an id, or an address like "/careers/senior-php-developer".');
        }

        $id = Route::query()
            ->where('path', UrlNormaliser::key($reference))
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', (new Vacancy)->getMorphClass())
            ->value('entity_id');

        $vacancy = is_numeric($id) ? Vacancy::query()->find((int) $id) : null;

        return $vacancy instanceof Vacancy
            ? $vacancy
            : throw new ToolFailure(
                'No vacancy answers at [/'.UrlNormaliser::key($reference).']. vacancies_list has the addresses; a vacancy in the bin has none.'
            );
    }

    /**
     * A category by id or by slug in any language — the slug is the key of the careers page's
     * filter, `?category=development`.
     */
    private function category(mixed $reference): ?VacancyCategory
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $category = VacancyCategory::query()->find((int) $reference);
        } elseif (is_string($reference)) {
            $category = VacancyCategory::query()->whereTranslationLikeAny('slug', trim($reference))->first();
        } else {
            throw new ToolFailure('`category` is an id or a slug.');
        }

        return $category instanceof VacancyCategory
            ? $category
            : throw new ToolFailure('No such category. vacancy_categories_list and vacancies://catalog say what there is.');
    }

    /**
     * One vacancy as an agent needs it — every language at once, the draft's words and categories
     * where there is a draft, and from the site whether it is closed, since that is what the tabs
     * of the list go by (§4.11).
     *
     * @return array<string, mixed>
     */
    private function summary(Vacancy $vacancy): array
    {
        $vacancy->loadMissing('routes');
        $shown = $vacancy->hasDraft() ? $vacancy->withDraft() : $vacancy;
        $ids = $vacancy->draftedCategoryIds();

        $categories = VacancyCategory::query()->whereKey($ids)->get()
            ->sortBy(static fn (VacancyCategory $category): int => (int) array_search((int) $category->getKey(), $ids, true))
            ->map(static fn (VacancyCategory $category): array => [
                'id' => (int) $category->getKey(),
                'title' => $category->getTranslations('title'),
                'slug' => $category->getTranslations('slug'),
            ])
            ->values()
            ->all();

        $summary = [
            'id' => (int) $vacancy->getKey(),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'urls' => $this->urls($vacancy),
            'status' => $vacancy->status(),
            'has_draft' => $vacancy->hasDraft(),
            'workplace' => $shown->workplace,
            'city' => $shown->getTranslations('city'),
            'employment_types' => $shown->employment(),
            'closed' => $vacancy->isClosed(),
            'closed_reason' => $vacancy->closedReason(),
            'valid_through' => $vacancy->valid_through?->toDateString(),
            'posted_at' => $vacancy->posted_at?->toDateString(),
            'position' => (int) $vacancy->position,
            'categories' => $categories,
            'updated_at' => $vacancy->updated_at?->toAtomString(),
        ];

        if ($this->formTarget() !== null) {
            $summary['form'] = $this->formOf($vacancy);
        }

        if ($vacancy->trashed()) {
            $summary['deleted_at'] = $vacancy->deleted_at?->toAtomString();
        }

        return $summary;
    }

    /**
     * The application form the draft names: its slug, and whether it is switched on — a form that
     * is off is still chosen, and prints nothing.
     *
     * @return array{id: int, slug: string, enabled: bool}|null
     */
    private function formOf(Vacancy $vacancy): ?array
    {
        $target = $this->formTarget();
        $ids = $vacancy->draftedRelatedIds(Vacancy::FORM);

        if ($target === null || $ids === []) {
            return null;
        }

        $form = $target->query()->whereKey($ids[0])->first();

        return $form === null ? null : [
            'id' => (int) $form->getKey(),
            'slug' => (string) $form->getAttribute('slug'),
            'enabled' => (bool) $form->getAttribute('is_enabled'),
        ];
    }

    /**
     * @return array<string, array{path: string, url: string}>
     */
    private function urls(Vacancy $vacancy): array
    {
        $urls = [];

        foreach ($vacancy->loadMissing('routes')->routes as $route) {
            if ($route->kind === Route::CANONICAL) {
                $urls[$route->locale] = ['path' => '/'.$route->path, 'url' => $vacancy->url($route->locale)];
            }
        }

        return $urls;
    }

    private function reference(Vacancy $vacancy): string
    {
        $urls = $this->urls($vacancy);
        $address = $urls[$this->locales()->defaultCode()] ?? reset($urls);

        return sprintf('#%s (%s)', $vacancy->getKey(), is_array($address) ? $address['path'] : 'no address');
    }

    /**
     * Where a vacancy with these slugs would answer, before it exists — what a dry run reports.
     *
     * @param  array<string, string>  $slug
     * @return array<string, string>
     */
    private function addresses(array $slug): array
    {
        $prefix = UrlNormaliser::key((string) config('webx-vacancies.prefix', 'careers'));

        return array_map(
            static fn (string $one): string => '/'.UrlNormaliser::join($prefix, $one),
            $slug,
        );
    }

    /**
     * Run a change, and turn a refusal into something the agent can read: the screen refusing a
     * value and the registry refusing an address are both a `ValidationException`.
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
        } catch (CategoryException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function sameRevision(array $arguments, Vacancy $vacancy): void
    {
        $sent = $arguments['revision'] ?? null;

        if (! is_string($sent) || $sent === '') {
            return;
        }

        $current = Revision::of($vacancy);

        if ($sent !== $current) {
            throw new ToolFailure(
                "The vacancy changed since you read it: revision is [{$current}], you sent [{$sent}]. "
                .'Read it again with vacancies_get and redo the edit on what is there now.'
            );
        }
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
            // Words in a language the site is not published in are words nobody reads — and
            // an address in one is an address that answers nowhere.
            if (! $this->locales()->has((string) $locale)) {
                throw new ToolFailure("`{$field}` has a value in [{$locale}], which this site is not published in. It has: ".implode(', ', $this->locales()->codes()).'.');
            }

            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            }
        }

        return $texts === [] ? throw new ToolFailure("`{$field}` cannot be empty.") : $texts;
    }

    /**
     * @param  array<string, string>  $title
     * @return array<string, string>
     */
    private function slugFrom(array $title): array
    {
        return array_filter(array_map(static fn (string $text): string => Str::slug($text), $title));
    }

    /** Only with `module-blocks`, which draws previews; a vacancy it cannot sign is still worth reading. */
    private function preview(Vacancy $vacancy, ?Authenticatable $user): ?string
    {
        if (! class_exists(Preview::class)) {
            return null;
        }

        try {
            return Preview::url($vacancy, $this->authorId($user));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The permission check the screen asks for a field behind one — the same question the panel
     * asks of its editor, so an agent cannot write a card its administrator could not.
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

    private function authorId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    /** `module-inbox`'s forms, or null where the package is not installed. */
    private function formTarget(): ?RelationTarget
    {
        return $this->container->make(RelationTargets::class)->find(Vacancy::FORM_TARGET);
    }

    private function form(): VacancyForm
    {
        return $this->container->make(VacancyForm::class);
    }

    private function salary(): Salary
    {
        return $this->container->make(Salary::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
