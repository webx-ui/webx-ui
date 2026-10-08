<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Shortcodes\Shortcodes;

/**
 * The shortcodes this site has, for the panel's text fields: what `[` offers, what a chip shows
 * when the pointer is over it, and the list behind a field's help. Behind the panel's sign-in
 * like every other address here, and nothing more: a shortcode is what any editor may type.
 */
final class ShortcodeController
{
    public function __construct(private readonly Shortcodes $shortcodes) {}

    public function __invoke(): JsonResponse
    {
        return ApiResponse::data($this->shortcodes->list());
    }
}
