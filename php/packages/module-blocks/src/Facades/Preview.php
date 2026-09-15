<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use WebxUi\Blocks\Preview\PreviewGrant;

/**
 * @method static string url(Model $entity, ?int $adminId = null, ?int $minutes = null)
 * @method static PreviewGrant|null grant(Request $request, string $type, string $id)
 * @method static bool active()
 * @method static int minutes()
 *
 * @see \WebxUi\Blocks\Preview\Preview
 */
final class Preview extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \WebxUi\Blocks\Preview\Preview::class;
    }
}
