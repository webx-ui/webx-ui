<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/**
 * Which view prints which page, with the package's own underneath.
 *
 * Three pages and one rule: the site names a view in `webx-events.views`, and until it has
 * written that view the package's bare one is used. A fresh installation therefore serves a
 * list rather than "View [events.event] not found".
 */
final class Views
{
    /** What is printed when the site has written nothing of its own. */
    public const FALLBACK = 'webx-events::';

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
        $configured = (string) $this->config->get("webx-events.views.{$which}", '');

        return $configured !== '' && $this->views->exists($configured)
            ? $configured
            : self::FALLBACK.$which;
    }
}
