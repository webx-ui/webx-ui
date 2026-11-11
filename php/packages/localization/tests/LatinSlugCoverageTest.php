<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use WebxUi\Localization\HasLatinSlug;

/**
 * No model takes a slug past the rule: a new module with a `slug` either has an address (`HasUrl`
 * brings {@see HasLatinSlug}), uses the trait by name, or says here why it needs neither. Reads the
 * source of every package, so runs only in the monorepo.
 */
final class LatinSlugCoverageTest extends PlainTestCase
{
    /**
     * Models with a `slug` the rule does not need, with the reason.
     *
     * @var array<string, string>
     */
    private const array ALLOWED = [
        'module-auth/src/Models/CmsUser.php' => 'only asks a role by its slug',
        'module-auth/src/Models/Role.php' => 'a code: brought to `[a-z0-9][a-z0-9-]*` by HasCodes, not an address',
        'module-blocks/src/Models/Block.php' => 'a code: brought to `[a-z][a-z0-9-]*` by HasCodes, not an address',
        'module-catalog-properties/src/Models/PropertyGroup.php' => 'emptied on every save: a group has no page',
        'module-faq/src/Models/FaqCategory.php' => 'emptied on every save',
        'module-inbox/src/Models/Form.php' => 'a code of a form: brought by HasCodes, not an address',
        'module-recipes/src/Models/RecipeNutrient.php' => 'emptied on every save',
        'module-reviews/src/Models/ReviewCategory.php' => 'emptied on every save',
        'module-tariffs/src/Models/TariffCategory.php' => 'emptied on every save',
        'module-tasks/src/Models/Project.php' => 'a code of the task queue: brought by HasCodes, not an address',
    ];

    #[Test]
    public function every_model_with_a_slug_is_held_to_the_rule(): void
    {
        $root = dirname(__DIR__, 2);

        if (count(glob($root.'/*/composer.json') ?: []) < 2) {
            $this->markTestSkipped('Not in the monorepo: there are no neighbouring packages to check.');
        }

        $found = [];

        foreach (glob($root.'/*/src/Models/*.php') ?: [] as $path) {
            $relative = substr($path, strlen($root) + 1);
            $source = (string) file_get_contents($path);

            if (! str_contains($source, "'slug'") || isset(self::ALLOWED[$relative])) {
                continue;
            }

            if (preg_match('/^\s+use [^;]*\b(HasUrl|HasLatinSlug)\b/m', $source) !== 1) {
                $found[] = $relative;
            }
        }

        $this->assertSame([], $found, 'A model with a slug that neither has an address nor uses HasLatinSlug.');
    }
}
