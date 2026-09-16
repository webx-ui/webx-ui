<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\Rendering\BlockContext;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * @method static HtmlString render(iterable<array-key, mixed>|null $blocks, ?object $entity = null)
 * @method static HtmlString nested(BlockContext $parent, string $field)
 * @method static Renderer preview(bool $on = true)
 * @method static bool isPreview()
 * @method static array<string, int> used()
 * @method static array<string, BlockType> usedTypes()
 * @method static void flush()
 *
 * @see Renderer
 */
final class Blocks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Renderer::class;
    }
}
