<?php

declare(strict_types=1);

return [
    'config' => [
        'debug' => [
            'title' => 'Modo de depuração num domínio em produção',
            'found' => 'APP_DEBUG=true num domínio que não é um ambiente de desenvolvimento.',
            'why' => 'Cada página de erro mostra o código, as consultas e o ambiente, palavras-passe incluídas, a quem quer que encontre uma.',
            'fix' => 'Defina APP_DEBUG=false em .env e execute php artisan config:cache.',
        ],
        'env' => [
            'title' => 'O ambiente não é production',
            'found' => 'APP_ENV não é production num domínio em produção.',
            'why' => 'Fora de production os pacotes comportam-se de forma diferente: caches, páginas de erro, correio e ferramentas de depuração.',
            'fix' => 'Defina APP_ENV=production em .env e execute php artisan config:cache.',
        ],
        'app_url' => [
            'title' => 'APP_URL não corresponde ao site',
            'found' => 'APP_URL difere do esquema e do host em que o site responde.',
            'why' => 'Todos os endereços absolutos que o site imprime — o sitemap, os links canónicos, os e-mails, os links de ficheiros — apontam para outro lado.',
            'fix' => 'Defina APP_URL com o endereço que os visitantes usam, com https se o site o tiver, e execute php artisan config:cache.',
        ],
        'queue' => [
            'title' => 'A fila executa-se dentro do pedido',
            'found' => 'O driver da fila é sync.',
            'why' => 'Os e-mails e os envios são tratados enquanto o visitante espera, um servidor de correio lento torna os formulários lentos, e as tarefas longas, como a auditoria, não podem ser executadas a partir do painel.',
            'fix' => 'Use a fila database ou redis e mantenha um worker em execução (php artisan queue:work sob um supervisor).',
        ],
        'mail' => [
            'title' => 'O correio não vai a lado nenhum',
            'found' => 'O mailer escreve os e-mails no registo ou na memória.',
            'why' => 'Cada formulário diz “enviado” e ninguém recebe nunca um e-mail.',
            'fix' => 'Configure um mailer real (SMTP ou uma API) em .env: MAIL_MAILER e as suas definições.',
        ],
        'schedule' => [
            'title' => 'O agendador não está a ser executado',
            'found' => 'O agendador não é executado há mais de uma hora.',
            'why' => 'As cópias de segurança, a limpeza do registo e tudo o resto agendado param em silêncio.',
            'fix' => 'Adicione “* * * * * php artisan schedule:run” ao crontab do utilizador do site.',
        ],
        'storage_link' => [
            'title' => 'Sem link public/storage',
            'found' => 'public/storage não existe.',
            'why' => 'Cada imagem e ficheiro carregado no site responde 404.',
            'fix' => 'Execute php artisan storage:link no servidor.',
        ],
        'site_gate' => [
            'title' => 'O site está fechado com palavra-passe',
            'found' => 'A barreira do site está ativa.',
            'why' => 'Os motores de busca não veem nada atrás da palavra-passe — certo enquanto o site está em testes, errado depois do lançamento.',
            'fix' => 'Defina WEBX_SITE_GATE=false quando o site abrir.',
        ],
    ],
    'host' => [
        'mirror' => [
            'title' => 'Respondem dois espelhos',
            'found' => 'Tanto o www como o nome sem ele respondem 200.',
            'why' => 'Cada página existe duas vezes e os motores de busca dividem o seu peso entre as cópias.',
            'fix' => 'Redirecione o segundo nome para o principal com um único 301 no servidor web.',
        ],
        'https' => [
            'title' => 'http não leva a https num só passo',
            'found' => 'http:// responde por si, leva a outro lado ou chega a https através de uma cadeia.',
            'why' => 'Os visitantes aterram numa cópia insegura e cada passo extra custa tempo e peso de links.',
            'fix' => 'Um único 301 de http:// para https:// do host principal, no servidor web.',
        ],
        'tls' => [
            'title' => 'Problema de certificado',
            'found' => 'O certificado expira em breve, nomeia outro host ou não é de confiança.',
            'why' => 'Os navegadores mostram um aviso em ecrã inteiro e a maioria dos visitantes sai.',
            'fix' => 'Renove o certificado (verifique que a renovação automática funciona) e sirva a cadeia completa para este host.',
        ],
        'hsts' => [
            'title' => 'Sem HSTS',
            'found' => 'Sem cabeçalho Strict-Transport-Security.',
            'why' => 'A primeira visita ainda pode ir por http simples e ser intercetada.',
            'fix' => 'Adicione Strict-Transport-Security: max-age=31536000 no servidor web quando o https estiver estável.',
        ],
        'index_files' => [
            'title' => 'Os ficheiros index respondem',
            'found' => '/index.php ou outro ficheiro index responde 200.',
            'why' => 'A página está disponível num segundo endereço — um duplicado para os motores de busca.',
            'fix' => 'Redirecione os ficheiros index para o endereço sem eles com um 301.',
        ],
        'slashes' => [
            'title' => 'As barras duplas não são reduzidas',
            'found' => 'Um endereço com // responde 200.',
            'why' => 'Qualquer link mal escrito cria outra cópia da página.',
            'fix' => 'Redirecione os endereços com barras repetidas para o reduzido com um 301.',
        ],
        'trailing_slash' => [
            'title' => 'Com e sem barra final',
            'found' => 'A mesma página responde com e sem barra final.',
            'why' => 'Dois endereços para uma página dividem o seu peso.',
            'fix' => 'Escolha uma forma e redirecione a outra com um 301.',
        ],
        'case' => [
            'title' => 'As maiúsculas não são normalizadas',
            'found' => 'Um endereço com letras maiúsculas responde 200.',
            'why' => 'Um link escrito com outras maiúsculas cria um duplicado.',
            'fix' => 'Redirecione os endereços com maiúsculas para o de minúsculas com um 301.',
        ],
        'soft_404' => [
            'title' => 'As páginas inexistentes não respondem 404',
            'found' => 'Um endereço que não pode existir responde 200 ou redireciona.',
            'why' => 'Os motores de busca indexam erros de escrita e páginas eliminadas como páginas reais.',
            'fix' => 'Responda 404 para endereços desconhecidos; não os redirecione para a página inicial.',
        ],
        '404_page' => [
            'title' => 'A página 404 não leva a lado nenhum',
            'found' => 'A página 404 não tem link para a página inicial.',
            'why' => 'Um visitante que seguiu um link quebrado não tem para onde ir.',
            'fix' => 'Adicione ao modelo 404 um link para a página inicial, a pesquisa ou as secções principais.',
        ],
        'compression' => [
            'title' => 'HTML sem compressão',
            'found' => 'As páginas são enviadas sem gzip ou brotli.',
            'why' => 'As páginas pesam várias vezes mais e abrem mais devagar, sobretudo no telemóvel.',
            'fix' => 'Ative gzip ou brotli para text/html no servidor web.',
        ],
        'security_headers' => [
            'title' => 'Faltam cabeçalhos de segurança',
            'found' => 'Faltam alguns de X-Content-Type-Options, Referrer-Policy e a proteção contra incorporação em frames.',
            'why' => 'Fecham ataques fáceis: MIME sniffing, fuga de endereços, clickjacking.',
            'fix' => 'Adicione os cabeçalhos no servidor web: nosniff, strict-origin-when-cross-origin, SAMEORIGIN.',
        ],
        'server_leak' => [
            'title' => 'O servidor revela as suas versões',
            'found' => 'X-Powered-By, ou Server com um número de versão.',
            'why' => 'Um mapa pronto para quem procura uma falha conhecida nessa versão.',
            'fix' => 'Desative expose_php e server_tokens (ou os seus equivalentes).',
        ],
        'static_cache' => [
            'title' => 'Os ficheiros estáticos não estão em cache',
            'found' => 'CSS, JS ou imagens sem Cache-Control ou com cache inferior a uma semana.',
            'why' => 'Cada página volta a descarregá-los.',
            'fix' => 'Dê aos ficheiros estáticos versionados um Cache-Control longo (um ano, immutable) no servidor web.',
        ],
    ],
    'hosts' => [
        'dev_content' => [
            'title' => 'Links para um ambiente de desenvolvimento no conteúdo',
            'found' => 'Um endereço de um ambiente de desenvolvimento num registo — publicado, em rascunho ou num campo que o modelo não imprime.',
            'why' => 'O conteúdo preenchido num ambiente de desenvolvimento entra em produção com links e imagens que apontam de volta para esse ambiente; os visitantes recebem erros e o ambiente é indexado.',
            'fix' => 'Abra o registo e substitua o endereço do ambiente pelo do próprio site ou por um link relativo. Liste os ambientes nas definições da auditoria para que sejam todos detetados.',
        ],
    ],
];
