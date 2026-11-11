<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use WebxUi\Localization\HasCodes;
use WebxUi\Localization\Slug;

/**
 * No code somebody types is refused for its shape (#125): it is brought to it — on the model
 * ({@see HasCodes}), in a request that checks it before the model sees it ({@see Slug::code()}),
 * and in the panel (`wx-code`). A new code field that skips any of the three fails here; one that
 * needs none says why below. Reads the source of every package, so runs only in the monorepo.
 *
 * The panel's own dialogs are held to the same in `@webx-ui/module-admin`'s `codeCoverage.test.ts`.
 */
final class CodeCoverageTest extends PlainTestCase
{
    /** The columns a person or an agent types a machine name into, as a string or a `@property`. */
    private const string COLUMN = '/\'(code|key|toggle_slug)\'|@property\s+\S+\s+\$(code|key|toggle_slug)\b/';

    /** A slug that is no address is a code too: an address is {@see HasLatinSlug}'s. */
    private const string SLUG = '/\'slug\'|@property\s+\S+\s+\$slug\b/';

    /**
     * Models with a code column that do not bring it to its shape, with the reason.
     *
     * @var array<string, string>
     */
    private const array MODELS = [
        'geography/src/Models/GeoCountry.php' => 'an ISO code of a data package, never typed',
        'geography/src/Models/GeoLocality.php' => 'a code of a data package, never typed',
        'geography/src/Models/GeoRegion.php' => 'a code of a data package, never typed',
        'localization/src/Models/Locale.php' => 'a language code chosen from a list',
        'module-auth/src/Models/CmsUser.php' => 'only asks a role by its slug',
        'module-catalog-properties/src/Models/PropertyGroup.php' => 'a slug emptied on every save: a group has no page',
        'module-faq/src/Models/FaqCategory.php' => 'a slug emptied on every save',
        'module-recipes/src/Models/RecipeNutrient.php' => 'a slug emptied on every save',
        'module-reviews/src/Models/ReviewCategory.php' => 'a slug emptied on every save',
        'module-tariffs/src/Models/TariffCategory.php' => 'a slug emptied on every save',
        'module-catalog-feeds/src/Models/Taxonomy.php' => 'a key of a published taxonomy, imported',
        'module-catalog-labels/src/Models/Label.php' => 'brought by Dictionary::codeOf()',
        'module-catalog-stock/src/Models/StockStatus.php' => 'brought by Dictionary::codeOf()',
        'module-delivery/src/Models/DeliveryCarrier.php' => 'a key of a carrier registered in code',
        'module-delivery/src/Models/DeliveryMethod.php' => 'made of the type by MethodForm::key(), never typed',
        'module-menu/src/Models/MenuItem.php' => 'reads the key of its menu, has none of its own',
        'module-orders/src/Models/OrderBenefit.php' => 'a key of a benefit the pricing chain gives, copied into the order',
        'module-payments/src/Models/PaymentGroup.php' => 'a key of a group declared in the config, the row holds edits',
        'module-payments/src/Models/PaymentMethod.php' => 'made of the type by MethodForm::key(), never typed',
        'module-settings/src/Models/Setting.php' => 'a key of a setting declared in code',
        'module-snippets/src/Models/Snippet.php' => 'the HTML of a snippet, not a machine name',
        'module-users/src/Models/ProviderSettings.php' => 'a key of a sign-in provider registered in code',
        'module-users/src/Models/User.php' => 'a one-time code of a sign-in, made by the site',
    ];

    /**
     * Files that refuse a code-shaped value without bringing it first, with the reason.
     *
     * @var array<string, string>
     */
    private const array RULES = [
        'module-catalog-feeds/src/Http/TaxonomyController.php' => 'a language tag of a published taxonomy',
        'module-seo/src/Http/Requests/SeoUrlRequest.php' => 'a robots directive, not a machine name',
        'module-seo/src/Screens/SeoFieldType.php' => 'a robots directive, not a machine name',
    ];

    /**
     * Screen fields named like a code that are not `wx-code`, as `<file>#<id>`, with the reason.
     *
     * @var array<string, string>
     */
    private const array FIELDS = [
        'module-catalog-collections/resources/screens/collection-form.json#key' => 'renders WxCodeField, read-only once the collection is saved',
        'module-blog/resources/screens/author-form.json#slug' => 'an address: HasLatinSlug brings it',
        'module-vacancies/resources/screens/category-form.json#slug' => 'an address: HasLatinSlug brings it',
        'widgets/resources/screens/settings.captcha.json#captcha-recaptcha-key' => 'an API key of a captcha service',
        'widgets/resources/screens/settings.captcha.json#captcha-turnstile-key' => 'an API key of a captcha service',
        'widgets/resources/screens/settings.map.json#map-google-key' => 'an API key of Google Maps',
        'widgets/resources/screens/settings.map.json#map-google-no-key' => 'an alert that the API key is missing, not a field',
    ];

