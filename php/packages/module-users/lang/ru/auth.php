<?php

declare(strict_types=1);

return [
    'sign_in' => [
        'title' => 'Вход',
        'remember' => 'Запомнить меня',
        'submit' => 'Войти',
        'no_account' => 'Ещё нет аккаунта?',
        'register' => 'Регистрация',
    ],
    'register' => [
        'title' => 'Регистрация',
        'submit' => 'Зарегистрироваться',
        'password_hint' => 'Не короче :min символов.',
        'have_account' => 'Уже есть аккаунт?',
        'sign_in' => 'Войти',
    ],
    'fields' => [
        'email' => 'E-mail',
        'password' => 'Пароль',
        'first_name' => 'Имя',
        'last_name' => 'Фамилия',
        'phone' => 'Телефон',
    ],
    'failed' => 'Неверный e-mail или пароль.',
    'blocked' => 'Аккаунт заблокирован, свяжитесь с магазином.',
    'taken' => 'Адрес уже зарегистрирован — войдите или восстановите пароль.',
    'throttled' => 'Слишком много попыток, подождите :seconds с.',
];
