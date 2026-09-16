<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * One block, drawn: the type at its current version on the values sent, as HTML with the
 * marker pair — what the constructor swaps into the preview after a field changed, and what
 * the editor's stage shows.
 *
 * With `content` in the request the template, styles, schema or sample are taken from there
 * instead of the stored version, so the editor sees what is being typed. That is running
 * Blade that nobody saved, which is the same thing as saving it: it needs `blocks.manage` and
 * editing switched on, the same as a save would.
 */
final class RenderController
{
    public function __invoke(Request $request, Block $block, Renderer $renderer, Bundles $bundles, Config $config): JsonResponse
    {
        $request->validate([
            'values' => ['nullable', 'array'],
            'key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'content' => ['nullable', 'array:'.implode(',', BlockVersion::CONTENT)],
            'content.schema' => ['sometimes', 'array'],
            'content.template' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'content.styles' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'content.script' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'content.sample' => ['sometimes', 'nullable', 'array'],
        ]);

        $version = $block->currentVersion();

        if ($version === null) {
            return ApiResponse::message((string) __('webx-blocks::page.no-draft'), 409);
        }

        $type = BlockType::fromModels($block, $version);
        $content = $request->input('content');
        $unsaved = is_array($content) && $content !== [];

        if ($unsaved) {
            $user = $request->user();
            $allowed = $user instanceof HasPermissions
                && $user->hasPermission('blocks.manage')
                && (bool) $config->get('webx-blocks.editing', true);

            if (! $allowed) {
                return ApiResponse::message((string) __('webx-blocks::page.editing-off'), 403);
            }

            $type = BlockType::fromArray($this->overridden($type, $content));
        }

        $values = $request->input('values');
        $key = $request->input('key');

        $html = $renderer->draw(
            $type,
            is_array($values) ? $values : $type->sample,
            is_string($key) && $key !== '' ? $key : 'sample',
            $unsaved,
        );

        return ApiResponse::data([
            'html' => $html,
            'styles' => $type->styles,
            'script' => Bundles::wrapScript($type),
            'runtime' => $bundles->runtimeUrl(),
            'version' => $type->version,
        ]);
    }

    /**
     * The stored type with the request's content laid over it, field by field.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function overridden(BlockType $type, array $content): array
    {
        $row = $type->toArray();

        foreach (BlockVersion::CONTENT as $field) {
            if (! array_key_exists($field, $content)) {
                continue;
            }

            $row[$field] = match ($field) {
                'schema' => is_array($content[$field]) ? array_values($content[$field]) : [],
                'sample' => is_array($content[$field]) ? $content[$field] : [],
                'script' => is_string($content[$field]) && trim($content[$field]) !== '' ? $content[$field] : null,
                default => is_string($content[$field]) ? $content[$field] : '',
            };
        }

        return $row;
    }
}
