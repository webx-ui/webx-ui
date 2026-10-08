<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

/**
 * Anchors that say nothing about where they lead, in every language the panel ships — what
 * `links.vague_anchor` looks for before the site's own words. Compared whole and lowercased,
 * without the punctuation around them ({@see VagueAnchors::normalise()}): "Read more…" is here,
 * "Read more about roses" is not.
 */
final class VagueWords
{
    /** @var list<string> */
    public const ALL = [
        // English
        'here', 'click here', 'click', 'this', 'this link', 'link', 'more', 'read more', 'learn more',
        'see more', 'view more', 'find out more', 'more info', 'more information', 'details', 'more details',
        'continue', 'continue reading', 'go', 'open', 'view', 'info',
        // Russian
        'здесь', 'тут', 'сюда', 'нажмите здесь', 'нажмите', 'кликните здесь', 'по ссылке', 'ссылка', 'ссылке',
        'подробнее', 'подробно', 'читать далее', 'читать дальше', 'читать', 'далее', 'дальше', 'ещё', 'еще',
        'больше', 'узнать больше', 'узнать подробнее', 'перейти', 'смотреть', 'открыть',
        // Ukrainian
        'тут', 'ось тут', 'сюди', 'натисніть тут', 'натисніть', 'за посиланням', 'посилання', 'детальніше',
        'докладніше', 'читати далі', 'читати', 'далі', 'ще', 'більше', 'дізнатися більше', 'перейти',
        'дивитися', 'відкрити',
        // German
        'hier', 'hier klicken', 'klicken sie hier', 'klicken', 'link', 'mehr', 'mehr lesen', 'weiterlesen',
        'mehr erfahren', 'mehr infos', 'mehr informationen', 'weiter', 'details', 'öffnen', 'ansehen',
        // Polish
        'tutaj', 'tu', 'kliknij tutaj', 'kliknij', 'link', 'więcej', 'czytaj więcej', 'czytaj dalej',
        'dowiedz się więcej', 'szczegóły', 'dalej', 'zobacz', 'zobacz więcej', 'otwórz',
        // French
        'ici', 'cliquez ici', 'cliquez', 'lien', 'ce lien', 'plus', 'en savoir plus', 'lire la suite',
        'lire plus', 'la suite', 'suite', 'détails', 'voir plus', 'voir', 'ouvrir', 'continuer',
        // Spanish
        'aquí', 'aqui', 'haz clic aquí', 'clic aquí', 'pulsa aquí', 'enlace', 'este enlace', 'más', 'leer más',
        'más información', 'ver más', 'saber más', 'detalles', 'ver', 'abrir', 'continuar',
        // Italian
        'qui', 'clicca qui', 'clicca', 'link', 'questo link', 'di più', 'leggi di più', 'leggi tutto',
        'continua a leggere', 'scopri di più', 'altro', 'dettagli', 'vedi', 'vedi di più', 'apri', 'continua',
        // Portuguese
        'aqui', 'clique aqui', 'clique', 'link', 'este link', 'mais', 'leia mais', 'ler mais', 'saiba mais',
        'ver mais', 'mais informações', 'detalhes', 'ver', 'abrir', 'continuar',
        // Turkish
        'burada', 'buraya', 'buraya tıklayın', 'tıklayın', 'tıkla', 'bağlantı', 'link', 'devamı',
        'devamını oku', 'daha fazla', 'daha fazlası', 'detaylar', 'ayrıntılar', 'incele', 'aç', 'görüntüle',
    ];
}
