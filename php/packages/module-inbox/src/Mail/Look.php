<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Mail;

use Illuminate\View\Factory;
use InvalidArgumentException;

/**
 * What the letters of this package are painted with and stand in (THEMES §7.5).
 *
 * On a site with a theme: the values of its tokens from `theme_token()` — a preset or the owner's
 * colour in the panel repaints the letters with the site — and its frame, `mail.layout`, found
 * through the chain like any view. This package does not require the theme engine, so on a site
 * without one the values below and a frame of its own, `webx-inbox::mail.frame`.
 *
 * The values are theme-default's, so a letter reads the same on the day a theme is installed.
 * They are here, in PHP, and not in a view: `webx:theme:check` reads the letters of the modules
 * too, and a literal colour there is a finding (rule 25).
 */
final class Look
{
    /** @var array<string, string> */
    private const array DEFAULTS = [
        'color-bg' => '#ffffff',
        'color-surface' => '#f6f7f9',
        'color-text' => '#1d2127',
        'color-text-muted' => '#555e6b',
        'color-border' => '#dde1e6',
        'color-accent' => '#1f5fbf',
        'color-accent-contrast' => '#ffffff',
        'font-body' => "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
        'radius-sm' => '4px',
        'radius-md' => '8px',
    ];

    public function __construct(private readonly Factory $views) {}

    /** `$look('color-accent')` — the value to write into `style="…"`. */
    public function __invoke(string $token): string
    {
        if ($this->themed()) {
            $value = theme_token($token);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return self::DEFAULTS[$token] ?? '';
    }

    /** The view a letter stands in: the theme's frame when there is one, this package's otherwise. */
    public function frame(): string
    {
        if (! $this->themed()) {
            return 'webx-inbox::mail.frame';
        }

        // Asked of the finder rather than `exists()`: the theme's views are found through the chain
        // at run time, and a static analyser that reads the site's views calls the name unknown.
        try {
            $this->views->getFinder()->find('mail.layout');

            return 'mail.layout';
        } catch (InvalidArgumentException) {
            return 'webx-inbox::mail.frame';
        }
    }

    private function themed(): bool
    {
        return function_exists('theme_active') && function_exists('theme_token') && theme_active();
    }
}
