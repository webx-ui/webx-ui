<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Contracts\HasOpenGraph;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\Faq\PageFaq;
use WebxUi\Settings\Settings;

/**
 * What a page says about itself, worked out and printed.
 *
 * The only thing a template needs: `@webxSeo` inside `<head>`, or `<x-webx-seo::head :for="$page" />`
 * when there is an entity to name. Everything behind it — which rule matched, which language was
 * asked for, where the picture lives — is somebody else's business by then.
 */
final class Seo
{
    /** Where `push()` keeps its blocks on the request. */
    public const PUSHED = 'webx.seo.pushed';

    /** Where `put()` keeps its blocks on the request. */
    public const PUT = 'webx.seo.put';

    /**
     * Blocks pushed outside a request — a console command rendering a page.
     *
     * @var list<array<string, mixed>>
     */
    private array $pushed = [];

    /**
     * Blocks put outside a request, by key.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $put = [];

    public function __construct(
        private readonly SeoSources $sources,
        private readonly Config $config,
        private readonly ViewFactory $views,
    ) {}

    /**
     * Every source, highest first, merged field by field, with the title template applied and
     * the Open Graph gaps filled in from what is already there.
     *
     * @param  SeoData|array<string, mixed>|null  $fallback  What the view calls a page that has no entity — see `head()`.
     */
    public function for(string $url, ?object $subject = null, ?string $locale = null, SeoData|array|null $fallback = null): SeoData
    {
        $url = UrlNormaliser::normalise($url);
        $data = SeoData::empty();
        $titled = null;
        $images = [];
        $pictured = null;

        foreach ($this->answers($url, $subject, $locale, $fallback) as [$source, $answer]) {
            $data = $data->mergeOver($answer);
            $titled ??= $answer->title !== null ? $source : null;

            if (isset($answer->og['image'])) {
                $images[] = $answer->og['image'];
                $pictured ??= $answer;
            }
        }

        // A picture and its alt travel together: merged field by field, a card's picture would
        // otherwise be printed with the alt of the cover it replaced.
        $og = array_filter($data->og, static fn (string $key): bool => $key !== 'image' && ! str_starts_with($key, 'image:'), ARRAY_FILTER_USE_KEY);
        $og += $pictured === null ? [] : array_filter($pictured->og, static fn (string $key): bool => $key === 'image' || str_starts_with($key, 'image:'), ARRAY_FILTER_USE_KEY);

        $data = new SeoData(
            title: $data->title,
            h1: $data->h1,
            description: $data->description,
            keywords: $data->keywords,
            canonical: $data->canonical,
            robots: $data->robots,
            og: $og,
            jsonLd: $data->jsonLd,
            images: array_values(array_unique($images)),
        );

        return $this->finish($data, $url, $subject, $locale, $titled instanceof FallbackSource);
    }

    /**
     * The same walk, with every step kept.
     *
     * This is what `POST /test-url` answers with, and it exists because "why does this page have
     * the wrong title" is the question this module gets asked most. One call should answer it.
     *
     * @param  SeoData|array<string, mixed>|null  $fallback
     * @return list<array{source: string, priority: int, data: array<string, mixed>}>
     */
    public function chain(string $url, ?object $subject = null, ?string $locale = null, SeoData|array|null $fallback = null): array
    {
        $url = UrlNormaliser::normalise($url);
        $chain = [];

        foreach ($this->answers($url, $subject, $locale, $fallback) as [$source, $answer]) {
            $chain[] = [
                'source' => class_basename($source),
                'priority' => $source->priority(),
                'data' => $answer->toArray(),
            ];
        }

        return $chain;
    }

    /**
     * Every source that had something to say, highest first, each with its answer.
     *
     * @param  SeoData|array<string, mixed>|null  $fallback
     * @return list<array{0: SeoSource, 1: SeoData}>
     */
    private function answers(string $url, ?object $subject, ?string $locale, SeoData|array|null $fallback): array
    {
        $given = is_array($fallback) ? SeoData::make($fallback) : $fallback;
        $answers = [];

        foreach ($this->sources->all() as $source) {
            $answer = $source instanceof FallbackSource
                ? $source->answer($subject, $locale, $given)
                : $source->forUrl($url, $subject, $locale);

            if ($answer instanceof SeoData) {
                $answers[] = [$source, $answer];
            }
        }

        return $answers;
    }

