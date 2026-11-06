<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Storefront;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\View;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\View\Components\Collection;

/**
 * `[product-collection key=cameras view=carousel limit=4]`: a collection in the text of a page or an
 * article (§4.3). The same component as the tag, so everything it prints — and everything it does
 * not — is the tag's.
 *
 * A typo in the key is not told from a collection turned off: both print nothing. So a manager does
 * not type the key, the panel's `[` offers a row for each collection ({@see self::menu()}).
 */
final readonly class CollectionShortcode
{
    public const NAME = 'product-collection';

    public function __construct(private Container $container) {}

    /**
     * @param  array<string, string>  $args
     */
    public function html(array $args): string
    {
        // The list of shortcodes asks for this with no arguments at all.
        if (($args['key'] ?? '') === '') {
            return '';
        }

        $printed = $this->container->make(Collection::class, [
            'key' => $args['key'],
            'view' => $args['view'] ?? null,
            'limit' => $args['limit'] ?? null,
        ])->render();

        return $printed instanceof View ? $printed->render() : $printed;
    }

    /**
     * A ready bracket for each collection. One turned off is offered too, and says so: it may be
     * waiting for its day, and the text can be written before it.
     *
     * @return list<array{insert: string, description: string}>
     */
    public function menu(): array
    {
        $rows = [];

        foreach (ProductCollection::query()->orderByDesc('is_enabled')->orderBy('key')->get() as $collection) {
            $title = trim((string) $collection->getTranslation('title'));
            $title = $title !== '' ? $title : $collection->key;

            $rows[] = [
                'insert' => sprintf('[%s key=%s]', self::NAME, $collection->key),
                'description' => $collection->is_enabled ? $title : (string) __('webx-catalog-collections::module.menu-off', ['title' => $title]),
            ];
        }

        return $rows;
    }
}
