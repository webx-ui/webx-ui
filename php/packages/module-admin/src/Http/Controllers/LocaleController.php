<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Localization\Locales;

/**
 * Which languages exist, before anybody has signed in.
 *
 * Public on purpose: the sign-in screen has to be drawn in some language, and it is drawn
 * before there is a session to ask. Nothing here is private — the list of languages a site
 * publishes in is visible from the site itself.
 */
final class LocaleController
{
    public function __construct(private readonly Locales $locales) {}

    public function __invoke(): JsonResponse
    {
        return ApiResponse::data([
            // The language this answer is in, which the front end adopts as its own. Narrowed
            // rather than read back raw, so a panel is never told to draw itself in a language
            // it has no words for.
            'locale' => $this->locales->resolvePanel($this->locales->current()),
            'fallback' => $this->locales->fallback(),
            // What the interface can be shown in.
            'panel' => $this->locales->panel(),
            // What the site is published in — the languages content is written for.
            'content' => $this->locales->toPayload(),
        ]);
    }
}
