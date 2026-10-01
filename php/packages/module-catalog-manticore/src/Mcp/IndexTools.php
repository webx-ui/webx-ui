<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Mcp;

use WebxUi\Catalog\Manticore\IndexStatus;
use WebxUi\Mcp\Tool;

/**
 * `catalog_index_status` (decision 28): the search index to an agent, read only — so that it can
 * answer why a product is not found: the server is down, the table is out of date, the product
 * waits in the queue, or it is in the index as unpublished. The rebuild stays a person's, from
 * the panel or the console: it is minutes of load, and its time is theirs to choose.
 *
 * Served as the catalogue's own tool, with its scope and permission, and only on the Manticore
 * engine: on the database engine there is no index to ask about.
 */
final class IndexTools
{
    public function __construct(private readonly IndexStatus $status) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        if (! $this->status->active()) {
            return [];
        }

        return [
            Tool::read(
                'index_status',
                'The search index of the catalogue (Manticore): whether the server answers, each language\'s table — '
                .'products in it against products in the database, and whether its schema is out of date — the queue '
                .'of products waiting to be written, and a rebuild under way. With `product`, also why that product is '
                .'or is not found: deleted, unpublished or hidden in the database, waiting in the queue since when, '
                .'and what each table holds for it. A rebuild is started by a person, not here.',
                fn (array $arguments): array => [
                    ...$this->status->report(),
                    ...(isset($arguments['product']) ? ['product' => $this->status->product((int) $arguments['product'])] : []),
                ],
                ['properties' => [
                    'product' => ['type' => 'integer', 'description' => 'A product id, as catalog_products_list returns it.'],
                ]],
            ),
        ];
    }
}
