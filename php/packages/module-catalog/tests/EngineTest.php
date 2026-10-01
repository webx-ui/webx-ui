<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Catalog;

/**
 * §8.2 and §16: `SqlEngine` — the questions every engine answers alike ({@see EngineScenarios}),
 * and what is the database's own.
 */
final class EngineTest extends EngineScenarios
{
    #[Test]
    public function the_search_looks_in_the_name_the_article_number_and_the_barcode(): void
    {
        $laptops = $this->category('laptops');
        $byName = $this->product('ThinkPad X1', $laptops);
        $bySku = $this->product('Another', $laptops, ['sku' => 'TP-777']);
        $byBarcode = $this->product('Third', $laptops, ['barcode' => '4600000777001']);
        $this->product('Unrelated', $laptops);

        $this->assertSame([$byName->id], $this->search(search: 'thinkpad')->ids);
        $this->assertEqualsCanonicalizing([$bySku->id, $byBarcode->id], $this->search(search: '777')->ids);
    }

    #[Test]
    public function the_database_engine_keeps_no_index(): void
    {
        $this->assertFalse($this->app->make(Catalog::class)->needsIndex());
    }
}
