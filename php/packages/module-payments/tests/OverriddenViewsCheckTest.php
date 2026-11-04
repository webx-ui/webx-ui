<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Payments\Doctor\OverriddenViewsCheck;

/** A theme's copy of a template from before `prompt` and `declined` (spec §8.4): the doctor says so. */
final class OverriddenViewsCheckTest extends TestCase
{
    private string $theme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->theme = sys_get_temp_dir().'/webx-overrides-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->theme);

        parent::tearDown();
    }

    #[Test]
    public function it_is_registered_with_the_doctor(): void
    {
        $this->assertContains(OverriddenViewsCheck::class, $this->app->make(DoctorChecks::class)->all());
    }

    #[Test]
    public function the_packages_own_templates_say_nothing(): void
    {
        $this->assertSame([], $this->app->make(OverriddenViewsCheck::class)->run());
    }

    #[Test]
    public function an_order_page_without_the_prompt_is_named(): void
    {
        $path = $this->override('webx-orders', 'order/body.blade.php', '<div>{{ $order[\'number\'] }}</div>');

        $found = $this->app->make(OverriddenViewsCheck::class)->run();

        $this->assertCount(1, $found);
        $this->assertSame(Diagnosis::WARN, $found[0]->state);
        $this->assertStringContainsString($path, $found[0]->detail);
        $this->assertStringContainsString('prompt', $found[0]->detail);
    }

    #[Test]
    public function a_pay_page_without_the_state_declined_is_named(): void
    {
        $path = $this->override('webx-payments', 'pay/page.blade.php', '@switch($page[\'state\']) @endswitch');

        [$diagnosis] = $this->app->make(OverriddenViewsCheck::class)->run();

        $this->assertStringContainsString($path, $diagnosis->detail);
        $this->assertStringContainsString('declined', $diagnosis->detail);
    }

    #[Test]
    public function a_copy_brought_up_to_date_says_nothing(): void
    {
        $this->override('webx-orders', 'order/body.blade.php', '{!! $order[\'prompt\'][\'html\'] ?? \'\' !!}');
        $this->override('webx-payments', 'pay/page.blade.php', '@case(\'declined\')');

        $this->assertSame([], $this->app->make(OverriddenViewsCheck::class)->run());
    }

    private function override(string $namespace, string $file, string $source): string
    {
        $path = "{$this->theme}/{$namespace}/{$file}";
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $source);
        $this->app['view']->prependNamespace($namespace, "{$this->theme}/{$namespace}");

        return $path;
    }
}
