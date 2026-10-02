<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hosts;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Page\LinkCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A link or a picture written with the site’s own host — it works, and breaks at the next move.
 *
 * What the layout prints on most pages (a menu built with `url()`) is left out: that is the
 * template’s choice, made once, and listing it on every page would bury the links an editor
 * pasted into the content, which is what this check is for.
 */
final class AbsoluteOwn extends LinkCheck
{
    protected const ID = 'hosts.absolute_own';

    protected const SEVERITY = Severity::NOTICE;

    protected const GROUP = 'hosts';

    protected const SUMMARY = 'absolute-own';

    protected function links(AuditContext $context): Builder
    {
        $pages = AuditPage::query()->where('run_id', $context->run->id)->html()->count();

        $template = AuditLink::query()
            ->where('run_id', $context->run->id)
            ->where('host_class', HostClassifier::OWN)
            ->groupBy('to_url')
            ->havingRaw('count(distinct from_page_id) > ?', [max(1, intdiv($pages, 2))])
            ->pluck('to_url')
            ->all();

        return $this->query($context)
            ->whereIn('kind', [AuditLink::A, AuditLink::IMG])
            ->where('host_class', HostClassifier::OWN)
            ->where('absolute', true)
            ->when($template !== [], static fn (Builder $query) => $query->whereNotIn('to_url', $template));
    }
}
