<?php

declare(strict_types=1);

namespace WebxUi\Orders\Demo;

use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Models\OrderField;
use WebxUi\Orders\OrderFields;

/**
 * The extra fields of the checkout (WEBX_UI_ORDER_FIELDS.md §10, stage 8), one or more in each of
 * the three places of the first version: a company behind a checkbox in the contacts, the
 * installation and the time of delivery before the comment, "no call back" after it. Written by
 * {@see OrderFields}, as the panel writes them, so the demo shows what a real site would have.
 *
 * A site that has fields of its own gets none: they are somebody's, and a key taken would refuse.
 */
final class OrderFieldsDemo
{
    public function __construct(private readonly OrderFields $fields) {}

    /** @return bool whether the demo's own fields are there now: only then do the demo orders answer them */
    public function seed(DemoLedger $ledger): bool
    {
        if (OrderField::query()->exists()) {
            $ledger->note('The site already has extra fields of the checkout; the demo left them alone.');

            return false;
        }

        foreach (self::definitions() as $input) {
            $plan = $this->fields->write($input);
            $ledger->created(OrderField::query()->findOrFail($plan->id), $input['key']);
        }

        return true;
    }

    /**
     * The answers of the demo orders, by the order's place in the demo, as the manager would give
     * them: the checkout has its own way, and an order has no draft to take them from.
     *
     * @return array<int, array<string, string|true>>
     */
    public static function answers(): array
    {
        return [
            4 => ['for_company' => true, 'company_name' => 'Spare Parts Trading Ltd', 'company_code' => 'WX-9042', 'delivery_time' => 'morning'],
            7 => ['installation' => true, 'delivery_time' => 'evening', 'no_callback' => true],
            11 => ['delivery_time' => 'afternoon'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function definitions(): array
    {
        return [
            [
                'key' => 'for_company',
                'type' => OrderField::CHECKBOX,
                'place' => FieldPlaces::CONTACTS,
                'label' => ['en' => 'I am buying for a company', 'ru' => 'Покупаю для компании', 'uk' => 'Купую для компанії'],
            ],
            [
                'key' => 'company_name',
                'type' => OrderField::TEXT,
                'parent' => 'for_company',
                'required' => true,
                'label' => ['en' => 'Company name', 'ru' => 'Название компании', 'uk' => 'Назва компанії'],
            ],
            [
                'key' => 'company_code',
                'type' => OrderField::TEXT,
                'parent' => 'for_company',
                'label' => ['en' => 'Company registration code', 'ru' => 'Код компании', 'uk' => 'Код компанії'],
                'hint' => ['en' => 'For the invoice.', 'ru' => 'Для счёта.', 'uk' => 'Для рахунку.'],
            ],
            [
                'key' => 'installation',
                'type' => OrderField::CHECKBOX,
                'place' => FieldPlaces::BEFORE_COMMENT,
                'label' => ['en' => 'I need installation and setup', 'ru' => 'Нужен монтаж и настройка', 'uk' => 'Потрібен монтаж і налаштування'],
                'hint' => [
                    'en' => 'A specialist will call to agree on the day; the work is paid separately.',
                    'ru' => 'Специалист позвонит, чтобы договориться о дне; работа оплачивается отдельно.',
                    'uk' => 'Фахівець зателефонує, щоб домовитися про день; робота оплачується окремо.',
                ],
            ],
            [
                'key' => 'delivery_time',
                'type' => OrderField::SELECT,
                'place' => FieldPlaces::BEFORE_COMMENT,
                'label' => ['en' => 'Convenient delivery time', 'ru' => 'Удобное время доставки', 'uk' => 'Зручний час доставки'],
                'options' => [
                    ['value' => 'morning', 'label' => ['en' => 'Morning, 9–12', 'ru' => 'Утро, 9–12', 'uk' => 'Ранок, 9–12']],
                    ['value' => 'afternoon', 'label' => ['en' => 'Afternoon, 12–17', 'ru' => 'День, 12–17', 'uk' => 'День, 12–17']],
                    ['value' => 'evening', 'label' => ['en' => 'Evening, 17–21', 'ru' => 'Вечер, 17–21', 'uk' => 'Вечір, 17–21']],
                ],
            ],
            [
                'key' => 'no_callback',
                'type' => OrderField::CHECKBOX,
                'place' => FieldPlaces::AFTER_COMMENT,
                'label' => [
                    'en' => 'Do not call back if everything is in stock — just send it',
                    'ru' => 'Не перезванивайте, если всё в наличии — сразу отправляйте',
                    'uk' => 'Не передзвонюйте, якщо все в наявності — одразу відправляйте',
                ],
            ],
        ];
    }
}
