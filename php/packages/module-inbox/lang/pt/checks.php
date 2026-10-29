<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Formulários que não avisam ninguém',
            'found' => 'Um formulário ativo não indica nenhum destinatário que um e-mail alcançaria: nenhum, só administradores excluídos ou desativados desde então, ou endereços que não são endereços.',
            'why' => 'Cada envio é guardado e o visitante recebe o agradecimento, mas ninguém fica sabendo até alguém abrir a caixa de entrada — um pedido pode esperar dias.',
            'fix' => 'Abra o formulário, vá à aba Avisos e adicione um administrador ou um endereço. Se o formulário é lido só no painel, ignore este aviso.',
        ],
        'notification' => [
            'title' => 'Notificações de envios que não saíram',
            'found' => 'Cartas sobre envios falharam ou aguardam há muito tempo na fila.',
            'why' => 'As pessoas indicadas no formulário não sabem de pedidos que o painel já tem, e o site não o diz.',
            'fix' => 'Falharam: corrija as configurações de e-mail, reinicie o worker da fila (php artisan queue:restart) para que as leia e envie a notificação novamente a partir do envio. Aguardando: inicie um worker da fila.',
        ],
        'captcha_keys' => [
            'title' => 'Formulários que pedem um captcha sem chaves no site',
            'found' => 'Um formulário ativo pede reCAPTCHA ou Turnstile, e falta no .env do site a chave do site, o segredo ou ambos.',
            'why' => 'Sem chave do site o widget não aparece; sem segredo nenhuma resposta pode ser verificada. Em ambos os casos o formulário recusa cada envio e os visitantes não conseguem contactá-lo.',
            'fix' => 'Acrescente WEBX_CAPTCHA_RECAPTCHA_KEY (WEBX_INBOX_RECAPTCHA_KEY) e WEBX_CAPTCHA_RECAPTCHA_SECRET (WEBX_INBOX_RECAPTCHA_SECRET) (ou o par TURNSTILE) ao .env do site, com WEBX_CAPTCHA_RECAPTCHA_TYPE (WEBX_INBOX_RECAPTCHA_TYPE) conforme o tipo de chave, e limpe a cache de configuração (php artisan config:clear). Ou desligue o captcha no separador Antispam do formulário.',
        ],
        'captcha_provider_mismatch' => [
            'title' => 'Um formulário com um captcha diferente do site',
            'found' => 'Um formulário ligado mantém o seu próprio fornecedor — reCAPTCHA ou Turnstile — e o captcha do site é o outro.',
            'why' => 'Continua a funcionar com o seu fornecedor e as chaves do ambiente do site, mas o site tem um captcha para todos os formulários: um segundo par de chaves para manter e um formulário que deixa de funcionar quando forem retiradas.',
            'fix' => 'Ponha o captcha do formulário em «ligado» na aba Antispam para que use o do site, ou escolha o fornecedor do formulário na aba Captcha das definições.',
        ],
        'captcha_unused' => [
            'title' => 'Formulários sem captcha num site que tem as chaves',
            'found' => 'Um formulário ativo não pede captcha, embora o site tenha chaves para reCAPTCHA ou Turnstile.',
            'why' => 'O campo oculto, a marca temporal e o limite por endereço travam a maioria dos robôs, por isso é só uma nota: o captcha está pronto para o dia em que o formulário receber spam.',
            'fix' => 'Se o formulário recebe spam, ligue o captcha no separador Antispam. Caso contrário, ignore este problema.',
        ],
        'spam_without_captcha' => [
            'title' => 'Formulários sem captcha que recebem spam',
            'found' => 'Um formulário ativo sem captcha recebeu nos últimos dias envios marcados como spam, ou o antispam recusou envios para ele.',
            'why' => 'Os robôs encontraram o formulário. O que passa chega à caixa e às notificações, e as camadas gratuitas são precisamente o que tentam contornar.',
            'fix' => 'Ligue um captcha no separador Antispam do formulário — o site precisa das suas chaves no .env — e mantenha o campo oculto. As recusas contam-se por dia na cache, por isso uma cache limpa recomeça do zero.',
        ],
        'legacy_views' => [
            'title' => 'Uma vista de formulário anterior ao motor de formulários',
            'found' => 'O site substitui o formulário de pedidos pelo seu próprio form.blade.php, escrito antes de os formulários passarem para o motor dos widgets (<x-webx-form> e <x-webx-field>).',
            'why' => 'Por enquanto funciona: o módulo entrega-lhe o que entregava e serve o script antigo. Uma versão posterior retira isso, e os formulários dessa vista deixam de ser enviados sem recarregar e perdem o captcha.',
            'fix' => 'Apague a cópia do site para obter o formulário do pacote, ou reescreva-a em torno de <x-webx-form> e <x-webx-field>; altere o estilo dos campos com webx-field-* e webx-form-* em vez de wx-form-* (a tabela de classes está no guia de pedidos).',
        ],
    ],
];
