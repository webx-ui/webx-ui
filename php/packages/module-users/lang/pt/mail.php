<?php

declare(strict_types=1);

return [
    'hello' => 'Olá, :name!',
    'ignore' => 'Se não foi você que pediu, ignore esta mensagem.',
    'verify' => [
        'subject' => 'Confirme o seu e-mail em :site',
        'text' => 'Confirme que este é o seu endereço em :site.',
        'button' => 'Confirmar o e-mail',
        'expires' => 'O link é válido durante :hours horas.',
    ],
    'reset' => [
        'subject' => 'Uma nova senha em :site',
        'text' => 'Alguém pediu para recuperar a senha da sua conta em :site.',
        'button' => 'Definir uma nova senha',
        'expires' => 'O link é válido durante :minutes minutos e apenas uma vez.',
    ],
    'invite' => [
        'subject' => 'A sua conta em :site',
        'text' => 'Foi criada uma conta para si em :site: acede com :email. Defina uma senha para começar.',
        'button' => 'Definir uma senha',
        'expires' => 'O link é válido durante :days dias e apenas uma vez.',
    ],
    'password-changed' => [
        'subject' => 'A sua senha em :site foi alterada',
        'text' => 'A senha da sua conta em :site acabou de ser alterada.',
        'not-you' => 'Se não foi você, recupere a senha de imediato.',
        'button' => 'Recuperar a senha',
    ],
    'email-change' => [
        'subject' => 'Confirme o novo endereço em :site',
        'text' => 'Abra o link para passar a entrar em :site com :email.',
        'button' => 'Confirmar o endereço',
        'expires' => 'O link é válido durante :minutes minutos.',
    ],
    'email-changed' => [
        'subject' => 'O seu endereço de acesso em :site foi alterado',
        'text' => 'A sua conta em :site agora entra com :email.',
        'not-you' => 'Se não foi você, recupere a senha e contacte-nos.',
        'button' => 'Recuperar a senha',
    ],
    'account-deleted' => [
        'subject' => 'Sua conta em :site foi excluída',
        'text' => 'Sua conta em :site foi excluída junto com seus dados.',
        'not-you' => 'Se não foi você, entre em contato conosco imediatamente.',
    ],
    'provider-linked' => [
        'subject' => 'Acesso com :provider associado em :site',
        'text' => 'Agora pode entrar na sua conta em :site com :provider.',
        'not-you' => 'Se não foi você, desassocie-o na conta e redefina a palavra-passe.',
        'button' => 'Acesso e segurança',
    ],
];