    /**
     * The address of the request being answered: path and query, nothing else.
     *
     * The path the application sees, not the one the browser asked for: `/index.php/about` and a
     * site in `/sub` both put something in front of it that `url()` adds back on its own, and
     * the canonical came out with the front controller in it twice.
     */
    public function currentUrl(): string
    {
        $request = app()->bound('request') ? app('request') : null;

        if (! $request instanceof Request) {
            return '/';
        }

        $uri = $request->getRequestUri();
        $at = strpos($uri, '?');

        return UrlNormaliser::normalise($request->getPathInfo().($at === false ? '' : substr($uri, $at)));
    }

    /**
     * The site's root without its front controller: `https://example.com` or
     * `https://example.com/sub`, never `…/index.php` — a canonical names the address a visitor
     * should use, and that is the one without it.
     */
    public static function root(): string
    {
        return rtrim((string) preg_replace('~/index\.php$~i', '', rtrim(url('/'), '/')), '/');
    }

    /**
     * The `<head>` block, ready to print: what `for()` resolved, and around it what only a page
     * being served can say — its other languages, its trail, the schema.org blocks of the entity
     * and of the handler (§17.4).
     *
     * `$fallback` is what a page that is a route rather than a record is called when nobody wrote
     * a rule for it — `@webxSeo(fallback: ['title' => __('Recipes')])` on the index of recipes.
     * It stands where an entity's own name would ({@see FallbackSource}), so the title template
     * and `og:title` reach it too.
     *
     * @param  SeoData|array<string, mixed>|null  $fallback
     */
    public function head(?object $subject = null, ?string $url = null, ?string $locale = null, SeoData|array|null $fallback = null): HtmlString
    {
        $subject ??= $this->subject();
        $url ??= $this->currentUrl();
        $locale ??= $this->locale();

        $data = $this->for($url, $subject, $locale, $fallback);

        /** @var array<string, bool> $print */
        $print = (array) $this->config->get('webx-seo.print', []);

        // Asked once for both: `hreflang` prints them, `og:locale:alternate` names their languages.
        $alternates = ($print['hreflang'] ?? true) || ($print['og'] ?? true)
            ? app(Alternates::class)->for($url, $subject, $locale, $data)
            : [];

        return new HtmlString((string) $this->views->make('webx-seo::head', [
            'seo' => $data,
            'print' => $print,
            'alternates' => ($print['hreflang'] ?? true) ? $alternates : [],
            'social' => app(SocialTags::class)->for($data, $subject, $locale, $alternates),
            'blocks' => $this->blocks($data, $subject, $locale, $print),
        ])->render());
    }

    /**
     * JSON-LD that belongs to this response rather than to the entity — the articles on this
     * page of a rubric — put in by the handler before the view is rendered (§17.2).
     *
     * Kept on the request, so it dies with it: a worker that serves the next request from the
     * same process must not print this one's list.
     *
     * @param  array<string, mixed>  $block
     */
    public function push(array $block): void
    {
        if ($block === []) {
            return;
        }

        $request = $this->request();

        if ($request === null) {
            $this->pushed[] = $block;

            return;
        }

        $request->attributes->set(self::PUSHED, [...$this->pushed(), $block]);
    }

    /**
     * What has been pushed so far in this request.
     *
     * @return list<array<string, mixed>>
     */
    public function pushed(): array
    {
        $request = $this->request();

        if ($request === null) {
            return $this->pushed;
        }

        /** @var list<array<string, mixed>> $pushed */
        $pushed = (array) $request->attributes->get(self::PUSHED, []);

        return $pushed;
    }

    /**
     * One block under a key, replacing whatever was put under it before in this request.
     *
     * For what several parts of one page add to together. Two FAQ blocks on a page are two
     * callers, and `push()` from each would print two `FAQPage` — with the question they share
     * twice. Here each caller keeps what it has gathered so far and puts the whole block again;
     * the last one is the one printed. An empty block takes the key away.
     *
     * Kept on the request for the same reason as `push()`: it must die with it.
     *
     * @param  array<string, mixed>  $block
     */
    public function put(string $key, array $block): void
    {
        $request = $this->request();
        $put = $this->putBlocks();

        if ($block === []) {
            unset($put[$key]);
        } else {
            $put[$key] = $block;
        }

        if ($request === null) {
            $this->put = $put;

            return;
        }

        $request->attributes->set(self::PUT, $put);
    }

    /**
     * What has been put so far in this request, by key, in the order the keys first came.
     *
     * @return array<string, array<string, mixed>>
     */
    public function putBlocks(): array
    {
        $request = $this->request();

        if ($request === null) {
            return $this->put;
        }

        /** @var array<string, array<string, mixed>> $put */
        $put = (array) $request->attributes->get(self::PUT, []);

        return $put;
    }

