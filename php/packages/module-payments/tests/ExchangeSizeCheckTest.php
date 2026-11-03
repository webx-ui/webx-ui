<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Payments\Doctor\ExchangeSizeCheck;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;

/** The journal only grows, so the doctor says when it is big (spec §20.4) — a note, never a stop. */
final class ExchangeSizeCheckTest extends TestCase
{
    #[Test]
    public function it_is_registered_with_the_doctor(): void
    {
        $this->assertContains(ExchangeSizeCheck::class, $this->app->make(DoctorChecks::class)->all());
    }

    #[Test]
    public function a_journal_below_the_size_says_nothing(): void
    {
        $this->assertSame([], $this->app->make(ExchangeSizeCheck::class)->run());
    }

    #[Test]
    public function a_journal_over_the_size_names_the_command(): void
    {
        $this->app['config']->set('webx-payments.exchange.warn_size', 10);
        $this->app->make(ExchangeJournal::class)->record(new ExchangeEntry(
            kind: ExchangeKind::Notify,
            trace: ExchangeContext::newTrace(),
            requestBody: str_repeat('x', 100),
        ), $this->secrets());

        [$diagnosis] = $this->app->make(ExchangeSizeCheck::class)->run();

        $this->assertSame(Diagnosis::WARN, $diagnosis->state);
        $this->assertTrue($diagnosis->note);
        $this->assertStringContainsString('webx:payments:exchange-prune --days=365', $diagnosis->detail);
    }
}
