<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\Role;

/**
 * A role's code out of its name is transliterated by the language the panel is open in — the
 * language the name was typed in: «Хіт» is `khit` in Ukrainian and `hit` in Russian.
 */
final class SlugLanguageTest extends TestCase
{
    #[Test]
    public function a_roles_code_is_transliterated_by_the_panels_language(): void
    {
        $this->app->setLocale('uk');
        $this->assertSame('khit', Role::freeSlug('Хіт'));

        $this->app->setLocale('ru');
        $this->assertSame('hit', Role::freeSlug('Хит'));

        // A name of no letters still makes a code that says what it is.
        $this->assertSame('role-42', Role::freeSlug('42'));
    }
}
