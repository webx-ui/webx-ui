<?php

declare(strict_types=1);

return [
    'config' => [
        'debug' => [
            'title' => 'Modo de depuración en un dominio en producción',
            'found' => 'APP_DEBUG=true en un dominio que no es un entorno de desarrollo.',
            'why' => 'Cada página de error muestra el código, las consultas y el entorno, contraseñas incluidas, a cualquiera que encuentre una.',
            'fix' => 'Establece APP_DEBUG=false en .env y ejecuta php artisan config:cache.',
        ],
        'env' => [
            'title' => 'El entorno no es production',
            'found' => 'APP_ENV no es production en un dominio en producción.',
            'why' => 'Fuera de production los paquetes se comportan de otra manera: cachés, páginas de error, correo y herramientas de depuración.',
            'fix' => 'Establece APP_ENV=production en .env y ejecuta php artisan config:cache.',
        ],
        'app_url' => [
            'title' => 'APP_URL no coincide con el sitio',
            'found' => 'APP_URL difiere del esquema y el host en los que responde el sitio.',
            'why' => 'Todas las direcciones absolutas que imprime el sitio —el mapa del sitio, los enlaces canónicos, los correos, los enlaces a archivos— apuntan a otro lugar.',
            'fix' => 'Establece APP_URL con la dirección que usan los visitantes, con https si el sitio lo tiene, y ejecuta php artisan config:cache.',
        ],
        'queue' => [
            'title' => 'La cola se ejecuta dentro de la petición',
            'found' => 'El controlador de la cola es sync.',
            'why' => 'Los correos y los envíos se procesan mientras el visitante espera, un servidor de correo lento ralentiza los formularios y los trabajos largos, como la auditoría, no pueden ejecutarse desde el panel.',
            'fix' => 'Usa la cola database o redis y mantén un worker en marcha (php artisan queue:work bajo un supervisor).',
        ],
        'mail' => [
            'title' => 'El correo no va a ninguna parte',
            'found' => 'El mailer escribe los correos en el registro o en la memoria.',
            'why' => 'Cada formulario dice “enviado” y nadie recibe nunca un correo.',
            'fix' => 'Configura un mailer real (SMTP o una API) en .env: MAIL_MAILER y sus ajustes.',
        ],
        'schedule' => [
            'title' => 'El programador no se ejecuta',
            'found' => 'El programador no se ha ejecutado en más de una hora.',
            'why' => 'Las copias de seguridad, la limpieza del registro y todo lo demás programado se detienen en silencio.',
            'fix' => 'Añade “* * * * * php artisan schedule:run” al crontab del usuario del sitio.',
        ],
        'storage_link' => [
            'title' => 'No hay enlace public/storage',
            'found' => 'public/storage no existe.',
            'why' => 'Cada imagen y archivo subido al sitio responde 404.',
            'fix' => 'Ejecuta php artisan storage:link en el servidor.',
        ],
        'site_gate' => [
            'title' => 'El sitio está cerrado con contraseña',
            'found' => 'La barrera del sitio está activada.',
            'why' => 'Los buscadores no ven nada tras la contraseña: correcto mientras el sitio está en pruebas, incorrecto tras el lanzamiento.',
            'fix' => 'Establece WEBX_SITE_GATE=false cuando el sitio se abra.',
        ],
    ],
    'host' => [
        'mirror' => [
            'title' => 'Responden dos espejos',
            'found' => 'Tanto www como el nombre sin él responden 200.',
            'why' => 'Cada página existe dos veces y los buscadores reparten su peso entre las copias.',
            'fix' => 'Redirige el segundo nombre al principal con una única redirección 301 en el servidor web.',
        ],
        'https' => [
            'title' => 'http no lleva a https en un solo paso',
            'found' => 'http:// responde por sí mismo, lleva a otro sitio o llega a https a través de una cadena.',
            'why' => 'Los visitantes aterrizan en una copia insegura y cada paso extra cuesta tiempo y peso de enlace.',
            'fix' => 'Una redirección 301 de http:// a https:// del host principal, en el servidor web.',
        ],
        'tls' => [
            'title' => 'Problema de certificado',
            'found' => 'El certificado caduca pronto, nombra a otro host o no es de confianza.',
            'why' => 'Los navegadores muestran una advertencia a pantalla completa y la mayoría de los visitantes se va.',
            'fix' => 'Renueva el certificado (comprueba que la renovación automática funciona) y sirve la cadena completa para este host.',
        ],
        'hsts' => [
            'title' => 'Sin HSTS',
            'found' => 'No hay cabecera Strict-Transport-Security.',
            'why' => 'La primera visita aún puede ir por http sin cifrar y ser interceptada.',
            'fix' => 'Añade Strict-Transport-Security: max-age=31536000 en el servidor web cuando https sea estable.',
        ],
        'index_files' => [
            'title' => 'Responden los archivos index',
            'found' => '/index.php u otro archivo index responde 200.',
            'why' => 'La página está disponible en una segunda dirección: un duplicado para los buscadores.',
            'fix' => 'Redirige los archivos index a la dirección sin ellos con una redirección 301.',
        ],
        'slashes' => [
            'title' => 'Las barras dobles no se colapsan',
            'found' => 'Una dirección con // responde 200.',
            'why' => 'Cualquier enlace mal escrito crea otra copia de la página.',
            'fix' => 'Redirige las direcciones con barras repetidas a la colapsada con una redirección 301.',
        ],
        'trailing_slash' => [
            'title' => 'Con y sin barra final',
            'found' => 'La misma página responde con y sin barra final.',
            'why' => 'Dos direcciones para una página reparten su peso.',
            'fix' => 'Elige una forma y redirige la otra con una redirección 301.',
        ],
        'case' => [
            'title' => 'Las mayúsculas no se normalizan',
            'found' => 'Una dirección con mayúsculas responde 200.',
            'why' => 'Un enlace escrito con otras mayúsculas crea un duplicado.',
            'fix' => 'Redirige las direcciones con mayúsculas a la de minúsculas con una redirección 301.',
        ],
        'soft_404' => [
            'title' => 'Las páginas inexistentes no responden 404',
            'found' => 'Una dirección que no puede existir responde 200 o redirige.',
            'why' => 'Los buscadores indexan los errores tipográficos y las páginas eliminadas como páginas reales.',
            'fix' => 'Responde 404 para las direcciones desconocidas; no las redirijas a la página de inicio.',
        ],
        '404_page' => [
            'title' => 'La página 404 no lleva a ninguna parte',
            'found' => 'La página 404 no tiene enlace a la página de inicio.',
            'why' => 'Un visitante que siguió un enlace roto no tiene adónde ir.',
            'fix' => 'Añade a la plantilla 404 un enlace a la página de inicio, al buscador o a las secciones principales.',
        ],
        'compression' => [
            'title' => 'HTML sin compresión',
            'found' => 'Las páginas se envían sin gzip ni brotli.',
            'why' => 'Las páginas pesan varias veces más y abren más despacio, sobre todo en el móvil.',
            'fix' => 'Activa gzip o brotli para text/html en el servidor web.',
        ],
        'security_headers' => [
            'title' => 'Faltan cabeceras de seguridad',
            'found' => 'Faltan algunas de X-Content-Type-Options, Referrer-Policy y la protección contra incrustación en marcos.',
            'why' => 'Cierran ataques sencillos: detección de MIME, filtración de direcciones, clickjacking.',
            'fix' => 'Añade las cabeceras en el servidor web: nosniff, strict-origin-when-cross-origin, SAMEORIGIN.',
        ],
        'server_leak' => [
            'title' => 'El servidor revela sus versiones',
            'found' => 'X-Powered-By, o Server con un número de versión.',
            'why' => 'Un mapa listo para quien busque un fallo conocido en esa versión.',
            'fix' => 'Desactiva expose_php y server_tokens (o sus equivalentes).',
        ],
        'static_cache' => [
            'title' => 'Los archivos estáticos no se almacenan en caché',
            'found' => 'CSS, JS o imágenes sin Cache-Control o con caché de menos de una semana.',
            'why' => 'Cada página los descarga de nuevo.',
            'fix' => 'Da a los archivos estáticos versionados un Cache-Control largo (un año, immutable) en el servidor web.',
        ],
    ],
    'hosts' => [
        'dev_content' => [
            'title' => 'Enlaces a un entorno de desarrollo en el contenido',
            'found' => 'Una dirección de un entorno de desarrollo en un registro: publicado, en borrador o en un campo que la plantilla no muestra.',
            'why' => 'El contenido rellenado en un entorno de desarrollo sale a producción con enlaces e imágenes que apuntan de vuelta a él; los visitantes reciben errores y ese entorno se indexa.',
            'fix' => 'Abre el registro y sustituye la dirección del entorno por la del propio sitio o por un enlace relativo. Enumera los entornos en los ajustes de la auditoría para que se detecten todos.',
        ],
    ],
];
