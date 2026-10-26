<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as Checks;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\Types\LinkType;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Pricing\Quoter;
use WebxUi\Configurator\Schema\Problem;
use WebxUi\Configurator\Schema\Validator;
use WebxUi\Inbox\Models\Form;
use WebxUi\Localization\Locales;

/**
 * Every write to a configurator, whichever door it comes through — the MCP tools now, the panel's
 * API with the editor (stage 3).
 *
 * The rules that are the module's own and not the frame's: the key is a slug, unique, and fixed
 * once the configurator has been on the site (decision 11); the form is one the inbox has; a draft
 * is saved with problems, a publication is refused with them (§9).
 */
final class ConfiguratorWriter
{
    /** A key: what a block and a template name the configurator by. */
    public const string KEY = '/^[a-z0-9][a-z0-9-]{0,63}$/';

    public function __construct(
        private readonly Quoter $quoter,
        private readonly Locales $locales,
        private readonly Config $config,
        private readonly ModuleRegistry $modules,
    ) {}

    /**
     * A new configurator, as a draft: nothing is on the site until it is published.
     *
     * @param  array<string, string>  $title
     * @param  array<string, mixed>|null  $schema  the document; a skeleton with no steps when null
     */
    public function create(string $key, array $title, ?array $schema = null, ?string $form = null, ?int $authorId = null, string $source = EntityVersion::SOURCE_PANEL): Configurator
    {
        $this->checkKey($key);
        $this->checkTitle($title);
        $this->checkForm($form);

        $configurator = new Configurator;

        $configurator->getConnection()->transaction(function () use ($configurator, $key, $title, $schema, $form, $authorId, $source): void {
            $configurator->fill(['key' => $key, 'title' => $title])->save();
            $configurator->saveDraft([
                'title' => $title,
                'schema' => $schema ?? $this->skeleton(),
                'form' => $form,
            ], $authorId, $source);
        });

        return $configurator;
    }

    /**
     * Change the draft: what is given replaces what was there, the rest is kept. The key changes
     * at once — it is not content — and only while the configurator has never been published; the
     * products that open it follow it. The page changes at once too: it is where the configurator
     * stands, not what it is (§22.1).
     *
     * @param  array{title?: array<string, string>, schema?: array<string, mixed>, form?: string|null, key?: string, page?: array<string, mixed>|null}  $changes
     */
    public function update(Configurator $configurator, array $changes, ?int $authorId = null, string $source = EntityVersion::SOURCE_PANEL): Configurator
    {
        if (array_key_exists('key', $changes) && $changes['key'] !== $configurator->key) {
            if ($configurator->isPublished() || $configurator->versions()->published()->exists()) {
                throw new Refused("The key [{$configurator->key}] has been on the site and does not change: blocks of the site name the configurator by it.", reason: 'key.locked');
            }

            $this->checkKey($changes['key']);
        }

        if (array_key_exists('title', $changes)) {
            $this->checkTitle($changes['title']);
        }

        if (array_key_exists('form', $changes)) {
            $this->checkForm($changes['form']);
        }

        if (array_key_exists('page', $changes)) {
            $changes['page'] = $this->page($changes['page']);
        }

        $configurator->getConnection()->transaction(function () use ($configurator, $changes, $authorId, $source): void {
            if (array_key_exists('key', $changes) && $changes['key'] !== $configurator->key) {
                // The products that open it name it by the key: they go where it goes.
                DB::table(Configurator::LINKS)->where('configurator', $configurator->key)->update(['configurator' => $changes['key']]);
                $configurator->key = $changes['key'];
            }

            if (array_key_exists('page', $changes)) {
                $configurator->page = $changes['page'];
            }

            if ($configurator->isDirty()) {
                $configurator->save();
            }

            // Only the key or the page: the draft is left as it is, and so is the status.
            if (array_intersect_key($changes, array_flip(Configurator::DRAFTED)) !== []) {
                $draft = array_intersect_key([...$configurator->editing(), ...$changes], array_flip(Configurator::DRAFTED));
                $configurator->saveDraft($draft, $authorId, $source);
            }
        });

        return $configurator;
    }

