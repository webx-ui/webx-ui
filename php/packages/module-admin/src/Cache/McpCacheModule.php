<?php

declare(strict_types=1);

namespace WebxUi\Admin\Cache;

use InvalidArgumentException;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The same section with its agent's tool, `cache_reset` (WEBX_UI_CACHE.md §5.3). A class of its
 * own because the frame does not require `webx-ui/mcp`: the provider registers this one where the
 * interface exists and the plain {@see CacheModule} where it does not.
 */
final class McpCacheModule extends CacheModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly CacheReset $reset) {}

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return [
            Tool::mutating(
                'reset',
                'Reset the site cache: move the version of every cache group, or of one group, so the site '
                .'rebuilds what it cached — the menu, the settings, the addresses. Sessions and locks are '
                .'not touched. Use it when the site shows what the panel no longer says; that is a bug to '
                .'report, because every save is meant to reset its own group. With `dry_run` it lists the '
                .'groups it would move and moves none.',
                fn (array $arguments): array => $this->run($arguments),
                ['properties' => [
                    'group' => ['type' => 'string', 'description' => 'One group to reset (`menu`, `settings`, …); every group when omitted.'],
                ]],
                permission: self::RESET,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function run(array $arguments): array
    {
        $group = $arguments['group'] ?? null;
        $asked = is_string($group) && trim($group) !== '' ? [trim($group)] : [];

        try {
            $groups = $this->reset->resolve($asked);
        } catch (InvalidArgumentException $failure) {
            throw new ToolFailure($failure->getMessage());
        }

        if ((bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            return ['dry_run' => true, 'would_reset' => $groups];
        }

        return ['reset' => $this->reset->reset($asked), 'store' => $this->reset->store()];
    }
}
