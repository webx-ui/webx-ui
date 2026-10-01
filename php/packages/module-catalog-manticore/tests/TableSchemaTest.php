<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Manticore\TableSchema;

/**
 * Decisions 5–7 and 9 of the Manticore spec, without a server: what a table of one language is
 * made of, what goes into it, and when the live one is no longer it.
 */
final class TableSchemaTest extends TestCase
{
    #[Test]
    public function the_own_language_is_the_text_and_the_others_are_one_field_weighed_lower(): void
    {
        $ru = $this->schema('ru');

        $this->assertSame(['name', TableSchema::OTHER], array_values(array_intersect(array_keys($ru->columns()), ['name', TableSchema::OTHER])));

        $row = $ru->row(['name_en' => 'Protective case', 'name_ru' => 'Чехол', 'name_de' => 'Schutzhülle', 'price' => null, 'categories' => [3, '7'], 'pn' => ['12' => 1.5]]);

        $this->assertSame('Чехол', $row['name']);
        $this->assertSame("Protective case\nSchutzhülle", $row[TableSchema::OTHER]);
        $this->assertSame(0.0, $row['price']);
        $this->assertFalse($row['price__set']);
        $this->assertSame([3, 7], $row['categories']);
        $this->assertSame(['12' => 1.5], $row['pn']);
    }

    #[Test]
    public function without_a_translation_the_main_language_stands_in_and_is_not_said_twice(): void
    {
        $row = $this->schema('de')->row(['name_en' => 'Protective case', 'name_ru' => 'Чехол', 'name_de' => '']);

        $this->assertSame('Protective case', $row['name']);
        $this->assertSame('Чехол', $row[TableSchema::OTHER]);
    }

    #[Test]
    public function the_morphology_is_of_every_language_its_own_first(): void
    {
        $this->assertSame('libstemmer_de, stem_en, lemmatize_ru_all', $this->schema('de')->settings()['morphology']);
        $this->assertSame('stem_en, lemmatize_ru_all, libstemmer_de', $this->schema('en')->settings()['morphology']);
        $this->assertStringContainsString("min_prefix_len='3'", $this->schema('en')->create('t'));
    }

    #[Test]
    public function a_live_table_of_another_shape_is_out_of_date_and_says_how(): void
    {
        $schema = $this->schema('en');
        $live = [];

        foreach ($schema->columns() as $name => $column) {
            $live[$name] = ['type' => $column['type'], 'props' => $column['props']];
        }

        $settings = ['morphology' => 'stem_en, lemmatize_ru_all, libstemmer_de', 'min_prefix_len' => '3', 'index_exact_words' => '1'];

        $this->assertNull($schema->differs(['id' => ['type' => 'bigint', 'props' => ''], ...$live], $settings));
        $this->assertStringContainsString('morphology', (string) $schema->differs($live, ['morphology' => 'stem_en', 'min_prefix_len' => '3']));
        $this->assertStringContainsString('[brand]', (string) $schema->differs([...$live, 'brand' => ['type' => 'bigint', 'props' => '']], $settings));

        unset($live['pn']);
        $this->assertStringContainsString('[pn] is missing', (string) $schema->differs($live, $settings));
    }

    private function schema(string $locale): TableSchema
    {
        return new TableSchema(
            [
                new IndexField('is_deleted', IndexField::BOOL),
                new IndexField('categories', IndexField::INT, multi: true),
                new IndexField('price', IndexField::FLOAT),
                new IndexField('pn', IndexField::JSON),
                new IndexField('name_en', IndexField::TEXT),
                new IndexField('name_ru', IndexField::TEXT),
                new IndexField('name_de', IndexField::TEXT),
            ],
            $locale,
            ['en', 'ru', 'de'],
            'en',
            ['en' => 'stem_en', 'ru' => 'lemmatize_ru_all', 'de' => 'libstemmer_de'],
        );
    }
}
