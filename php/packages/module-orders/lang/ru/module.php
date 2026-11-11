<?php

declare(strict_types=1);

return [
    'orders' => 'Заказы',
    'description' => 'Заказы, оформленные на сайте: что купили, кто и на каком этапе каждый из них.',
    'empty' => 'Заказов пока нет.',
    'empty-help' => 'Заказ появится здесь, как только его оформят на сайте.',
    'order' => 'Заказ',
    'statuses-cache' => 'Статусы заказов',
    'order-field' => [
        'title' => 'Поле оформления',
        'cache' => 'Поля оформления',
        'key' => 'Ключ',
        'type' => 'Тип',
        'place' => 'Место',
        'label' => 'Название',
        'hint' => 'Подсказка',
        'options' => 'Варианты',
        'required' => 'Обязательное',
        'active' => 'Включено',
        'parent_id' => 'Показывать, если отмечено',
    ],
    'fields' => [
        'customer_first_name' => 'Имя',
        'customer_last_name' => 'Фамилия',
        'customer_phone' => 'Телефон',
        'customer_email' => 'E-mail',
        'recipient' => 'Получатель',
        'comment' => 'Комментарий покупателя',
        'manager_note' => 'Заметка менеджера',
        'user_id' => 'Посетитель',
        'fields' => 'Дополнительные поля',
    ],
    'milestones' => [
        'placed' => 'Оформлен',
        'awaiting_payment' => 'Ожидает оплаты',
        'paid' => 'Оплачен',
        'received' => 'Получен',
        'cancelled' => 'Отменён',
        'refunded' => 'Возвращён',
    ],
    'colors' => [
        'neutral' => 'Нейтральный',
        'info' => 'Информация',
        'success' => 'Успех',
        'warning' => 'Внимание',
        'danger' => 'Опасность',
        'accent' => 'Акцент',
    ],
];
