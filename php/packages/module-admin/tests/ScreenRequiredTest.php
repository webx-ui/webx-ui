<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\Required;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;

/**
 * The asterisk comes from the rules, never from a label: a field the module always requires is
 * handed out `required`, and a field required only beside another — «a phone or an e-mail» — is
 * not.
 */
final class ScreenRequiredTest extends TestCase
{
    #[Test]
    public function only_a_bare_required_counts(): void
    {
        $this->assertTrue(Required::among(['required', 'string', 'max:190']));
        $this->assertTrue(Required::among('nullable|string|required'));
        $this->assertFalse(Required::among(['nullable', 'required_without:customer.email']));
        $this->assertFalse(Required::among(['required_if:kind,company', 'string']));
        $this->assertFalse(Required::among([]));
        $this->assertFalse(Required::among(null));
    }

    #[Test]
    public function the_screen_is_handed_out_with_the_flag_its_rules_give(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);
        $screens->register('demo.form', [[
            'id' => 'card',
            'type' => 'wx-card',
            'children' => [
                ['id' => 'first', 'type' => 'wx-input', 'name' => 'first_name'],
                ['id' => 'phone', 'type' => 'wx-input', 'name' => 'phone'],
                ['id' => 'email', 'type' => 'wx-input', 'name' => 'email', 'props' => ['type' => 'email']],
                ['id' => 'note', 'type' => 'wx-textarea', 'name' => 'note', 'required' => true],
                ['id' => 'rows', 'type' => 'wx-repeater', 'name' => 'rows', 'children' => [
                    ['id' => 'row-title', 'type' => 'wx-input', 'name' => 'first_name'],
                ]],
            ],
        ]]);
        $screens->rules('demo.form', [
            'first_name' => ['required', 'string', 'max:190'],
            'phone' => 'nullable|string|required_without:email',
        ]);
        $screens->rules('demo.form', static fn (): array => ['email' => ['nullable', 'required_without:phone']]);

        $root = $screens->render('demo.form');
        $flags = [];

        foreach (['first', 'phone', 'email', 'note', 'rows', 'row-title'] as $id) {
            $flags[$id] = Tree::find($root, $id)['required'] ?? false;
        }

        $this->assertSame([
            'first' => true,
            'phone' => false,
            'email' => false,
            // Written by the description for a field its project checks: kept.
            'note' => true,
            'rows' => false,
            // A repeater's row is its own: the screen's `first_name` is not this one.
            'row-title' => false,
        ], $flags);

        // What the write side validates against is the description, untouched.
        $this->assertArrayNotHasKey('required', Tree::find($screens->tree('demo.form'), 'first') ?? []);
    }
}
