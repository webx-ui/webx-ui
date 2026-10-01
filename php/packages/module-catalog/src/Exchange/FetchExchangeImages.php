<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Illuminate\Auth\GenericUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Catalog\Gallery\Gallery;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;

/**
 * The pictures one row of an import asked for, downloaded by the gallery (decision 16 of the
 * exchange spec) — and, with `images: replace`, the ones it no longer names taken off.
 *
 * A job per product, after the chunk is committed: a slow supplier's server holds up this job
 * and nobody else's row. What the gallery refuses is written into the run's errors under the row
 * that asked, rather than failing the job: the rest of that row's pictures still come.
 */
final class FetchExchangeImages implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @param  list<string>  $urls
     * @param  list<int>  $remove
     */
    public function __construct(
        public readonly int $productId,
        public readonly array $urls,
        public readonly array $remove,
        public readonly ?int $historyRunId,
        public readonly ?int $adminId,
        public readonly string $adminName,
        public readonly ?int $exchangeRunId,
        public readonly int $row,
    ) {}

    public function handle(Gallery $gallery, HistoryContext $context): void
    {
        $product = Product::query()->find($this->productId);

        if (! $product instanceof Product) {
            return;
        }

        $admin = $this->adminId === null ? null : new GenericUser(['id' => $this->adminId, 'name' => $this->adminName]);

        $context->during(HistoryContext::IMPORT, $admin, null, fn () => $context->inRun($this->historyRunId, HistoryContext::IMPORT, function () use ($gallery, $product): void {
            foreach (ProductImage::query()->where('product_id', $product->id)->whereKey($this->remove)->get() as $image) {
                $gallery->remove($product, $image);
            }

            foreach ($this->urls as $url) {
                try {
                    $image = $gallery->add($product, $url);

                    if ($image instanceof ProductImage) {
                        $image->forceFill(['source_hash' => sha1($url)])->save();
                    }
                } catch (ValidationException $refused) {
                    $this->error($url, (string) collect($refused->errors())->flatten()->first());
                } catch (Throwable $failure) {
                    report($failure);
                    $this->error($url, $failure->getMessage());
                }
            }
        }));
    }

    private function error(string $url, string $message): void
    {
        // The run may have been pruned while the job waited: nobody is left to tell.
        if ($this->exchangeRunId === null || ! DB::table('catalog_exchange_runs')->where('id', $this->exchangeRunId)->exists()) {
            return;
        }

        DB::table('catalog_exchange_errors')->insert([
            'run_id' => $this->exchangeRunId,
            'row' => $this->row,
            'column' => 'images',
            'value' => mb_substr($url, 0, 500),
            'message' => mb_substr($message, 0, 255),
        ]);
    }
}
