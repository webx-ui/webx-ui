<?php

declare(strict_types=1);

return [
    'marks' => [
        'paid' => 'Оплачен',
        'received' => 'Получен',
        'cancelled' => 'Отменён',
        'refunded' => 'Возвращено :amount',
    ],
    'blocks' => [
        'delivery' => 'Доставка',
        'payment' => 'Оплата',
    ],
    'order' => [
        'title' => 'Заказ :number',
        'placed' => 'Оформлен :date',
        'scale' => 'Где заказ',
        'awaiting_payment' => 'Заказ ждёт оплаты.',
        'items' => 'Состав',
        'subtotal' => 'Товары',
        'total' => 'Итого',
        'tenders' => 'Оплачено балансом и сертификатами',
        'payable' => 'К оплате',
        'contacts' => 'Контакты',
        'customer' => 'Покупатель',
        'recipient' => 'Получатель',
        'comment' => 'Комментарий',
    ],
    'account' => [
        'menu' => 'Мои заказы',
        'title' => 'Мои заказы',
        'empty' => 'Заказов пока нет. Здесь появятся заказы, оформленные после входа.',
        'back' => '← Все заказы',
        'lines' => 'Позиций: :count',
        'pages' => 'Страницы',
        'newer' => '← Новее',
        'older' => 'Старше →',
        'deletion' => 'Заказы в работе (:numbers) будут выполнены, но в кабинете вы их больше не увидите.',
    ],
];
