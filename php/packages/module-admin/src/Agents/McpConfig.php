<?php

declare(strict_types=1);

namespace WebxUi\Admin\Agents;

use JsonException;

/**
 * The site's `.mcp.json`: the one server entry that is this site's own panel.
 *
 * Claude Code reads the file from the folder a session is opened in, and names every tool after
 * the key of its server — `mcp__example.com__pages_create`. Every WebX UI site offers the same
 * tools under the same names, so a session opened in one site's checkout should see that site
 * and no other; the key is the server's own name, the host of the site.
 *
 * Only that entry is ours. Any other server in the file — the production panel added by hand
 * beside the local one, a tool of the project's — is kept as it was, and an entry is replaced
 * rather than added when it already points at this address, so a key renamed by hand stays.
 */
final class McpConfig
{
    public const FILE = '.mcp.json';

    public function __construct(private readonly ?string $contents) {}

    /**
     * The file with this site's entry in place, or null when the file is there but is not JSON
     * this can read — rewritten, it would lose whatever somebody meant by it.
     */
    public function with(string $name, string $url): ?string
    {
        $config = $this->decode();

        if ($config === null) {
            return null;
        }

        $servers = $config['mcpServers'] ?? [];

        if (! is_array($servers)) {
            return null;
        }

        $key = $name;

        foreach ($servers as $existing => $server) {
            if (is_array($server) && ($server['url'] ?? null) === $url) {
                $key = (string) $existing;

                break;
            }
        }

        $servers[$key] = ['type' => 'http', 'url' => $url] + (is_array($servers[$key] ?? null) ? $servers[$key] : []);
        $config['mcpServers'] = $servers;

        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(): ?array
    {
        if ($this->contents === null || trim($this->contents) === '') {
            return [];
        }

        try {
            $decoded = json_decode($this->contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        // `{}` decodes to an empty list; any other list is not a config.
        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            return null;
        }

        return $decoded;
    }
}
