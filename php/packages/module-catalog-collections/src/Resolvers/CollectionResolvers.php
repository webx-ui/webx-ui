<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Resolvers;

/**
 * Who fills a collection, by its `source`.
 */
final class CollectionResolvers
{
    /** @var array<string, CollectionResolver> */
    private array $resolvers = [];

    public function register(CollectionResolver $resolver): void
    {
        $this->resolvers[$resolver->source()] = $resolver;
    }

    public function find(string $source): ?CollectionResolver
    {
        return $this->resolvers[$source] ?? null;
    }
}
