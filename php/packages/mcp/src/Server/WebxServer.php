<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Composer\InstalledVersions;
use Illuminate\Container\Container;
use Laravel\Mcp\Server;
use OutOfBoundsException;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * The one MCP server of a panel.
 *
 * Nothing is declared here: what it serves is whatever the installed modules offer, read from
 * the registry when the server starts — so a panel with the blocks module has the blocks
 * tools, and one without it does not. Served over HTTP at `{api_path}/mcp` and over stdio as
 * `mcp:start webx`, both from the service provider.
 */
final class WebxServer extends Server
{
    protected string $name = 'WebX UI';

    /**
     * Everything a panel offers in one page of `tools/list`.
     *
     * The default is fifteen, and a panel with six modules has three times that — so a client
     * that does not follow the cursor sees a third of the tools and concludes the rest do not
     * exist. Nothing here is expensive to list, and a hundred is well past the number of tools
     * a panel will ever have. The ceiling goes up with it — the page size is `min` of the two,
     * so raising one alone changes nothing.
     */
    public int $defaultPaginationLength = 100;

    public int $maxPaginationLength = 200;

    protected string $instructions = <<<'MARKDOWN'
        This is the admin panel of a site built on WebX UI. Every tool belongs to a module of
        the panel and is named `<module>_<tool>`.

        A tool that changes something accepts `dry_run: true` and then reports what it would do
        without doing it — use that before a change you are not sure about. Writing needs a token
        with the `<module>:write` scope; a refusal says which scope was missing.

        You act as the administrator who connected you, with their permissions and nothing more:
        the list of tools is already what they may use, so a tool that is not listed is not one
        to ask for.

        Read a module's resources before writing through it: they carry the house rules and the
        catalogue of what already exists, so that you reuse rather than duplicate.
        MARKDOWN;

    protected function boot(): void
    {
        $registry = Container::getInstance()->make(ToolRegistry::class);

        $this->version = self::packageVersion();

        $this->tools = array_map(
            static fn (BoundTool $tool): RegistryTool => new RegistryTool($tool),
            $registry->tools(),
        );

        $this->resources = array_map(
            static fn (McpResource $resource): RegistryResource => new RegistryResource($resource),
            $registry->resources(),
        );

        $this->prompts = array_map(
            static fn (Prompt $prompt): RegistryPrompt => new RegistryPrompt($prompt),
            $registry->prompts(),
        );
    }

    private static function packageVersion(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('webx-ui/mcp') ?? 'dev';
        } catch (OutOfBoundsException) {
            return 'dev';
        }
    }
}
