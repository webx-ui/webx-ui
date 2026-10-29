<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * The closed palette of internal statuses (spec §3): a name the panel turns into `--wx-*` tokens,
 * not a hex value — components know only CSS variables, and a colour picked in the light theme
 * would be unreadable in the dark one.
 */
enum StatusColor: string
{
    case Neutral = 'neutral';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Accent = 'accent';

    /** In the language of the panel, or the one asked for. */
    public function label(?string $locale = null): string
    {
        return (string) __("webx-orders::module.colors.{$this->value}", [], $locale);
    }
}
