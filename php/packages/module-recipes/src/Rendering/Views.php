<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/**
 * Which view prints which page, with the package's own underneath.
 *
 * Three pages and one rule: the site names a view in `webx-recipes.views`, and until it has
 * written that view the package's bare one is used. A fresh installation therefore serves a
 * catalogue rather than "View [recipes.recipe] not found".
 */
final class Views
{
    /** What is printed when the site has written nothing of its own. */
    public const FALLBACK = 'webx-recipes::';

    public function __construct(
        private readonly ViewFactory $views,
        private readonly Config $config,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function make(string $which, array $data): View
    {
        return $this->views->make($this->name($which), $data);
    }

    public function name(string $which): string
    {
        $configured = (string) $this->config->get("webx-recipes.views.{$which}", '');

        return $configured !== '' && $this->views->exists($configured)
            ? $configured
            : self::FALLBACK.$which;
    }
}
