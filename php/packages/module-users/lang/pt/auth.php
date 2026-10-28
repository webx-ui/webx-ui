<?php

declare(strict_types=1);

return [
    'sign_in' => [
        'title' => 'Iniciar sessão',
        'remember' => 'Lembrar-me',
        'submit' => 'Entrar',
        'no_account' => 'Ainda não tem conta?',
        'register' => 'Registe-se',
    ],
    'register' => [
        'title' => 'Criar conta',
        'submit' => 'Registar',
        'password_hint' => 'Pelo menos :min caracteres.',
        'have_account' => 'Já tem conta?',
        'sign_in' => 'Iniciar sessão',
    ],
    'fields' => [
        'email' => 'E-mail',
        'password' => 'Palavra-passe',
        'first_name' => 'Nome',
        'last_name' => 'Apelido',
        'phone' => 'Telefone',
    ],
    'failed' => 'E-mail ou palavra-passe incorretos.',
    'blocked' => 'Esta conta está bloqueada. Contacte a loja.',
    'taken' => 'Este endereço já está registado — inicie sessão ou recupere a palavra-passe.',
    'throttled' => 'Demasiadas tentativas. Aguarde :seconds s.',
    'forgot' => [
        'link' => 'Esqueceu a senha?',
        'title' => 'Recuperar a senha',
        'intro' => 'Indique o e-mail da sua conta — enviaremos um link para uma nova senha.',
        'submit' => 'Enviar o link',
        'sent' => 'Se existir uma conta com este endereço, enviámos-lhe um link.',
        'back' => 'Voltar ao início de sessão',
    ],
    'reset' => [
        'title' => 'Nova senha',
        'password' => 'Nova senha',
        'confirmation' => 'Repita a senha',
        'submit' => 'Guardar a senha',
        'expired' => 'O link expirou. Peça um novo abaixo.',
    ],
    'verify' => [
        'banner' => 'Confirme o seu e-mail: enviámos uma mensagem para',
        'resend' => 'Enviar de novo',
        'sent_short' => 'Enviado',
        'close' => 'Fechar',
        'sent' => 'Enviámos a mensagem para :email outra vez.',
        'already' => 'O seu e-mail já está confirmado.',
        'throttled' => 'A mensagem já foi enviada. Tente de novo dentro de :minutes min.',
        'sign_in' => 'Entrar',
        'continue' => 'Continuar',
        'confirmed' => [
            'title' => 'E-mail confirmado',
            'text' => 'Obrigado — o seu endereço está confirmado.',
        ],
        'expired' => [
            'title' => 'O link expirou',
            'text' => 'Este link já não funciona. Entre para receber uma nova mensagem.',
        ],
        'unverified' => [
            'title' => 'Confirme o seu e-mail',
            'text' => 'Esta página abre depois de confirmar o e-mail. Enviámos uma mensagem para :email.',
        ],
    ],
    'email' => [
        'new' => 'Novo e-mail',
        'password' => 'Senha atual',
        'wrong_password' => 'A senha está errada.',
        'same' => 'Já entra com este endereço.',
        'taken' => 'Este endereço pertence a outra conta.',
        'sent' => 'Enviámos um link para :email. O endereço muda quando o abrir.',
        'changed' => [
            'title' => 'Endereço alterado',
            'text' => 'Agora entra com :email.',
        ],
    ],
];
