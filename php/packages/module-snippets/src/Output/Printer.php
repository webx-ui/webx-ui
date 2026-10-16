<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Output;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use WebxUi\Routing\SiteUrl;
use WebxUi\Snippets\Places;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Widgets;

/**
 * What one place prints on the page being answered (§5): its enabled codes in their order, each
 * past the filters — where it is never printed, the environment, the paths it covers, the
 * languages — and each either as it is or waiting for consent the way `<x-webx-consent>` waits.
 *
 * Nothing at all for a place nobody declared, for a render outside a request (a mail, a queued
 * job), and for a place with nothing to print: no wrapper, no marker, no blank line.
 */
final class Printer
{
    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
        private readonly Places $places,
        private readonly Codes $codes,
        private readonly Exclusions $exclusions,
        private readonly Seen $seen,
        private readonly SiteUrl $urls,
        private readonly Consent $consent,
        private readonly Views $views,
    ) {}

    public function place(string $place): string
    {
        if (! $this->places->has($place)) {
            return '';
        }

        $request = $this->request();

        if ($request === null || $this->exclusions->excluded($request)) {
            return '';
        }

        $this->seen->mark($place);

        $everything = $this->production();
        $path = $this->path($request);
        $locale = $this->app->getLocale();
        $printed = [];

        foreach ($this->codes->of($place) as $code) {
            if (! $everything && ! $code['on_stage']) {
                continue;
            }

            if (! $this->covers($code['only_paths'], $code['except_paths'], $path)) {
                continue;
            }

            if ($code['locales'] !== [] && ! in_array($locale, $code['locales'], true)) {
                continue;
            }

            $printed[] = $this->wrap($code['code'], $code['consent']);
        }

        if ($printed === []) {
            return '';
        }

        $html = implode("\n", $printed);

        // Consent Mode's default has to come before any tag the head place starts (decision 9):
        // the widgets put it on this marker instead of their own, lower one.
        return $place === 'head' ? Widgets::EARLY."\n".$html : $html;
    }

    /**
     * The request being answered, or none for a render no visitor asked for: a console command,
     * a queue worker. Tests are requests — they answer one.
     */
    private function request(): ?Request
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return null;
        }

        if (! $this->app->bound('request')) {
            return null;
        }

        $request = $this->app->make('request');

        return $request instanceof Request ? $request : null;
    }

    private function production(): bool
    {
        $environments = array_values(array_filter((array) $this->config->get('webx-snippets.environments', ['production']), is_string(...)));

        return $environments !== [] && $this->app->environment($environments);
    }

    /**
     * The path without its language prefix, so a mask means the same page in every language:
     * `/ru/blog/news` is `/blog/news`.
     */
    private function path(Request $request): string
    {
        $path = '/'.trim($request->path(), '/');
        $prefix = $this->urls->prefix();

        if ($prefix === '') {
            return $path;
        }

        if ($path === '/'.$prefix) {
            return '/';
        }

        return str_starts_with($path, '/'.$prefix.'/') ? substr($path, strlen($prefix) + 1) : $path;
    }

    /**
     * @param  list<string>  $only
     * @param  list<string>  $except
     */
    private function covers(array $only, array $except, string $path): bool
    {
        if ($only !== [] && ! Str::is($only, $path)) {
            return false;
        }

        return $except === [] || ! Str::is($except, $path);
    }

    /**
     * `necessary` as it is; any other category through the consent widget's own view — as it is
     * once agreed to (or with the banner off), inert in a `<template>` until then.
     */
    private function wrap(string $code, string $category): string
    {
        if ($category === 'necessary' || ! in_array($category, Consent::CATEGORIES, true)) {
            return $code;
        }

        return trim($this->views->make('webx-widgets::components.consent', [
            'granted' => $this->consent->has($category),
            'category' => $category,
            'slot' => new HtmlString($code),
        ])->render());
    }
}
