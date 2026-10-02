<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use WebxUi\Audit\Probes\Certificate;
use WebxUi\Audit\Probes\CertificateReader;

final class FakeCertificateReader extends CertificateReader
{
    public ?Certificate $certificate = null;

    public function read(string $host, ?string $connectTo = null, int $port = 443): ?Certificate
    {
        return $this->certificate;
    }
}