    /**
     * Why the index is closed to this page, or null when it is open.
     *
     * `noindex` in the robots line, or a canonical naming another address — the page itself asks
     * search engines to take that one instead. The sitemap, the `hreflang` lines and `test-url`
     * all ask this, so that none of them can disagree with the `<head>` (§17.1, decision 2).
     *
     * @return 'noindex'|'canonical'|null
     */
    public function closedBecause(SeoData $data, string $path): ?string
    {
        if ($data->robots !== null) {
            $directives = array_map('trim', explode(',', mb_strtolower($data->robots, 'UTF-8')));

            if (in_array('noindex', $directives, true) || in_array('none', $directives, true)) {
                return 'noindex';
            }
        }

        return $this->pointsElsewhere($data->canonical, $path) ? 'canonical' : null;
    }

    /** Would the `<head>` of this address leave it open to the index? */
    public function indexable(string $path, ?object $subject, ?string $locale): bool
    {
        return $this->closedBecause($this->for($path, $subject, $locale), $path) === null;
    }

    /**
     * The part of the query that makes a different page (`?page=2`), with its question mark, or
     * nothing. The list is the config's, shared with the self canonical.
     */
    public function keptQuery(string $url): string
    {
        $query = explode('?', $url, 2)[1] ?? '';

        /** @var list<string> $keep */
        $keep = (array) $this->config->get('webx-seo.canonical.query', ['page']);
        $kept = [];

        foreach ($query === '' ? [] : explode('&', $query) as $pair) {
            if (in_array(urldecode(explode('=', $pair, 2)[0]), $keep, true)) {
                $kept[] = $pair;
            }
        }

        return $kept === [] ? '' : '?'.implode('&', $kept);
    }

    /**
     * The entity the page is about: the one the address registry found for this request.
     *
     * `<x-webx-seo::head :for="$page" />` is still the explicit way to say it, and a template that
     * renders something other than what the address belongs to has to. But a page reached
     * through `webx-ui/routing` was already looked up once, and making the template repeat the
     * lookup is how the two end up disagreeing about what the page is.
     *
     * Never in `for()`: that one is given an address to answer about — `/test-url` asks it about
     * somebody else's page — and the entity of the request being served would be the wrong
     * subject for every one of those.
     */
    public function subject(): ?object
    {
        $request = $this->request();

        return $request === null ? null : Resolution::of($request)?->entity;
    }

    /**
     * The JSON-LD the `<head>` prints for this data: the sources', the trail, the entity's own.
     *
     * For `test_url`, which has no page being rendered: what a handler or a template pushes while
     * it renders (`push()`, `put()`) exists only during that render and is not in here.
     *
     * @return list<array<string, mixed>>
     */
    public function printedJsonLd(SeoData $data, ?object $subject, string $locale): array
    {
        /** @var array<string, bool> $print */
        $print = (array) $this->config->get('webx-seo.print', []);

        return $this->blocks($data, $subject, $locale, $print);
    }

    /**
     * The JSON-LD of the page, in the order a reader of the source expects: the site's own and
     * the rules', the trail, the entity's, the handler's.
     *
     * @param  array<string, bool>  $print
     * @return list<array<string, mixed>>
     */
    private function blocks(SeoData $data, ?object $subject, string $locale, array $print): array
    {
        $blocks = ($print['json_ld'] ?? true) ? $data->jsonLd : [];

        if ($print['breadcrumbs'] ?? true) {
            $crumbs = app(Breadcrumbs::class);
            $trail = $crumbs->jsonLd($crumbs->trail($subject, $locale));

            if ($trail !== null) {
                $blocks[] = $trail;
            }
        }

        if ($print['structured_data'] ?? true) {
            if ($subject instanceof HasStructuredData) {
                array_push($blocks, ...$subject->structuredData($locale));
            }

            array_push($blocks, ...$this->pushed(), ...array_values($this->putBlocks()));
        }

        // One `FAQPage` a page (§18.5): the rule's questions and the ones `module-faq` put are
        // folded together, a question repeated by its text kept once.
        return PageFaq::fold(array_values(array_filter($blocks, static fn (array $block): bool => $block !== [])));
    }

