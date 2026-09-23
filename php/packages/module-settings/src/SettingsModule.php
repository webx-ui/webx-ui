<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
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
        return ['screen' => Settings::SCREEN];
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
                'Change one setting. A localized value is an object keyed by language code.',
                static fn (array $arguments): array => self::set($arguments),
                [
                    'properties' => [
                        'key' => ['type' => 'string', 'description' => 'The setting key'],
                        'value' => ['description' => 'The new value, in the shape the field expects'],
                    ],
                    'required' => ['key', 'value'],
                ],
                scope: 'settings:write',
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function describe(): array
    {
        $fields = app(ScreenRegistry::class)->fields(Settings::SCREEN);

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

        try {
            $stored = app(ScreenValues::class)->validate(Settings::SCREEN, [$key => $arguments['value'] ?? null]);
        } catch (ValidationException $exception) {
            return ['ok' => false, 'reason' => 'The value was refused.', 'errors' => $exception->errors()];
        }

        $before = $settings->raw()[$key] ?? null;
        $changes = $before !== ($stored[$key] ?? null);

        if ((bool) ($arguments['dry_run'] ?? false) || ! $changes) {
            return ['ok' => true, 'would_change' => $changes, 'key' => $key, 'applied' => false];
        }

        $settings->save($stored);

        return ['ok' => true, 'would_change' => true, 'key' => $key, 'applied' => true];
    }
}
