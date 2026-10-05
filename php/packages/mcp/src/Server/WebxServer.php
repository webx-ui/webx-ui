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
    /** Replaced on boot by the site's address — see Site — so that two panels can be told apart. */
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

    /**
     * The site's house rules for content, served by `webx-ui/module-settings`. Named here
     * because the instructions point at it, and the module takes the address from here.
     */
    public const CONTENT_RULES = 'settings://content-rules';

    protected string $instructions = <<<'MARKDOWN'
        This is the admin panel of a site built on WebX UI. Every tool belongs to a module of
        the panel and is named `<module>_<tool>`.

        Other sites built on WebX UI offer the very same tools, and more than one may be connected
        at once: before the first change, call `site_info` and check that this is the site the
        person meant.

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
        $this->name = Site::name();

        // Said in the first sentence, because the instructions are what an agent reads first.
        $this->instructions = str_replace(
            'the admin panel of a site built on WebX UI.',
            'the admin panel of '.Site::name().', a site built on WebX UI, running as `'.Site::environment().'`.',
            $this->instructions,
        );

        $this->tools = [
            new SiteInfoTool,
            ...array_map(
                static fn (BoundTool $tool): RegistryTool => new RegistryTool($tool),
                $registry->tools(),
            ),
        ];

        $this->resources = array_map(
            static fn (McpResource $resource): RegistryResource => new RegistryResource($resource),
            $registry->resources(),
        );

        $this->prompts = array_map(
            static fn (Prompt $prompt): RegistryPrompt => new RegistryPrompt($prompt),
            $registry->prompts(),
        );

        // Said whenever the rules are served, filled in or not: the resource always carries the
        // site's languages, and the instructions are read once, on connect, so they cannot
        // know whether an editor has written the rest yet. Without the settings module the
        // line would point at nothing, so it is left out.
        foreach ($registry->resources() as $resource) {
            if ($resource->uri === self::CONTENT_RULES && ! str_contains($this->instructions, self::CONTENT_RULES)) {
                $this->instructions .= "\n\nBefore you write or edit anything a visitor will read, read `".self::CONTENT_RULES.'`: '
                    .'the languages of the site and which one is primary, the tone of voice, and what never to say. '
                    .'Follow them in every language you write.';

                break;
            }
        }
    }

    public static function packageVersion(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('webx-ui/mcp') ?? 'dev';
        } catch (OutOfBoundsException) {
            return 'dev';
        }
    }
}
