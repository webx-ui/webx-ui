<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests\Fixtures;

use WebxUi\CatalogFeeds\Formats\FeedContext;
use WebxUi\CatalogFeeds\Formats\FeedFormat;
use WebxUi\CatalogFeeds\Formats\FeedWriter;

/**
 * A structure no template writes: a JSON array whose count stands before the products, and a comma
 * only a class that keeps nothing between jobs can place, from what the writer says.
 */
final class LinesFormat implements FeedFormat
{
    public ?string $refusal = null;

    /** @var list<string> */
    public array $names = ['sku', 'title'];

    public function key(): string
    {
        return 'lines';
    }

    public function label(): string
    {
        return 'Lines of a marketplace';
    }

    public function extension(): string
    {
        return 'lines';
    }

    public function contentType(): string
    {
        return 'application/x-lines';
    }

    public function variables(): array
    {
        return $this->names;
    }

    public function open(FeedWriter $out, FeedContext $context): void
    {
        $out->write('{"shop":'.json_encode($context->values['feed.name']).',"count":'.$out->written().',"items":[');
    }

    public function item(FeedWriter $out, array $values): void
    {
        $out->write(($out->written() > 0 ? ',' : '').json_encode(array_map(static fn (string $name): mixed => $values[$name], $this->names)));
    }

    public function close(FeedWriter $out, FeedContext $context): void
    {
        $out->write(']}');
    }

    public function validate(string $path): ?string
    {
        return $this->refusal;
    }
}
