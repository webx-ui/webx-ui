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
        'dev_page' => [
            'title' => 'Enlaces a un entorno de desarrollo en la página',
            'found' => 'Un enlace o un recurso de la página (una imagen, un script, un estilo, og:image, el canonical) lleva a un entorno de desarrollo.',
            'why' => 'Los visitantes siguen enlaces a un sitio que no es para ellos, las imágenes se rompen cuando el entorno se apaga y los buscadores encuentran el entorno a través del sitio.',
            'fix' => 'Busca la dirección en el contenido o en la plantilla de la página y sustituye el entorno por el host del propio sitio o por un enlace relativo.',
        ],
        'similar' => [
            'title' => 'Un host que se parece a este sitio',
            'found' => 'Un enlace a un host con la misma primera palabra que el sitio en otra zona, como shop.local junto a shop.com.',
            'why' => 'Lo más probable es que sea un entorno de desarrollo o una copia antigua del sitio que la auditoría no conoce.',
            'fix' => 'Si es un entorno o un dominio antiguo, añádelo a «Otras direcciones de este sitio» en los ajustes de la auditoría y corrige los enlaces; si es el sitio de otra persona, no hay que hacer nada.',
        ],
        'wrong_mirror' => [
            'title' => 'Enlaces a través de otro espejo',
            'found' => 'Un enlace al sitio a través de su otro espejo (www o el nombre sin él) o por http en un sitio https.',
            'why' => 'Cada clic pasa por una redirección: más lento para los visitantes, y los buscadores ven enlaces a una dirección que no es la página.',
            'fix' => 'Enlaza al espejo principal por https o usa enlaces relativos.',
        ],
        'absolute_own' => [
            'title' => 'Enlaces absolutos al propio sitio',
            'found' => 'Un enlace o una imagen del contenido está escrito con el host del propio sitio en lugar de una ruta.',
            'why' => 'Hoy funciona y se rompe en el próximo cambio de dominio o de protocolo; y copiado a un entorno de desarrollo, lleva de vuelta al sitio real.',
            'fix' => 'Escribe los enlaces a las páginas del propio sitio como rutas: /about en lugar de https://shop.com/about.',
        ],
        'new_domain' => [
            'title' => 'Un dominio externo nuevo',
            'found' => 'El sitio enlaza a un dominio al que no enlazaba en la ejecución completa anterior.',
            'why' => 'Un dominio nuevo suele ser un enlace nuevo que alguien añadió, y a veces una errata o enlaces de spam dejados por alguien que entró en el sitio.',
            'fix' => 'Abre las páginas de la lista y comprueba que el enlace debe estar ahí.',
        ],
        'external_many' => [
            'title' => 'Muchos enlaces externos en una página',
            'found' => 'La página tiene más enlaces externos que el umbral.',
            'why' => 'Una página formada sobre todo por enlaces a otros sitios parece una granja de enlaces para los buscadores y suele ser señal de spam.',
            'fix' => 'Quita los enlaces que no ayudan al visitante o divide la página.',
        ],
        'blank_opener' => [
            'title' => 'Pestaña nueva sin noopener',
            'found' => 'Un enlace a otro sitio se abre en una pestaña nueva sin rel="noopener".',
            'why' => 'En navegadores antiguos, la página abierta puede redirigir la pestaña del sitio a una página de su elección.',
            'fix' => 'Añade rel="noopener" (o noreferrer) a los enlaces con target="_blank".',
        ],
    ],
    'indexing' => [
        'home_noindex' => [
            'title' => 'La página de inicio está cerrada a los buscadores',
            'found' => 'La página de inicio tiene noindex en la metaetiqueta robots o en la cabecera X-Robots-Tag.',
            'why' => 'La página más importante del sitio desaparece de la búsqueda, y a menudo le sigue todo el sitio.',
            'fix' => 'Quita noindex de la página de inicio: revisa los ajustes SEO de la página, la plantilla base y las cabeceras del servidor web.',
        ],
        'noindex' => [
            'title' => 'Páginas cerradas con noindex',
            'found' => 'La página tiene noindex en la metaetiqueta robots o en la cabecera X-Robots-Tag.',
            'why' => 'Los buscadores descartan la página. Correcto para resultados de búsqueda y páginas de servicio, incorrecto para contenido cerrado por error.',
            'fix' => 'Revisa la lista; abre las páginas que deberían encontrarse en sus ajustes SEO y quita noindex.',
        ],
    ],
    'title' => [
        'missing' => [
            'title' => 'Sin title',
            'found' => 'La página no tiene <title>, o está vacío.',
            'why' => 'El title es la línea que los buscadores muestran como enlace a la página; sin él, se inventan una.',
            'fix' => 'Da a la página un title en sus ajustes SEO o comprueba que la plantilla base lo imprime.',
        ],
        'duplicate' => [
            'title' => 'Títulos duplicados',
            'found' => 'Varias páginas indexables tienen el mismo title.',
            'why' => 'Los buscadores no distinguen las páginas y muestran una de ellas, no necesariamente la correcta.',
            'fix' => 'Da a cada página un title propio que diga qué hay en ella.',
        ],
        'length' => [
            'title' => 'Title demasiado corto o demasiado largo',
            'found' => 'El title es más corto o más largo que los umbrales, en caracteres.',
            'why' => 'Un title largo se corta en los resultados de búsqueda (el límite es de unos 600 píxeles, unos 60 caracteres); uno corto dice muy poco.',
            'fix' => 'Reescribe el title para que entre en el rango de los umbrales de la auditoría.',
        ],
        'multiple' => [
            'title' => 'Más de un title',
            'found' => 'La página tiene más de una etiqueta <title>.',
            'why' => 'Los buscadores toman uno de ellos, no necesariamente el escrito para la página.',
            'fix' => 'Busca qué plantilla o bloque imprime el segundo title y quítalo.',
        ],
    ],
    'description' => [
        'missing' => [
            'title' => 'Sin meta description',
            'found' => 'La página no tiene meta description, o está vacía.',
            'why' => 'Los buscadores componen el fragmento bajo el enlace con el texto que encuentren.',
            'fix' => 'Escribe una description en los ajustes SEO de la página: qué ofrece la página, en una o dos frases.',
        ],
        'duplicate' => [
            'title' => 'Descriptions duplicadas',
            'found' => 'Varias páginas indexables tienen la misma meta description.',
            'why' => 'El mismo fragmento bajo enlaces distintos no dice nada al usuario, y los buscadores lo sustituyen por uno propio.',
            'fix' => 'Escribe una description propia para cada página.',
        ],
        'length' => [
            'title' => 'Description demasiado corta o demasiado larga',
            'found' => 'La description es más corta o más larga que los umbrales, en caracteres.',
            'why' => 'Una description larga se corta en los resultados de búsqueda (unos 920 píxeles, unos 160 caracteres); una corta suele sustituirse.',
            'fix' => 'Reescribe la description para que entre en el rango de los umbrales de la auditoría.',
        ],
    ],
    'h1' => [
        'missing' => [
            'title' => 'Sin H1',
            'found' => 'La página no tiene encabezado H1.',
            'why' => 'El H1 dice a los visitantes y a los buscadores de qué trata la página; los lectores de pantalla lo usan para encontrar el inicio del contenido.',
            'fix' => 'Da a la página un H1 (normalmente su nombre) en la plantilla o en el contenido.',
        ],
        'multiple' => [
            'title' => 'Más de un H1',
            'found' => 'La página tiene más de un encabezado H1.',
            'why' => 'No es un error en sí, pero suele indicar que un bloque o el logotipo usa H1 donde se pretendía un nivel inferior.',
            'fix' => 'Deja un H1 para el nombre de la página y haz que los demás sean H2 o inferiores.',
        ],
        'equals_title' => [
            'title' => 'H1 igual que el title',
            'found' => 'El H1 repite el title palabra por palabra.',
            'why' => 'Dos lugares para describir la página dicen lo mismo; uno de ellos podría añadir una palabra que la gente busca.',
            'fix' => 'Mantén el H1 corto y legible, y deja que el title lleve las palabras clave y el nombre del sitio.',
        ],
    ],
    'headings' => [
        'skipped' => [
            'title' => 'Nivel de encabezado omitido',
            'found' => 'Se omite un nivel de encabezado al bajar, como un H2 seguido de un H4.',
            'why' => 'Los lectores de pantalla navegan por encabezados, y un salto se lee como contenido que falta.',
            'fix' => 'Usa los niveles en orden; elige el aspecto con estilos, no con el nivel.',
        ],
    ],
    'canonical' => [
        'missing' => [
            'title' => 'Sin canonical',
            'found' => 'Una página indexable no tiene enlace canonical, ni en la etiqueta ni en la cabecera.',
            'why' => 'Sin él, cada copia de la página con parámetros de seguimiento u ordenación puede competir con la propia página.',
            'fix' => 'Haz que la plantilla base imprima <link rel="canonical"> con la dirección propia de la página.',
        ],
        'relative' => [
            'title' => 'Canonical relativo',
            'found' => 'El canonical está escrito como una ruta, no como una dirección completa.',
            'why' => 'Los buscadores lo interpretan según la dirección por la que llegaron, incluido otro espejo u otro protocolo.',
            'fix' => 'Imprime el canonical como una dirección completa con el esquema y el host principal.',
        ],
        'multiple' => [
            'title' => 'Canonicals en conflicto',
            'found' => 'La página tiene más de un canonical, o la etiqueta y la cabecera Link no coinciden.',
            'why' => 'Con canonicals en conflicto, los buscadores los ignoran todos.',
            'fix' => 'Deja un solo canonical: busca la plantilla, el bloque o la regla del servidor que añade el segundo y quítalo.',
        ],
        'broken' => [
            'title' => 'Canonical a una página rota o cerrada',
            'found' => 'El canonical lleva a una redirección, un error o una página con noindex.',
            'why' => 'La página señala un original que no se puede indexar, y los buscadores pueden descartar ambas.',
            'fix' => 'Apunta el canonical a la dirección propia y operativa de la página, o al original activo.',
        ],
        'other' => [
            'title' => 'Canonical a otra página',
            'found' => 'El canonical apunta a una dirección distinta de la propia de la página.',
            'why' => 'La página pide no indexarse en favor de otra: correcto para filtros y copias, incorrecto para una página que debe encontrarse.',
            'fix' => 'Revisa la lista; en las páginas que deben encontrarse, haz que el canonical sea su propia dirección.',
        ],
    ],
    'html' => [
        'lang' => [
            'title' => 'Sin idioma de la página',
            'found' => 'La etiqueta <html> no tiene el atributo lang.',
            'why' => 'Los lectores de pantalla eligen la voz según él, los navegadores ofrecen traducir según él y los buscadores lo usan como indicio.',
            'fix' => 'Imprime <html lang="…"> con el idioma de la página en la plantilla base.',
        ],
        'viewport' => [
            'title' => 'Sin meta viewport',
            'found' => 'La página no tiene <meta name="viewport">.',
            'why' => 'Los móviles dibujan la página con el ancho de escritorio, reducida; los buscadores consideran que esa página no es apta para móviles.',
            'fix' => 'Añade <meta name="viewport" content="width=device-width, initial-scale=1"> a la plantilla base.',
        ],
        'favicon' => [
            'title' => 'Sin icono',
            'found' => 'La página no enlaza ningún icono.',
            'why' => 'Las pestañas del navegador, los marcadores y los resultados de búsqueda en móviles muestran un cuadrado vacío en lugar de la marca del sitio.',
            'fix' => 'Añade <link rel="icon"> a la plantilla base.',
        ],
    ],
    'og' => [
        'missing' => [
            'title' => 'Faltan las etiquetas Open Graph',
            'found' => 'La página no tiene og:title, og:image u og:url.',
            'why' => 'Un enlace compartido en un mensajero o una red social aparece como una dirección sin imagen ni título.',
            'fix' => 'Rellena la vista previa social en los ajustes SEO de la página o haz que la plantilla base imprima las etiquetas.',
        ],
    ],
    'content' => [
        'thin' => [
            'title' => 'Poco texto',
            'found' => 'Una página indexable tiene menos palabras que el umbral.',
            'why' => 'Los buscadores posicionan peor las páginas con poco que leer y pueden considerar de baja calidad muchas páginas así.',
            'fix' => 'Añade texto que ayude al visitante, fusiona las páginas escasas o ciérralas con noindex.',
        ],
        'text_ratio' => [
            'title' => 'Poco texto para el marcado',
            'found' => 'El texto visible es una parte menor del HTML que el umbral.',
            'why' => 'La página pesa mucho para lo que dice: es lenta en el móvil y los buscadores encuentran poco contenido en mucho código.',
            'fix' => 'Mueve los scripts y estilos en línea a archivos, quita el marcado que no se usa y añade contenido.',
        ],
        'duplicate' => [
            'title' => 'Texto duplicado',
            'found' => 'Varias páginas indexables tienen el mismo texto visible.',
            'why' => 'Los buscadores eligen una copia para mostrar e ignoran el resto.',
            'fix' => 'Haz las páginas distintas, fusiónalas o apunta el canonical de las copias al original.',
        ],
    ],
    'url' => [
        'length' => [
            'title' => 'Dirección larga',
            'found' => 'La dirección es más larga que el umbral.',
            'why' => 'Las direcciones largas se cortan en los resultados de búsqueda y son difíciles de compartir y de leer.',
            'fix' => 'Acorta el slug de la página; la redirección desde la dirección antigua se añade automáticamente.',
        ],
        'format' => [
            'title' => 'Formato de la dirección',
            'found' => 'La ruta tiene mayúsculas, guiones bajos o caracteres fuera de ASCII.',
            'why' => 'Las mayúsculas hacen de /About y /about dos páginas, los guiones bajos no separan palabras para los buscadores y otros caracteres se convierten en %D0%B0 al copiarlos.',
            'fix' => 'Usa letras latinas minúsculas, dígitos y guiones en los slugs.',
        ],
        'params' => [
            'title' => 'Parámetros sin canonical',
            'found' => 'Una dirección indexable tiene parámetros de consulta y no tiene canonical.',
            'why' => 'Cada combinación de filtros y ordenación se convierte en una página propia en los buscadores, y reparten el peso de la real.',
            'fix' => 'Imprime un canonical a la dirección sin parámetros o cierra esas direcciones con noindex.',
        ],
    ],
    'perf' => [
        'ttfb' => [
            'title' => 'Respuesta lenta',
            'found' => 'La página tardó en responder más que el umbral.',
            'why' => 'Los visitantes esperan antes de que aparezca nada, y los buscadores rastrean menos un sitio lento.',
            'fix' => 'Activa las cachés (configuración, rutas, vistas, páginas), revisa las consultas lentas y pasa el trabajo pesado a la cola.',
        ],
        'html_size' => [
            'title' => 'HTML pesado',
            'found' => 'El HTML de la página es mayor que el umbral.',
            'why' => 'Los móviles lo descargan y lo procesan despacio; los buscadores pueden dejar de leer antes del final.',
            'fix' => 'Pagina las listas largas, mueve los datos en línea y los SVG a archivos y quita las copias ocultas del contenido.',
        ],
    ],
    'links' => [
        'broken' => [
            'title' => 'Enlaces internos rotos',
            'found' => 'Un enlace a una página del propio sitio responde 4xx, 5xx o nada.',
            'why' => 'Los visitantes acaban en un error, y los buscadores malgastan su visita en él.',
            'fix' => 'Corrige o quita el enlace, o añade una redirección desde la dirección que falta a la página correcta.',
        ],
        'empty' => [
            'title' => 'Enlaces sin texto',
            'found' => 'Un enlace no tiene texto ni aria-label, y un enlace de imagen no tiene alt.',
            'why' => 'Los lectores de pantalla leen la dirección o solo «enlace», y los buscadores no aprenden nada de la página a la que lleva.',
            'fix' => 'Da al enlace un texto, un aria-label, o un alt a su imagen.',
        ],
        'nofollow_internal' => [
            'title' => 'nofollow en enlaces internos',
            'found' => 'Un enlace a una página del propio sitio tiene rel="nofollow".',
            'why' => 'El sitio pide a los buscadores que no sigan sus propios enlaces, y la página recibe menos peso.',
            'fix' => 'Quita nofollow de los enlaces a las páginas del propio sitio.',
        ],
    ],
    'mixed_content' => [
        'title' => 'Contenido mixto',
        'found' => 'Una página https carga un recurso por http.',
        'why' => 'Los navegadores bloquean esos scripts y estilos y avisan de las imágenes; el candado desaparece.',
        'fix' => 'Carga el recurso por https o usa una ruta sin el esquema.',
    ],
    'forms' => [
        'insecure' => [
            'title' => 'Formulario enviado por http',
            'found' => 'Un formulario se envía a una dirección http.',
            'why' => 'Lo que escriben los visitantes viaja sin cifrar, y los navegadores avisan antes de enviarlo.',
            'fix' => 'Apunta el formulario a una dirección https o a una ruta.',
        ],
    ],
    'images' => [
        'alt' => [
            'title' => 'Imágenes sin alt',
            'found' => 'Una <img> no tiene atributo alt.',
            'why' => 'Los lectores de pantalla leen el nombre del archivo, y los buscadores no saben qué muestra la imagen. Un alt vacío en una imagen decorativa está bien.',
            'fix' => 'Describe la imagen en su alt, o pon alt="" si es decoración.',
        ],
        'dimensions' => [
            'title' => 'Imágenes sin tamaño',
            'found' => 'Una <img> no tiene width ni height.',
            'why' => 'La página salta mientras se cargan las imágenes, y los visitantes pulsan lo que no querían.',
            'fix' => 'Imprime width y height de las imágenes en la plantilla; el CSS aún puede hacerlas adaptables.',
        ],
    ],
    'a11y' => [
        'button_name' => [
            'title' => 'Botones sin nombre',
            'found' => 'Un botón no tiene texto, aria-label ni title.',
            'why' => 'Un lector de pantalla solo puede decir «botón», y su usuario no sabe qué hace.',
            'fix' => 'Da al botón un texto, o un aria-label si es un icono.',
        ],
        'form_label' => [
            'title' => 'Campos sin etiqueta',
            'found' => 'Un campo de formulario no tiene label ni aria-label.',
            'why' => 'Un lector de pantalla no puede decir qué escribir; un placeholder desaparece en cuanto se empieza a escribir.',
            'fix' => 'Añade un <label for="…"> a cada campo, o un aria-label.',
        ],
        'iframe_title' => [
            'title' => 'Marcos sin title',
            'found' => 'Un iframe no tiene title.',
            'why' => 'Los lectores de pantalla anuncian un marco sin nombre, y sus usuarios no distinguen un mapa de un vídeo.',
            'fix' => 'Añade un title que diga qué muestra el marco.',
        ],
    ],
    'structure' => [
        'depth' => [
            'title' => 'Páginas profundas',
            'found' => 'Una página indexable está más lejos de la página de inicio que el umbral, en clics.',
            'why' => 'Los buscadores visitan menos las páginas profundas y las valoran menos; los visitantes rara vez llegan.',
            'fix' => 'Enlaza la página desde una categoría, el menú o páginas relacionadas.',
        ],
        'orphan' => [
            'title' => 'Páginas huérfanas',
            'found' => 'La página está en el sitemap o en el registro de direcciones, pero ninguna página del sitio enlaza a ella.',
            'why' => 'Los visitantes no pueden llegar a ella, y los buscadores consideran poco importante una página a la que nada enlaza.',
            'fix' => 'Enlaza la página desde donde corresponda, o despublícala si no hace falta.',
        ],
        'dead_end' => [
            'title' => 'Callejones sin salida',
            'found' => 'La página no enlaza a ninguna otra página del sitio.',
            'why' => 'Un visitante que llega a ella no tiene adónde ir salvo atrás.',
            'fix' => 'Comprueba que se usa la plantilla base con su menú y añade enlaces a páginas relacionadas.',
        ],
    ],
];
