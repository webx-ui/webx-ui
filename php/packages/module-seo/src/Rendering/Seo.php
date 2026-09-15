<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Settings\Settings;

/**
 * What a page says about itself, worked out and printed.
 *
 * The only thing a template needs: `@webxSeo` inside `<head>`, or `<x-webx-seo :for="$page" />`
 * when there is an entity to name. Everything behind it — which rule matched, which language was
 * asked for, where the picture lives — is somebody else's business by then.
 */
final class Seo
{
    public function __construct(
        private readonly SeoSources $sources,
        private readonly Config $config,
        private readonly ViewFactory $views,
    ) {}

    /**
     * Every source, highest first, merged field by field, with the title template applied and
     * the Open Graph gaps filled in from what is already there.
     */
    public function for(string $url, ?object $subject = null, ?string $locale = null): SeoData
    {
        $url = UrlNormaliser::normalise($url);
        $data = SeoData::empty();

        foreach ($this->sources->all() as $source) {
            $answer = $source->forUrl($url, $subject, $locale);

            if ($answer instanceof SeoData) {
                $data = $data->mergeOver($answer);
            }
        }

        return $this->finish($data, $url, $locale);
    }

    /**
     * The same walk, with every step kept.
     *
     * This is what `POST /test-url` answers with, and it exists because "why does this page have
     * the wrong title" is the question this module gets asked most. One call should answer it.
     *
     * @return list<array{source: string, priority: int, data: array<string, mixed>}>
     */
    public function chain(string $url, ?object $subject = null, ?string $locale = null): array
    {
        $url = UrlNormaliser::normalise($url);
        $chain = [];

        foreach ($this->sources->all() as $source) {
            $answer = $source->forUrl($url, $subject, $locale);

            if (! $answer instanceof SeoData) {
                continue;
            }

            $chain[] = [
                'source' => class_basename($source),
                'priority' => $source->priority(),
                'data' => $answer->toArray(),
            ];
        }

        return $chain;
    }

    /** The address of the request being answered: path and query, nothing else. */
    public function currentUrl(): string
    {
        $request = app()->bound('request') ? app('request') : null;

        return UrlNormaliser::normalise($request instanceof Request ? $request->getRequestUri() : '/');
    }

    /** The `<head>` block, ready to print. */
    public function head(?object $subject = null, ?string $url = null, ?string $locale = null): HtmlString
    {
        $data = $this->for($url ?? $this->currentUrl(), $subject, $locale);

        return new HtmlString((string) $this->views->make('webx-seo::head', [
            'seo' => $data,
            'print' => (array) $this->config->get('webx-seo.print', []),
        ])->render());
    }

    /**
     * The last word: the template around the title, the soft limits, and the Open Graph
     * properties that repeat what the page already said.
     */
    private function finish(SeoData $data, string $url, ?string $locale): SeoData
    {
        $title = $this->applyTemplate($data->title, $locale);

        $data = $data->with(
            title: $this->clamp($title, 'title'),
            description: $this->clamp($data->description, 'description'),
            keywords: $this->clamp($data->keywords, 'keywords'),
        );

        $og = $data->og;

        foreach ([
            'title' => $data->title,
            'description' => $data->description,
            'url' => $data->canonical ?? url($url),
            'type' => (string) $this->config->get('webx-seo.og.type', 'website'),
        ] as $property => $fallback) {
            if (! isset($og[$property]) && is_string($fallback) && $fallback !== '') {
                $og[$property] = $fallback;
            }
        }

        return $data->with(og: $og);
    }

    /**
     * `{title} — {site}`. A placeholder nobody filled takes the punctuation around it with it,
     * so a site without a name does not publish "Contacts —".
     */
    private function applyTemplate(?string $title, ?string $locale): ?string
    {
        if ($title === null) {
            return null;
        }

        $template = $this->setting('seo.title-template', $locale);

        if ($template === null || ! str_contains($template, '{title}')) {
            $template = (string) $this->config->get('webx-seo.title_template', '{title}');
        }

        $replaced = str_replace(
            ['{title}', '{site}'],
            [$title, $this->setting('general.project-name', $locale) ?? ''],
            $template,
        );

        // Separators left hanging where a placeholder resolved to nothing.
        $replaced = (string) preg_replace('/\s*[|\x{2013}\x{2014}\-\x{00B7}]\s*$/u', '', $replaced);
        $replaced = (string) preg_replace('/^\s*[|\x{2013}\x{2014}\-\x{00B7}]\s*/u', '', $replaced);

        $replaced = trim($replaced);

        return $replaced === '' ? $title : $replaced;
    }

    /**
     * Soft by default: the limits are there so the panel can say a title is long, and only cut
     * the printed value when the site asks for it.
     */
    private function clamp(?string $value, string $field): ?string
    {
        if ($value === null || ! (bool) $this->config->get('webx-seo.trim', false)) {
            return $value;
        }

        $limit = $this->config->get("webx-seo.limits.{$field}");

        if (! is_int($limit) || $limit <= 0 || mb_strlen($value) <= $limit) {
            return $value;
        }

        return Str::limit($value, $limit, '…');
    }

    /**
     * A setting, without knowing whether the settings module is even installed — a site that
     * renders SEO from its own sources should not need the panel's tables to do it.
     */
    private function setting(string $key, ?string $locale): ?string
    {
        if (! app()->bound(Settings::class)) {
            return null;
        }

        $value = app(Settings::class)->get($key, null, $locale);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
