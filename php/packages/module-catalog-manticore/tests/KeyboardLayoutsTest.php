<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebxUi\Catalog\Manticore\KeyboardLayouts;

/**
 * Decision 16 of the Manticore spec, without a server: a search typed with the wrong layout on,
 * spelt as it was meant — only among the languages of the site.
 */
final class KeyboardLayoutsTest extends TestCase
{
    #[Test]
    public function a_search_typed_with_the_other_layout_is_spelt_as_meant(): void
    {
        $layouts = $this->layouts();

        $this->assertSame('чехол', $layouts->alternatives('xt[jk', ['en', 'ru'], 'ru')[0]);
        $this->assertSame('ЧЕХОЛ', $layouts->alternatives('XT{JK', ['en', 'ru'], 'en')[0]);
        $this->assertSame(['ghbdtn'], $layouts->alternatives('привет', ['en', 'ru'], 'en'));
        // Digits and spaces are the same keys everywhere.
        $this->assertSame('чехол 12', $layouts->alternatives('xt[jk 12', ['en', 'ru'], 'ru')[0]);
    }

    #[Test]
    public function only_the_languages_of_the_site_are_tried(): void
    {
        $layouts = $this->layouts();

        $this->assertSame([], $layouts->alternatives('xt[jk', ['en'], 'en'));
        $this->assertSame([], $layouts->alternatives('xt[jk', ['en', 'fr'], 'en'));
        // The page's own language first.
        $this->assertSame(['чехол', 'xtüjk'], $layouts->alternatives('xt[jk', ['en', 'de', 'ru'], 'ru'));
        $this->assertSame('чехол', $layouts->alternatives('xt[jk', ['en', 'ru-RU'], 'ru-RU')[0]);
    }

    private function layouts(): KeyboardLayouts
    {
        return new KeyboardLayouts([
            'en' => ['`1234567890-=qwertyuiop[]\asdfghjkl;\'zxcvbnm,./', '~!@#$%^&*()_+QWERTYUIOP{}|ASDFGHJKL:"ZXCVBNM<>?'],
            'ru' => ['ё1234567890-=йцукенгшщзхъ\фывапролджэячсмитьбю.', 'Ё!"№;%:?*()_+ЙЦУКЕНГШЩЗХЪ/ФЫВАПРОЛДЖЭЯЧСМИТЬБЮ,'],
            'de' => ['^1234567890ß´qwertzuiopü+#asdfghjklöäyxcvbnm,.-', '°!"§$%&/()=?`QWERTZUIOPÜ*\'ASDFGHJKLÖÄYXCVBNM;:_'],
            'fr' => ['too short', 'to be a layout'],
        ]);
    }
}
