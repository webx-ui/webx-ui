<?php

declare(strict_types=1);

return [
    'menu' => [
        'label' => 'Minha conta',
        'profile' => 'Perfil',
        'security' => 'Acesso e segurança',
        'sign_out' => 'Sair',
    ],
    'profile' => [
        'title' => 'Perfil',
        'personal' => 'Dados pessoais',
        'save' => 'Salvar',
        'saved' => 'Salvo.',
    ],
    'email' => [
        'title' => 'E-mail',
        'confirmed' => 'confirmado',
        'unconfirmed' => 'não confirmado',
        'pending' => 'Aguardando confirmação: :email — siga o link do e-mail.',
        'submit' => 'Alterar o e-mail',
    ],
    'security' => [
        'title' => 'Acesso e segurança',
    ],
    'password' => [
        'title' => 'Senha',
        'current' => 'Senha atual',
        'new' => 'Nova senha',
        'confirmation' => 'Repita a nova senha',
        'submit' => 'Alterar a senha',
        'changed' => 'A senha foi alterada. Os outros dispositivos foram desconectados.',
        'none' => 'Sua conta ainda não tem senha. Enviaremos um link para defini-la.',
        'set' => 'Definir uma senha',
        'link_sent' => 'Enviamos um link para :email.',
    ],
    'sessions' => [
        'title' => 'Sair de todos os lugares',
        'text' => 'Encerra as sessões em todos os outros dispositivos e navegadores. Aqui você continua conectado.',
        'submit' => 'Sair de todos os lugares',
        'done' => 'Você saiu de todos os outros dispositivos.',
    ],
    'delete' => [
        'title' => 'Excluir a conta',
        'text' => 'A conta e tudo o que ela guarda são excluídos para sempre. Não é possível restaurá-la.',
        'password' => 'Senha atual',
        'email' => 'Seu e-mail',
        'email_hint' => 'Digite o endereço da conta para confirmar.',
        'wrong_email' => 'Este não é o endereço da conta.',
        'submit' => 'Excluir para sempre',
    ],
    'social' => [
        'title' => 'Redes sociais',
        'link' => 'Associar',
        'unlink' => 'Desassociar',
        'linked_short' => 'Associada',
        'since' => 'desde :date',
        'linked' => 'O acesso com :provider está associado.',
        'elsewhere' => 'Esta conta do :provider já está associada a outro perfil.',
        'other' => 'Já está associada outra conta do :provider. Desassocie-a primeiro.',
        'last' => 'Defina uma palavra-passe para desassociar a última forma de entrar.',
        'not_linked' => 'Esta rede não está associada.',
    ],
];
