<?php

declare(strict_types=1);

namespace WebxUi\Admin\Help\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Help\HelpBuilder;
use WebxUi\Admin\Help\HelpException;
use WebxUi\Localization\Contracts\HasPanelLocale;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The panel's help for an agent: `help_list`, `help_get` and `help://screens`
 * (WEBX_UI_PANEL_HELP.md §5). A settings listing says a field's label and type; this says what
 * changing it does.
 *
 * A module of its own so that the tools are named `help_*` and carry the `help:read` scope. It has
 * no page, and the panel's menu leaves out a module the front end has no routes for.
 *
 * Help has no permission of its own, and every MCP tool stands behind one, so these stand behind
 * any permission a module declares: an administrator holding none is not shown them. Fields behind
 * a permission are cut out as in the panel; on stdio there is nobody behind the call and all of
 * them are there.
 */
final class HelpModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    private const string LOCALE = 'The language to read in: one of the panel\'s, the language of the administrator by default. '
        .'A text not written in it comes in English; `locale` in the answer says which.';

    public function __construct(
        private readonly HelpBuilder $help,
        private readonly AccessPolicy $policy,
        private readonly Locales $locales,
    ) {}

    public function id(): string
    {
        return 'help';
    }

    public function title(): string
    {
        return 'Help';
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        $permissions = $this->policy->declared();

        if ($permissions === []) {
            return [];
        }

        return [
            Tool::read(
                'list',
                'Every screen of this panel that has help: its key, title, kind (`screen` has a form described '
                .'by the panel, `page` does not), whether it describes fields, and its module. '
                .'`help_get` reads one.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->list($arguments, $user),
                ['properties' => ['locale' => ['type' => 'string', 'description' => self::LOCALE]]],
                permission: $permissions,
            ),

            Tool::read(
                'get',
                'The help of one screen, as a manager reads it in the panel: why the section exists, how to do '
                .'things in it, and what each field does, tab by tab. Each field has its `name`, the key the '
                .'writing tools take, and its `type`. Read it when you are not sure what a field means.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->page($arguments, $user),
                ['properties' => [
                    'key' => ['type' => 'string', 'description' => 'The key, as `help_list` gives it: `catalog.product-form`.'],
                    'locale' => ['type' => 'string', 'description' => self::LOCALE],
                ], 'required' => ['key']],
                permission: $permissions,
            ),
        ];
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return [
            new McpResource(
                'help://screens',
                'Help screens',
                'Every screen of this panel that has help, the same as `help_list`.',
                fn (array $arguments = [], ?Authenticatable $user = null): array => $this->list($arguments, $user),
            ),
            new McpResource(
                'help://screens/{key}',
                'Help of a screen',
                'The help of one screen as a single Markdown document, in the language of the administrator.',
                fn (array $arguments, ?Authenticatable $user = null): string => $this->markdown(
                    $this->page($arguments, $user),
                    $this->language($arguments, $user),
                ),
                'text/markdown',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{locale: string, screens: list<array<string, mixed>>}
     */
    private function list(array $arguments, ?Authenticatable $user): array
    {
        $locale = $this->language($arguments, $user);

        return [
            'locale' => $locale,
            'screens' => $this->help->listing($locale, self::can($user), self::translator($locale)),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{key: string, title: string, locale: string, about: string, howto: list<array{title: string, body: string}>, groups: list<array{id: string, label: string|null, fields: list<array<string, mixed>>}>, guide: string|null}
     */
    private function page(array $arguments, ?Authenticatable $user): array
    {
        $key = $arguments['key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new ToolFailure('`key` must be the key of a screen. `help_list` lists them.');
        }

        $locale = $this->language($arguments, $user);

        try {
            $page = $this->help->build($key, $locale, self::can($user), self::translator($locale), forAgent: true);
        } catch (HelpException $broken) {
            // The path in the message is the server's, and the bug is the package's, not the agent's.
            report($broken);

            throw new ToolFailure("The help of {$key} cannot be read just now.");
        }

        if ($page === null) {
            throw new ToolFailure("There is no help for {$key}. `help_list` lists the screens that have it.");
        }

        return $page;
    }

    /**
     * The language asked for, or the administrator's own. Narrowed to the panel's languages before
     * it goes anywhere: the translator makes a path out of it.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function language(array $arguments, ?Authenticatable $user): string
    {
        $asked = $arguments['locale'] ?? null;

        if (is_string($asked) && $asked !== '') {
            return $this->locales->resolvePanel($asked);
        }

        return $this->locales->resolvePanel($user instanceof HasPanelLocale ? $user->panelLocale() : null);
    }

    /**
     * The rule of `webx-ui/mcp`: a caller with no permissions to ask about is not refused.
     *
     * @return callable(string): bool
     */
    private static function can(?Authenticatable $user): callable
    {
        return static fn (string $permission): bool => ! $user instanceof HasPermissions || $user->hasPermission($permission);
    }

    /**
     * By the language of the call and not of the request: an agent may read in any of them.
     *
     * @return callable(string): string
     */
    private static function translator(string $locale): callable
    {
        return static fn (string $words): string => (string) __($words, [], $locale);
    }

    /**
     * One document, in the order the panel draws it.
     *
     * @param  array{key: string, title: string, locale: string, about: string, howto: list<array{title: string, body: string}>, groups: list<array{id: string, label: string|null, fields: list<array<string, mixed>>}>, guide: string|null}  $page
     */
    private function markdown(array $page, string $locale): string
    {
        $parts = ['# '.$page['title'], $page['about']];

        if ($page['howto'] !== []) {
            $parts[] = '## '.self::translator($locale)('webx-admin::help.howto');

            foreach ($page['howto'] as $howto) {
                $parts[] = '### '.$howto['title'];
                $parts[] = $howto['body'];
            }
        }

        foreach ($page['groups'] as $group) {
            $parts[] = '## '.($group['label'] ?? self::translator($locale)('webx-admin::help.fields'));

            foreach ($group['fields'] as $field) {
                array_push($parts, ...self::field($field));
            }
        }

        if ($page['guide'] !== null) {
            $parts[] = $page['guide'];
        }

        return implode("\n\n", array_filter($parts, static fn (string $part): bool => $part !== ''))."\n";
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private static function field(array $field): array
    {
        $label = $field['label'] ?? $field['name'] ?? $field['id'];
        $name = is_string($field['name'] ?? null) ? ' (`'.$field['name'].'`)' : '';

        return [
            '### '.(is_string($label) ? $label : '').$name,
            is_string($field['hint'] ?? null) ? $field['hint'] : '',
            is_string($field['body'] ?? null) ? $field['body'] : '',
        ];
    }
}
