<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Audit;

use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;

/**
 * A form view the site published before W6.4, still in use (WIDGETS §20, W6.4).
 *
 * Since W6.4 the form on the site is the widgets' form engine: `<x-webx-form>` and
 * `<x-webx-field>`, classes `webx-field-*` and `webx-form-*`. A view the site rewrote before that
 * keeps working for an alias period — the old variables, the old partials, the old script — and
 * this is how the site finds out it is living on borrowed time: the view that will stop working
 * when the alias period ends, named by its path.
 */
final class LegacyViews extends ModuleCheck
{
    protected const ID = 'inbox.legacy_views';

    protected const GROUP = 'inbox';

    protected const SEVERITY = Severity::WARNING;

    protected const NEEDS = ['config'];

    protected const NAMESPACE = 'webx-inbox';

    private const string OWN = __DIR__.'/../../resources/views/form.blade.php';

    public function __construct(private readonly ViewFactory $views) {}

    public function run(AuditContext $context): iterable
    {
        try {
            $path = $this->views->getFinder()->find('webx-inbox::form');
        } catch (InvalidArgumentException) {
            return;
        }

        if (realpath($path) === realpath(self::OWN) || str_contains((string) @file_get_contents($path), '<x-webx-form')) {
            return;
        }

        yield $this->found('legacy-views', ['path' => self::relative($path)], key: 'form');
    }

    private static function relative(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
