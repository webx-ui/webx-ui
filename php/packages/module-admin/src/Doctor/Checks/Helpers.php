<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use ReflectionFunction;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Setup\Catalogue;

/**
 * The global functions the packages give templates, and whether they are the packages' at all.
 *
 * Each is declared behind `function_exists()`, because redeclaring a function the site already has
 * is a fatal error at boot. The price is silence the other way: a site with its own `services()`
 * keeps it, and `services()->in('implants')` in a block is then a call into somebody else's code
 * that fails somewhere nobody would look. Asking where the function was declared is one line.
 */
final class Helpers implements Check
{
    /** Function → the package that declares it in its `src/helpers.php`. */
    private const HELPERS = [
        'menu' => 'webx-ui/module-menu',
        'recipes' => 'webx-ui/module-recipes',
        'reviews' => 'webx-ui/module-reviews',
        'services' => 'webx-ui/module-services',
    ];

    public function __construct(private readonly Catalogue $catalogue) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $found = [];

        foreach (self::HELPERS as $function => $package) {
            if (! $this->catalogue->has($package) || ! function_exists($function)) {
                continue;
            }

            $file = str_replace('\\', '/', (string) (new ReflectionFunction($function))->getFileName());
            $ours = str_ends_with($file, '/'.substr($package, (int) strpos($package, '/') + 1).'/src/helpers.php');

            $found[] = $ours
                ? Diagnosis::ok("{$function}()", "from {$package}")
                : Diagnosis::fail("{$function}()", "declared by {$file}, not by {$package} — rename the site's own function; templates calling {$function}() reach it instead");
        }

        return $found;
    }
}
