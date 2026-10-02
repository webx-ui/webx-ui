<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** `/index.php` and friends answer 200: the home page, and a section, under a second address. */
final class IndexFiles extends Check
{
    protected const ID = 'host.index_files';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::ERROR;

    public function run(AuditContext $context): iterable
    {
        foreach ($context->probes->prefixed('index:') as $key => $answer) {
            if ($answer->ok()) {
                $path = substr($key, strlen('index:'));

                yield $this->found('index-file', ['path' => $path], $answer->url, key: $path);
            }
        }
    }
}