    private function pointsElsewhere(?string $canonical, string $path): bool
    {
        if ($canonical === null) {
            return false;
        }

        $host = parse_url($canonical, PHP_URL_HOST);
        $ours = parse_url(url('/'), PHP_URL_HOST);

        if (is_string($host) && mb_strtolower($host, 'UTF-8') !== mb_strtolower((string) $ours, 'UTF-8')) {
            return true;
        }

        [$canonicalPath] = explode('?', UrlNormaliser::normalise($canonical), 2);
        [$ownPath] = explode('?', UrlNormaliser::normalise($path), 2);

        return mb_strtolower($canonicalPath, 'UTF-8') !== mb_strtolower($ownPath, 'UTF-8');
    }

    private function locale(): string
    {
        return app(Locales::class)->current();
    }

    private function request(): ?Request
    {
        $request = app()->bound('request') ? app('request') : null;

        return $request instanceof Request ? $request : null;
    }

    /**
     * The last word: the template around the title, the soft limits, and the Open Graph
     * properties that repeat what the page already said.
     */
    private function finish(SeoData $data, string $url, ?object $subject, ?string $locale, bool $fallbackTitle = false): SeoData
    {
        $site = $this->setting('general.project-name', $locale);

        // The home page called by its own name is "Home — Site"; what it is called is the site.
        $title = $fallbackTitle && $site !== null && $this->isHome($url) ? $site : $data->title;
        $title = $this->applyTemplate($title, $site, $locale);

        $data = $data->with(
            title: $this->clamp($title, 'title'),
            description: $this->clamp($data->description, 'description'),
            keywords: $this->clamp($data->keywords, 'keywords'),
            canonical: $data->canonical ?? $this->selfCanonical($url),
        );

        $og = $data->og;

        foreach ([
            'title' => $data->title,
            'description' => $data->description,
            'url' => $data->canonical ?? self::root().$url,
            // The entity knows what kind of page it is; everything else is a website (§20).
            'type' => $subject instanceof HasOpenGraph ? $subject->openGraphType() : (string) $this->config->get('webx-seo.og.type', 'website'),
        ] as $property => $fallback) {
            if (! isset($og[$property]) && is_string($fallback) && $fallback !== '') {
                $og[$property] = $fallback;
            }
        }

        return $data->with(og: $og);
    }

    /**
     * The page names itself when nobody named anything else (§17.1, decision 5).
     *
     * Without it every `?utm_source=` a newsletter appends is a separate page to a search
     * engine, with the same text as the real one. The query is kept only where it makes a
     * different page — `?page=2` of a feed is not page one — and that list is the config's, so
     * a catalogue whose filters are pages of their own can say so.
     */
    private function selfCanonical(string $url): ?string
    {
        if (! (bool) $this->config->get('webx-seo.canonical.self', true)) {
            return null;
        }

        return self::root().explode('?', $url, 2)[0].$this->keptQuery($url);
    }

    /**
     * `{title} — {site}`. A placeholder nobody filled takes the punctuation around it with it,
     * so a site without a name does not publish "Contacts —".
     *
     * A title that already names the site is left as it was written: an editor who typed
     * "About us | Acme" into a card meant exactly that, and "About us | Acme — Acme" is what
     * applying the template anyway prints. "Names" is compared on letters and digits alone, so
     * "Acme Studio" in a title is the project named "AcmeStudio" too.
     */
    private function applyTemplate(?string $title, ?string $site, ?string $locale): ?string
    {
        if ($title === null) {
            return null;
        }

        if ($site !== null && self::names($title, $site)) {
            return $title;
        }

        $template = $this->setting('seo.title-template', $locale);

        if ($template === null || ! str_contains($template, '{title}')) {
            $template = (string) $this->config->get('webx-seo.title_template', '{title}');
        }

        $replaced = str_replace(
            ['{title}', '{site}'],
            [$title, $site ?? ''],
            $template,
        );

        // Separators left hanging where a placeholder resolved to nothing.
        $replaced = (string) preg_replace('/\s*[|\x{2013}\x{2014}\-\x{00B7}]\s*$/u', '', $replaced);
        $replaced = (string) preg_replace('/^\s*[|\x{2013}\x{2014}\-\x{00B7}]\s*/u', '', $replaced);

        $replaced = trim($replaced);

        return $replaced === '' ? $title : $replaced;
    }

    /** Whether `$title` already says `$site`, spaces, case and punctuation aside. */
    private static function names(string $title, string $site): bool
    {
        $squeeze = static fn (string $text): string => (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text, 'UTF-8'));
        $needle = $squeeze($site);

        return $needle !== '' && str_contains($squeeze($title), $needle);
    }

    private function isHome(string $url): bool
    {
        return explode('?', $url, 2)[0] === '/';
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
