<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Models\PaymentMethod;

/** A gateway's keys never leave by accident (spec §15): not in a string, a dump, JSON or the database. */
final class CredentialsTest extends TestCase
{
    #[Test]
    public function the_keys_turn_into_hidden_wherever_they_might_be_printed(): void
    {
        $credentials = Credentials::of(['secret_key' => self::SECRET, 'empty' => '', 'shop_id' => 'shop-a']);

        $this->assertSame(self::SECRET, $credentials->get('secret_key'));
        $this->assertSame(['secret_key', 'shop_id'], $credentials->names());
        $this->assertFalse($credentials->has('empty'));
        $this->assertSame('[hidden]', (string) $credentials);
        $this->assertSame('"[hidden]"', json_encode($credentials));
        $this->assertStringNotContainsString(self::SECRET, print_r($credentials, true));
        $this->assertStringNotContainsString(self::SECRET, (string) json_encode(['keys' => $credentials]));

        ob_start();
        var_dump($credentials);
        $this->assertStringNotContainsString(self::SECRET, (string) ob_get_clean());

        $this->expectException(LogicException::class);
        serialize($credentials);
    }

    #[Test]
    public function a_methods_keys_are_encrypted_in_the_database_and_hidden_from_its_array(): void
    {
        $method = PaymentMethod::query()->create([
            'key' => 'gateway-a-card',
            'type' => 'gateway-a.card',
            'title' => ['en' => 'Card'],
            'credentials' => ['secret_key' => self::SECRET],
        ]);

        $stored = (string) DB::table('payment_methods')->where('id', $method->id)->value('credentials');
        $this->assertStringNotContainsString(self::SECRET, $stored);
        $this->assertSame(['secret_key' => self::SECRET], PaymentMethod::query()->findOrFail($method->id)->credentials);
        $this->assertArrayNotHasKey('credentials', $method->toArray());
        $this->assertStringNotContainsString(self::SECRET, $method->toJson());
        $this->assertSame(['mode' => 'any'], $method->fresh()?->delivery_rule);
    }
}
