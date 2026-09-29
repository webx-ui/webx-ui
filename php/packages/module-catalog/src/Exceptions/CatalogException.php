<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Models\Product;

/**
 * What the catalogue refuses, as the 422 a refused form is — with one thing a plain validation
 * error cannot carry: `meta`, the facts the editor's next move depends on. Which product holds
 * the article number, and where to open it; how many products and subcategories stand in the way
 * of a delete.
 *
 * A validation exception so that every door that already knows how to show one — a form, an
 * agent's tool — shows this one too without learning anything.
 */
final class CatalogException extends ValidationException
{
    /** @var array<string, mixed> */
    public array $meta = [];

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function make(string $message, array $errors = [], array $meta = []): self
    {
        $validator = Validator::make([], []);

        foreach ($errors as $field => $messages) {
            foreach ($messages as $one) {
                $validator->errors()->add($field, $one);
            }
        }

        $exception = new self($validator);
        $exception->message = $message;
        $exception->meta = $meta;

        return $exception;
    }

    public static function skuTaken(Product $holder): self
    {
        $name = $holder->displayName();
        $message = (string) __('webx-catalog::errors.sku-taken', ['name' => $name]);

        return self::make($message, ['sku' => [$message]], [
            'taken_by' => [
                'id' => (int) $holder->getKey(),
                'name' => $name,
                // Where the panel opens it: a deleted product too, from the section of the deleted.
                'url' => URL::to(trim((string) config('webx-admin.path', 'cms'), '/').'/catalog/products/'.$holder->getKey()),
                'deleted' => $holder->trashed(),
            ],
        ]);
    }

    public static function publishNeedsCategory(): self
    {
        $message = (string) __('webx-catalog::errors.publish-needs-category');

        return self::make($message, ['category_id' => [$message]]);
    }

    public static function categoryNotEmpty(int $products, int $children): self
    {
        $message = (string) __($children > 0 && $products === 0
            ? 'webx-catalog::errors.category-has-children'
            : 'webx-catalog::errors.category-has-products', ['count' => $products > 0 ? $products : $children]);

        return self::make($message, [], ['products' => $products, 'children' => $children]);
    }

    public static function slugHasUnderscore(): self
    {
        $message = (string) __('webx-catalog::errors.slug-underscore');

        return self::make($message, ['slug' => [$message]]);
    }

    public static function unknownCategory(string $field): self
    {
        $message = (string) __('webx-catalog::errors.unknown-category');

        return self::make($message, [$field => [$message]]);
    }

    public static function moveIntoItself(): self
    {
        $message = (string) __('webx-catalog::errors.move-into-itself');

        return self::make($message, ['parent_id' => [$message]]);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        $body = ['message' => $this->getMessage(), 'errors' => (object) $this->errors()];

        if ($this->meta !== []) {
            $body['meta'] = $this->meta;
        }

        return new JsonResponse($body, 422);
    }
}
