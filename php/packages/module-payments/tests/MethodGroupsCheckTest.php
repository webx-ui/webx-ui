<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Payments\Doctor\MethodGroupsCheck;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Tests\Fakes\DeclaringType;
use WebxUi\Payments\Types\BankTransfer;

/** What the config of the groups left behind, said by `webx:doctor` (groups spec §2.1, §2.3, §2.7). */
final class MethodGroupsCheckTest extends TestCase
{
    #[Test]
    public function it_is_registered_with_the_doctor(): void
    {
        $this->assertContains(MethodGroupsCheck::class, $this->app->make(DoctorChecks::class)->all());
    }

    #[Test]
    public function a_site_as_it_comes_says_nothing(): void
    {
        PaymentMethod::query()->update(['enabled' => true]);

        $this->assertSame([], $this->diagnoses());
    }

    #[Test]
    public function a_move_to_a_group_of_the_config_says_nothing(): void
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['group_key' => 'online']);

        $this->assertSame([], $this->diagnoses());
    }

    #[Test]
    public function a_move_to_a_group_the_config_lost_is_named(): void
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['group_key' => 'instalments']);

        [$diagnosis] = $this->diagnoses();

        $this->assertSame(Diagnosis::WARN, $diagnosis->state);
        $this->assertStringContainsString('Method bank-transfer is moved to the group instalments', $diagnosis->detail);
    }

    #[Test]
    public function an_enabled_method_whose_group_is_gone_is_named(): void
    {
        $this->app['config']->set('webx-payments.groups', ['online' => ['title' => 'Online'], 'on_receipt' => ['title' => 'On receipt']]);

        $this->assertCount(0, $this->diagnoses());

        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['enabled' => true]);

        [$diagnosis] = $this->diagnoses();

        $this->assertStringContainsString('Method bank-transfer belongs to the group transfer', $diagnosis->detail);
    }

    #[Test]
    public function a_type_declaring_an_unknown_group_is_named(): void
    {
        $this->app->make(MethodTypes::class)->register(new DeclaringType('nowhere'));

        [$diagnosis] = $this->diagnoses();

        $this->assertStringContainsString('Type store.declaring declares the group nowhere', $diagnosis->detail);
    }

    #[Test]
    public function a_pictogram_outside_the_set_is_named(): void
    {
        $this->app['config']->set('webx-payments.groups', ['a' => ['title' => 'A', 'pictogram' => 'rocket'], 'on_receipt' => ['title' => 'B'], 'transfer' => ['title' => 'C']]);

        [$diagnosis] = $this->diagnoses();

        $this->assertStringContainsString('The group a of webx-payments.groups names the pictogram rocket', $diagnosis->detail);
    }

    #[Test]
    public function a_site_without_groups_asked_for_a_flat_list_and_hears_nothing(): void
    {
        $this->app['config']->set('webx-payments.groups', []);
        PaymentMethod::query()->update(['enabled' => true, 'group_key' => 'gone']);

        $this->assertSame([], $this->diagnoses());
    }

    #[Test]
    public function a_site_not_migrated_yet_is_told_to(): void
    {
        Schema::table('payment_methods', static function ($table): void {
            $table->dropColumn('group_key');
        });

        [$diagnosis] = $this->diagnoses();

        $this->assertStringContainsString('run php artisan migrate', $diagnosis->detail);
    }

    /** @return list<Diagnosis> */
    private function diagnoses(): array
    {
        return $this->app->make(MethodGroupsCheck::class)->run();
    }
}