    /**
     * Put the draft on the site — refused, with every problem, while the schema has any.
     *
     * @throws Refused
     */
    public function publish(Configurator $configurator, ?int $authorId = null, string $source = EntityVersion::SOURCE_PANEL): Configurator
    {
        $problems = $this->problems($configurator);

        if ($problems !== []) {
            throw Refused::schema($problems);
        }

        // The form may have gone from the inbox since the draft named it.
        $this->checkForm($configurator->editing()['form']);

        return $configurator->publish($authorId, $source);
    }

    /**
     * The problems of what is being edited. A picture or a product the published schema already
     * had is not looked up again: a file removed from the library later must not lock the editor.
     *
     * @return list<Problem>
     */
    public function problems(Configurator $configurator): array
    {
        return $this->quoter->problems(
            $configurator->editing()['schema'] ?? [],
            Validator::references($configurator->schema ?? []),
        );
    }

    /**
     * The document a new configurator starts from: the format and the currency, no steps yet.
     *
     * @return array<string, mixed>
     */
    public function skeleton(): array
    {
        $currency = $this->config->get('webx-configurator.currency');

        return [
            'version' => Validator::VERSION,
            'currency' => is_string($currency) && $currency !== '' ? $currency : 'USD',
            'pricing' => ['round' => 1],
            'steps' => [],
        ];
    }

    /**
     * Whether the inbox is here to take requests at all. Asked of the panel rather than of the
     * class: in the monorepo the class is always there, installed or not.
     */
    public function hasInbox(): bool
    {
        return $this->modules->has('inbox') && class_exists(Form::class);
    }

    /**
     * The page of the configurator as `wx-link` keeps it, or null for none; refused with the
     * link field's own words when it is not a link — an address nobody can open, a section the
     * site does not have.
     *
     * @return array<string, mixed>|null
     */
    private function page(mixed $page): ?array
    {
        if ($page === null || $page === []) {
            return null;
        }

        $type = app(LinkType::class);
        $checks = Checks::make(['page' => $page], ['page' => $type->rules([])]);

        if ($checks->fails()) {
            throw new Refused((string) $checks->errors()->first('page'), reason: 'page.invalid');
        }

        $stored = $type->store($page, []);

        return is_array($stored) ? $stored : null;
    }

    private function checkKey(mixed $key): void
    {
        if (! is_string($key) || preg_match(self::KEY, $key) !== 1) {
            throw new Refused('A key is a slug: a-z, 0-9 and dashes, up to 64 characters — "shower".', reason: 'key.invalid');
        }

        if (Configurator::withTrashed()->where('key', $key)->exists()) {
            throw new Refused("The key [{$key}] is taken (perhaps by a configurator in the bin). configurators_list has them all.", reason: 'key.taken');
        }
    }

    /**
     * @param  array<string, string>  $title
     */
    private function checkTitle(array $title): void
    {
        $default = $this->locales->defaultCode();

        if (trim((string) ($title[$default] ?? '')) === '') {
            throw new Refused("A title needs words in the default language [{$default}].", reason: 'title.missing');
        }

        foreach (array_keys($title) as $locale) {
            if (! $this->locales->has((string) $locale)) {
                throw new Refused("The title has a value in [{$locale}], which this site is not published in. It has: ".implode(', ', $this->locales->codes()).'.', reason: 'title.locale');
            }
        }
    }

    private function checkForm(?string $form): void
    {
        if ($form === null || $form === '') {
            return;
        }

        if (! $this->hasInbox()) {
            throw new Refused('There is no inbox on this site (webx-ui/module-inbox): a configurator here sends no requests, leave form empty.', reason: 'form.no_inbox');
        }

        if (! Form::query()->where('slug', $form)->exists()) {
            $known = Form::query()->orderBy('position')->pluck('slug')->map(strval(...))->all();

            throw new Refused("There is no inbox form [{$form}]. It has: ".($known === [] ? 'none' : implode(', ', $known)).'.', reason: 'form.unknown');
        }
    }
}
