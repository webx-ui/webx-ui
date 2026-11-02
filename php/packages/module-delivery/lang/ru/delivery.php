<?php

declare(strict_types=1);

return [
    'title' => 'Доставка',
    'cache' => 'Способы доставки и службы',
    'cache_points' => 'Пункты выдачи служб',
    'carriers' => [
        'store' => 'Магазин',
    ],
    'point_kinds' => [
        'branch' => 'Отделение',
        'locker' => 'Почтомат',
        'store' => 'Магазин',
        'pickup' => 'Пункт выдачи',
    ],
    'locality_kinds' => [
        'city' => 'Город',
        'town' => 'Город',
        'township' => 'Посёлок городского типа',
        'village' => 'Село',
        'settlement' => 'Посёлок',
        'other' => 'Населённый пункт',
    ],
    'step' => [
        'country' => 'Страна',
        'choose' => 'Выбрать',
        'methods' => 'Способ доставки',
        'save' => 'Сохранить',
        'choose_street' => 'Выберите улицу из списка',
        'delivery' => 'Доставка',
        'locality' => 'Город или населённый пункт',
        'locality_placeholder' => 'Начните вводить название',
        'where' => 'Куда доставить заказ?',
        'choose_locality' => 'Выберите город',
        'change' => 'Изменить',
        'popular' => 'Популярные города',
        'show_methods' => 'Показать способы доставки',
        'no_methods' => 'Сюда пока нет способов доставки',
        'free' => 'Бесплатно',
        'carrier_rates' => 'По тарифам перевозчика',
        'choose_point' => 'Выберите отделение',
        'pick_up_here' => 'Заберу здесь',
        'open_until' => 'Сегодня до :time',
        'closed_today' => 'Сегодня закрыто',
        'street' => 'Улица',
        'house' => 'Дом',
        'apartment' => 'Квартира',
        'note' => 'Комментарий курьеру',
        'cost' => 'Стоимость доставки',
        'points' => 'Точек: :count',
    ],
    // The line of the cart's totals, and the benefit the shop's cart rules may grant (§9.4).
    'totals' => [
        'delivery' => 'Доставка',
    ],
    'benefits' => [
        'paid_by_store' => 'Доставку оплачивает магазин',
    ],
    'hints' => [
        'no_streets' => 'Курьер сюда не доставляет, выберите другой способ',
    ],
    // A street of a carrier's directory as shown: its name with the type word where the language puts it.
    'street_kinds' => [
        'street' => 'ул. :name',
        'avenue' => 'просп. :name',
        'lane' => 'пер. :name',
        'square' => 'пл. :name',
        'other' => ':name',
    ],
    'errors' => [
        'choose_country' => 'Выберите страну доставки.',
        'choose_locality' => 'Выберите населённый пункт из списка.',
        'choose_method' => 'Выберите способ доставки.',
        'method_gone' => 'Этот способ доставки здесь больше недоступен, выберите другой.',
        'choose_point' => 'Выберите пункт выдачи.',
        'point_gone' => 'Пункт выдачи больше не работает, выберите другой.',
        'choose_street' => 'Выберите улицу из списка.',
        'street_required' => 'Укажите улицу.',
        'house_required' => 'Укажите номер дома.',
        'too_long' => 'Не больше :max символов.',
        'no_quote' => 'Не удалось рассчитать доставку, выберите другой способ.',
    ],
    'sync' => [
        'running' => 'Уже идёт, страница :page',
        'errors' => [
            'no_answer' => 'Служба не ответила',
            'key_rejected' => 'Ключ API отклонён',
            'bad_response' => 'Ответ не того формата',
            'failed' => 'Синхронизация упала на стороне сайта — подробности в логе',
            'interrupted' => 'Синхронизация прервалась',
        ],
    ],
    'schedule' => [
        'closed' => 'Закрыто',
        'around_the_clock' => 'Круглосуточно',
        'until' => 'До :time',
        'from' => 'Откроется в :time',
    ],
];