    /** Field types that are not typed, or are an address held by {@see HasLatinSlug}. */
    private const string SPARED = '/^(wx-code|wx-slug|wx-[a-z-]+-slug|wx-select|wx-autocomplete|wx-segmented|wx-code-editor)$/';

    #[Test]
    public function every_model_with_a_code_brings_it_to_its_shape(): void
    {
        $found = [];

        foreach ($this->sources('/*/src/Models/*.php') as $relative => $source) {
            $address = preg_match('/^\s+use [^;]*\b(HasUrl|HasLatinSlug)\b/m', $source) === 1;
            $code = preg_match(self::COLUMN, $source) === 1 || (! $address && preg_match(self::SLUG, $source) === 1);

            if (! $code || isset(self::MODELS[$relative])) {
                continue;
            }

            if (preg_match('/^\s+use [^;]*\bHasCodes\b/m', $source) !== 1 && ! str_contains($source, 'Slug::code(')) {
                $found[] = $relative;
            }
        }

        $this->assertSame([], $found, 'A model with a code column that neither uses HasCodes nor calls Slug::code().');
        $this->assertSame([], $this->stale(self::MODELS), 'An exception for a file that is gone.');
    }

    #[Test]
    public function no_request_refuses_a_code_it_did_not_bring_first(): void
    {
        $found = [];

        foreach ($this->sources('/*/src/{,*/,*/*/}*.php') as $relative => $source) {
            // A rule of a lower-case machine name: `regex:/^[a-z…` or a `CODE`/`KEY` pattern.
            $refuses = preg_match("~'regex:/\\^\\[a-z|regex:'\\s*\\.\\s*[A-Za-z]+::(CODE|KEY)\\b~", $source) === 1;

            if (! $refuses || isset(self::RULES[$relative])) {
                continue;
            }

            if (! str_contains($source, 'codeFor(') && ! str_contains($source, 'Slug::code(')) {
                $found[] = $relative;
            }
        }

        $this->assertSame([], $found, 'A rule refuses a code it never brought: call codeFor() or Slug::code() first.');
        $this->assertSame([], $this->stale(self::RULES), 'An exception for a file that is gone.');
    }

    #[Test]
    public function every_code_field_of_a_screen_is_wx_code(): void
    {
        $found = [];

        foreach ($this->sources('/*/resources/screens/*.json') as $relative => $source) {
            $walk = function (mixed $node) use (&$walk, &$found, $relative): void {
                if (! is_array($node)) {
                    return;
                }

                $id = $node['id'] ?? null;
                $type = $node['type'] ?? null;

                if (is_string($id) && is_string($type) && preg_match('/(^|[._-])(code|key|slug)$/', $id) === 1
                    && preg_match(self::SPARED, $type) !== 1 && ! isset(self::FIELDS[$relative.'#'.$id])) {
                    $found[] = "{$relative}#{$id} ({$type})";
                }

                array_map($walk, $node);
            };

            $walk(json_decode($source, true));
        }

        $this->assertSame([], $found, 'A code field of a screen that is not wx-code.');
    }

    /**
     * @return array<string, string> path from the packages folder → source
     */
    private function sources(string $pattern): array
    {
        $root = dirname(__DIR__, 2);

        if (count(glob($root.'/*/composer.json') ?: []) < 2) {
            $this->markTestSkipped('Not in the monorepo: there are no neighbouring packages to check.');
        }

        $sources = [];

        foreach (glob($root.$pattern, GLOB_BRACE) ?: [] as $path) {
            $sources[substr($path, strlen($root) + 1)] = (string) file_get_contents($path);
        }

        return $sources;
    }

    /**
     * @param  array<string, string>  $allowed
     * @return list<string>
     */
    private function stale(array $allowed): array
    {
        $root = dirname(__DIR__, 2);

        return array_values(array_filter(
            array_keys($allowed),
            static fn (string $relative): bool => ! is_file($root.'/'.explode('#', $relative)[0]),
        ));
    }
}
