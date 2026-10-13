<?php

declare(strict_types=1);

namespace WebxUi\Banners\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\JsonResponse;
use Throwable;
use WebxUi\Admin\Http\ApiResponse;

/**
 * What the layer editor needs to look like the site (decision 11 of the layers spec): the theme's
 * tokens and font faces as CSS, and its palette as swatches.
 *
 * The panel never changes how the site looks; it only shows it. A site without a theme — or one
 * whose theme cannot be read right now — answers empty, and the canvas draws with the system font.
 */
final class ThemeController
{
    public function __invoke(Container $app, UrlGenerator $urls): JsonResponse
    {
        $tokens = 'WebxUi\\Themes\\Tokens';
        $fonts = 'WebxUi\\Themes\\ThemeFonts';
        $answer = ['tokens_css' => '', 'fonts_css' => '', 'palette' => []];

        if (! class_exists($tokens) || ! $app->bound('WebxUi\\Themes\\ThemeChain')) {
            return ApiResponse::data($answer);
        }

        try {
            $theme = $app->make($tokens);
            $answer['tokens_css'] = (string) $theme->css();
            $answer['fonts_css'] = (string) $app->make($fonts)->css($urls);

            foreach ((array) $theme->values() as $name => $value) {
                if (is_string($name) && str_starts_with($name, 'color-')) {
                    $resolved = $theme->resolved($name);

                    if (is_string($resolved) && $resolved !== '') {
                        $answer['palette'][] = ['key' => substr($name, 6), 'value' => $resolved];
                    }
                }
            }
        } catch (Throwable) {
            return ApiResponse::data(['tokens_css' => '', 'fonts_css' => '', 'palette' => []]);
        }

        return ApiResponse::data($answer);
    }
}
