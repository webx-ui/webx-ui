<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Filter;

use Illuminate\Support\Facades\URL;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;

/**
 * Every address of the filter, from one builder (§7.7): the serializer writes the format, the
 * rewriters give some states addresses of their own, and this puts the language, the host and
 * the query around the result.
 *
 * One builder is what makes decision 26 of the architecture hold: a link to Apple points at the
 * Apple landing directly because the link and the landing were asked about in the same pass. The
 * rewriters are prepared once per call with every state of it — the filter asks for all its
 * links in one call, so a page prepares them once.
 */
final class FilterUrls
{
    /** @var list<FilterUrlRewriter> */
    private array $rewriters = [];

    public function __construct(
        private readonly FilterSerializer $serializer,
        private readonly SiteUrl $site,
    ) {}

    public function register(FilterUrlRewriter $rewriter): void
    {
        $this->rewriters[] = $rewriter;
    }

    /** @return list<FilterUrlRewriter> */
    public function rewriters(): array
    {
        return $this->rewriters;
    }

    public function parse(FilterContext $context, string $tail): ?FilterState
    {
        return $this->serializer->parse($context, $tail);
    }

    /**
     * The registry's spelling of each state's address — what the handler compares a request with
     * to decide on a 301.
     *
     * @param  list<FilterState>  $states
     * @return list<string>
     */
    public function paths(FilterContext $context, array $states): array
    {
        if ($states === []) {
            return [];
        }

        foreach ($this->rewriters as $rewriter) {
            $rewriter->prepare($context, $states);
        }

        $paths = $this->serializer->buildMany($context, $states);

        foreach ($states as $i => $state) {
            foreach ($this->rewriters as $rewriter) {
                $rewritten = $rewriter->rewrite($context, $state);

                if ($rewritten === null) {
                    continue;
                }

                $tail = $this->serializer->tails($context, [$rewritten->rest])[0] ?? '';
                $paths[$i] = trim(trim($rewritten->path, '/').($tail === '' ? '' : '/'.$tail), '/');

                break;
            }
        }

        return $paths;
    }

    public function path(FilterContext $context, FilterState $state): string
    {
        return $this->paths($context, [$state])[0];
    }

    /**
     * Absolute addresses, the context's query after each: what the filter prints.
     *
     * @param  list<FilterState>  $states
     * @return list<string>
     */
    public function urls(FilterContext $context, array $states): array
    {
        return array_map(fn (string $path): string => $this->absolute($context, $path), $this->paths($context, $states));
    }

    public function url(FilterContext $context, FilterState $state): string
    {
        return $this->urls($context, [$state])[0];
    }

    /**
     * Whether this state's page is open to the index (§8.1 of the architecture, decision 18):
     * exactly one value of exactly one facet, a facet that allows it, a list that is not empty,
     * and not a search. Everything else is `noindex, follow`, and its links carry `nofollow`.
     */
    public function indexable(FilterContext $context, FilterState $state, ?CatalogResult $result = null): bool
    {
        if ($context->context === FilterContext::SEARCH) {
            return false;
        }

        if ($state->isEmpty()) {
            return true;
        }

        if ($state->size() !== 1 || count($state->all()) !== 1) {
            return false;
        }

        $key = array_key_first($state->all());
        $facet = $context->facet((string) $key);
        $value = $state->get((string) $key);

        if ($facet === null || $value === null || $value->isRange() || ! $facet->indexable()) {
            return false;
        }

        return $result === null || $result->total > 0;
    }

    /** The language prefix, the path, percent-encoded, and the query every link keeps. */
    public function absolute(FilterContext $context, string $path): string
    {
        $segments = array_map('rawurlencode', array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));
        $full = UrlNormaliser::join($this->site->prefix($context->locale), implode('/', $segments));
        $query = http_build_query(array_filter($context->query, static fn (string $value): bool => $value !== ''));

        return URL::to('/'.$full).($query === '' ? '' : '?'.$query);
    }
}
