<?php

declare(strict_types=1);

return [
    'title' => 'Доставка',
    'cache' => 'Способи доставки та служби',
    'cache_points' => 'Пункти видачі служб',
    'carriers' => [
        'store' => 'Магазин',
    ],
    'point_kinds' => [
        'branch' => 'Відділення',
        'locker' => 'Поштомат',
        'store' => 'Магазин',
        'pickup' => 'Пункт видачі',
    ],
    'locality_kinds' => [
        'city' => 'Місто',
        'town' => 'Місто',
        'township' => 'Селище міського типу',
        'village' => 'Село',
        'settlement' => 'Селище',
        'other' => 'Населений пункт',
    ],
    'step' => [
        'delivery' => 'Доставка',
        'locality' => 'Місто або населений пункт',
        'locality_placeholder' => 'Почніть вводити назву',
        'where' => 'Куди доставити замовлення?',
        'choose_locality' => 'Виберіть місто',
        'change' => 'Змінити',
        'popular' => 'Популярні міста',
        'show_methods' => 'Показати способи доставки',
        'no_methods' => 'Сюди поки немає способів доставки',
        'free' => 'Безкоштовно',
        'carrier_rates' => 'За тарифами перевізника',
        'choose_point' => 'Виберіть відділення',
        'pick_up_here' => 'Заберу тут',
        'open_until' => 'Сьогодні до :time',
        'closed_today' => 'Сьогодні зачинено',
        'street' => 'Вулиця',
        'house' => 'Будинок',
        'apartment' => 'Квартира',
        'note' => 'Коментар кур’єру',
        'cost' => 'Вартість доставки',
        'points' => 'Точок: :count',
    ],
    'hints' => [
        'no_streets' => 'Кур’єр сюди не доставляє, виберіть інший спосіб',
    ],
    // A street of a carrier's directory as shown: its name with the type word where the language puts it.
    'street_kinds' => [
        'street' => 'вул. :name',
        'avenue' => 'просп. :name',
        'lane' => 'пров. :name',
        'square' => 'пл. :name',
        'other' => ':name',
    ],
    'errors' => [
        'choose_point' => 'Виберіть пункт видачі.',
        'point_gone' => 'Пункт видачі більше не працює, виберіть інший.',
        'choose_street' => 'Виберіть вулицю зі списку.',
        'street_required' => 'Вкажіть вулицю.',
        'house_required' => 'Вкажіть номер будинку.',
        'too_long' => 'Не більше :max символів.',
        'no_quote' => 'Не вдалося розрахувати доставку, виберіть інший спосіб.',
    ],
    'sync' => [
        'running' => 'Уже триває, сторінка :page',
        'errors' => [
            'no_answer' => 'Служба не відповіла',
            'key_rejected' => 'Ключ API відхилено',
            'bad_response' => 'Відповідь не того формату',
            'failed' => 'Синхронізація впала на боці сайту — подробиці в журналі',
            'interrupted' => 'Синхронізацію перервано',
        ],
    ],
    'schedule' => [
        'closed' => 'Зачинено',
        'around_the_clock' => 'Цілодобово',
        'until' => 'До :time',
        'from' => 'Відкриється о :time',
    ],
];
