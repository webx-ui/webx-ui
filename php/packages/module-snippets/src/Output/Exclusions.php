<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Output;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use WebxUi\Blocks\Preview\PreviewGrant;

/**
 * Where a snippet is never printed, whatever it covers (§5.6): the panel and its JSON, the block
 * preview and the stage the block editor draws on — told by the path of the request, because a
 * 404 under `/cms/…` is drawn with the site's own layout and looks like any other page.
 *
 * The paths are read at the request, as the panel's gate reads them; an empty path is nothing,
 * not everything.
 */
final class Exclusions
{
    public function __construct(private readonly Config $config) {}

    public function excluded(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach (['webx-admin.path', 'webx-admin.api_path', 'webx-blocks.preview.path'] as $key) {
            if ($this->under($path, $this->config->get($key))) {
                return true;
            }
        }

        // A preview mounted somewhere else still carries its grant.
        return class_exists(PreviewGrant::class) && PreviewGrant::of($request) !== null;
    }

    private function under(string $path, mixed $prefix): bool
    {
        $prefix = is_string($prefix) ? trim($prefix, '/') : '';

        if ($prefix === '') {
            return false;
        }

        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }
}
