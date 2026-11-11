<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Audit;

use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Inbox\Fields\Steps;
use WebxUi\Inbox\Models\Form;

/**
 * A form of steps, and a view of the site that does not print them (FORM_STEPS §12.5).
 *
 * The site's own `form.blade.php` — published before the steps — goes through `$controls`, which is
 * still every field of the form in one flat list: the form works, as one long page, the way a page
 * without JavaScript sees it, and the server checks and keeps what it always did. Nothing breaks
 * where anybody looks, which is why it has to be said: the editor put steps in, and the site does
 * not show them.
 */
final class StepsInOldView extends ModuleCheck
{
    protected const ID = 'inbox.steps_in_old_view';

    protected const GROUP = 'inbox';

    protected const SEVERITY = Severity::WARNING;

    protected const NEEDS = ['database'];

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

        if (realpath($path) === realpath(self::OWN)) {
            return;
        }

        $source = (string) @file_get_contents($path);

        // A view before the engine is `inbox.legacy_views`' to name; one that prints the steps is right.
        if (! str_contains($source, '<x-webx-form') || str_contains($source, '$steps')) {
            return;
        }

        $forms = Form::query()->enabled()->with('liveFields')->orderBy('position')->orderBy('id')->get();

        foreach ($forms as $form) {
            if (Steps::groups($form->liveFields) === []) {
                continue;
            }

            $title = FormTitle::of($form);

            yield $this->found('steps-in-old-view', ['form' => $title, 'slug' => $form->slug, 'path' => self::relative($path)], key: (string) $form->id, table: [
                'columns' => [Finding::column('title'), Finding::column('value'), Finding::column('edit', 'edit')],
                'rows' => [['title' => $title, 'value' => $form->slug, 'edit' => '/inbox/forms/'.$form->id.'?tab=fields']],
            ]);
        }
    }

    private static function relative(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
