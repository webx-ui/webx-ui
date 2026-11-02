<?php

declare(strict_types=1);

namespace WebxUi\Geography\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebxUi\Geography\Normalizer;

final class NormalizerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function spellings(): iterable
    {
        yield 'lower case, trimmed, spaces collapsed' => ["  Town   D \t", 'town d'];
        yield 'NFKC: a ligature' => ['Oﬁeld', 'ofield'];
        yield 'NFKC: full-width letters' => ['Ｔｏｗｎ Ａ', 'town a'];
        yield 'NFKC: a non-breaking space' => ["Town\u{00A0}B", 'town b'];
        yield 'apostrophe: right single quote' => ['O’Town', "o'town"];
        yield 'apostrophe: modifier letter' => ['Oʼtown', "o'town"];
        yield 'apostrophe: backtick' => ['O`town', "o'town"];
        yield 'apostrophe: plain' => ["O'town", "o'town"];
        yield 'en dash' => ['Town–on–Sea', 'town-on-sea'];
        yield 'em dash' => ['Town—on—Sea', 'town-on-sea'];
        yield 'minus sign' => ['Town−on−Sea', 'town-on-sea'];
        yield 'quotes are dropped' => ['«Town» "E"', 'town e'];
        yield 'dots are dropped' => ['St. Town', 'st town'];
        yield 'any alphabet, no language rules' => ['Πόλη Α', 'πόλη α'];
        yield 'a letter with a diacritic stays itself' => ['Städt-Nord', 'städt-nord'];
    }

    #[DataProvider('spellings')]
    public function test_a_name_folds_into_its_key(string $name, string $key): void
    {
        $this->assertSame($key, Normalizer::key($name));
    }

    public function test_two_carriers_spelling_one_place_get_one_key(): void
    {
        $this->assertSame(Normalizer::key('Oʼtown–Upper'), Normalizer::key("o'town-upper"));
    }

    public function test_a_key_fits_its_column(): void
    {
        $this->assertSame(191, mb_strlen(Normalizer::key(str_repeat('я', 300))));
    }

    public function test_tails_start_at_every_later_word(): void
    {
        $this->assertSame(['town d', 'd'], Normalizer::tails('lower town d'));
        $this->assertSame(['on-sea', 'sea'], Normalizer::tails('town-on-sea'));
        $this->assertSame([], Normalizer::tails('town'));
    }

    public function test_a_like_prefix_takes_wildcards_literally(): void
    {
        $this->assertSame('a!%b!_c!!%', Normalizer::likePrefix('a%b_c!'));
    }
}
