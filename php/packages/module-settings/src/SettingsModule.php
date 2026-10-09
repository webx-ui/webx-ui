<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Settings\Contacts\ContactsCheck;
use WebxUi\Settings\Demo\SettingsDemo;

/**
 * The panel section for the site's settings.
 *
 * What it knows about its own fields it reads from the screen: the MCP tools list the keys the
 * tree declares, and `settings_set` accepts only those, checked with the same rules the form
 * is. A key nobody described cannot be written from anywhere.
 */
final class SettingsModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly SettingsDemo $demo) {}

    public function id(): string
    {
        return 'settings';
    }

    public function title(): string
    {
        return (string) __('webx-settings::module.title');
    }

    public function icon(): string
    {
        return 'gear';
    }

    public function order(): int
    {
        return 800;
    }

    public function group(): string
    {
        return 'system';
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['settings.view', 'settings.manage'];
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return ['screen' => Settings::SCREEN, 'content_screen' => Settings::CONTENT_SCREEN];
    }

    /**
     * Nothing: the general settings are words, and the branding fields are left empty on
     * purpose — a demo logo is the one demo thing nobody notices is still there (§9).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return [];
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return [
            Tool::read(
                'list',
                'List the settings the site has: key, label, type, and whether the value is per language.',
                static fn (): array => self::describe(),
                scope: 'settings:read',
            ),

            Tool::read(
                'get',
                'Read one setting: the stored value and what the site sees.',
                static function (array $arguments): array {
                    $key = (string) ($arguments['key'] ?? '');
                    $settings = app(Settings::class);

                    if (! in_array($key, $settings->keys(), true)) {
                        return ['ok' => false, 'reason' => "No setting is named [{$key}]."];
                    }

                    return [
                        'ok' => true,
                        'key' => $key,
                        'stored' => $settings->raw()[$key] ?? null,
                        'resolved' => $settings->get($key),
                    ];
                },
                [
                    'properties' => ['key' => ['type' => 'string', 'description' => 'The setting key, e.g. general.project-name']],
                    'required' => ['key'],
                ],
                scope: 'settings:read',
            ),

            Tool::mutating(
                'set',
                'Change one setting. A localized value is an object keyed by language code; a language set to null empties that language. '
                .'Send null to clear a value — every language of a localized one — and the site then falls back to its default.',
                static fn (array $arguments): array => self::set($arguments),
                [
                    'properties' => [
                        'key' => ['type' => 'string', 'description' => 'The setting key'],
                        'value' => [
                            // Every JSON type spelled out: with no type at all, clients sent null
                            // as the string "null", which a text field then kept as its words.
                            'type' => ['string', 'number', 'integer', 'boolean', 'object', 'array', 'null'],
                            'description' => 'The new value, in the shape the field expects; null clears it.',
                        ],
                    ],
                    'required' => ['key', 'value'],
                ],
                scope: 'settings:write',
            ),
        ];
    }

    /**
     * The house rules for writing content — see {@see ContentRules}.
     *
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return [ContentRules::resource()];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function describe(): array
    {
        $fields = app(Settings::class)->fields();

        return array_map(static fn (array $node): array => [
            'key' => $node['name'],
            'label' => Tree::translate($node['label'] ?? $node['name'], static fn (string $key): string => (string) __($key)),
            'type' => $node['type'],
            'localized' => ($node['localized'] ?? false) === true,
        ], $fields);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function set(array $arguments): array
    {
        $key = (string) ($arguments['key'] ?? '');
        $settings = app(Settings::class);

        if (! in_array($key, $settings->keys(), true)) {
            return ['ok' => false, 'reason' => "No setting is named [{$key}]."];
        }

        $value = $arguments['value'] ?? null;
        $localized = in_array($key, array_column(array_filter(
            $settings->fields(),
            static fn (array $node): bool => ($node['localized'] ?? false) === true,
        ), 'name'), true);

        // Clearing is not a value of the field's type, so it skips the type's rules: a media field
        // would otherwise want a list, a number field a number, and nothing could be emptied.
        $clearing = self::clears($value);
        $stored = [];

        if (! $clearing) {
            if ($localized && is_array($value)) {
                $value = array_map(static fn (mixed $one): mixed => self::clears($one) ? null : $one, $value);
            }

            try {
                $screen = $settings->screenOf($key) ?? Settings::SCREEN;
                // A translated setting changes in the languages named and refuses one the site lacks.
                $input = app(ScreenValues::class)->patch($screen, [$key => $settings->raw()[$key] ?? null], [$key => $value]);
                $stored = app(ScreenValues::class)->validate($screen, $input);
                app(DataShortcodes::class)->check($stored);
                ContactsCheck::check($stored);
            } catch (ValidationException $exception) {
                return ['ok' => false, 'reason' => 'The value was refused.', 'errors' => $exception->errors()];
            }

            // The last language emptied leaves nothing to translate, which is the same as cleared.
            $clearing = $localized && ($stored[$key] ?? null) === [];
        }

        $changes = $clearing
            ? array_key_exists($key, $settings->raw())
            : ($settings->raw()[$key] ?? null) !== ($stored[$key] ?? null);

        if ((bool) ($arguments['dry_run'] ?? false) || ! $changes) {
            return ['ok' => true, 'would_change' => $changes, 'key' => $key, 'applied' => false];
        }

        // A clear removes the row instead of saving null, which the site would read as a value.
        if ($clearing) {
            $settings->clear([$key]);
        } else {
            $settings->save($stored);
        }

        return ['ok' => true, 'would_change' => true, 'key' => $key, 'applied' => true];
    }

    /**
     * Null, or the word for it: not every client can send a real null for a parameter that
     * takes any type, and no setting means the four letters "null".
     */
    private static function clears(mixed $value): bool
    {
        return $value === null || (is_string($value) && strtolower(trim($value)) === 'null');
    }
}
