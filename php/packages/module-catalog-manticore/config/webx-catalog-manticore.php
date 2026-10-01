<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The server
    |---------------------------------------------------------------------------
    |
    | A Manticore somebody else runs, often for several projects at once: the
    | package talks to its HTTP JSON API and never installs or configures it.
    |
    | The prefix has no default, on purpose. Two sites with one APP_NAME on one
    | server would overwrite each other's tables without a word, so the engine
    | does not start without it and `webx:doctor` says why. `[a-z0-9_]`; the
    | tables are `{prefix}_catalog_products_{locale}`, and nothing else on the
    | server is ever touched.
    |
    */

    'host' => env('MANTICORE_HOST', '127.0.0.1'),

    'port' => (int) env('MANTICORE_PORT', 9308),

    'table_prefix' => env('MANTICORE_TABLE_PREFIX'),

    /*
    |---------------------------------------------------------------------------
    | When it does not answer
    |---------------------------------------------------------------------------
    |
    | Seconds to connect and to wait for an answer. A server that is down
    | should cost a page a second, not the thirty of PHP's default; after the
    | first failure it is not asked again for `down_for` seconds — the
    | catalogue goes straight to the database, or answers 503 past
    | `webx-catalog.sql_engine_limit`.
    |
    */

    'connect_timeout' => 1.5,

    'timeout' => 5,

    'down_for' => 30,

    /*
    |---------------------------------------------------------------------------
    | Morphology
    |---------------------------------------------------------------------------
    |
    | The processors of each language. A table carries those of every language
    | of the site, its own first: among languages of one alphabet the first
    | processor wins, which is why each language has a table of its own. A
    | language not named here is indexed without morphology.
    |
    */

    'morphology' => [
        'ru' => 'lemmatize_ru_all',
        'uk' => 'lemmatize_uk_all',
        'en' => 'stem_en',
        'de' => 'libstemmer_de',
        'fr' => 'libstemmer_fr',
        'es' => 'libstemmer_es',
        'it' => 'libstemmer_it',
        'pt' => 'libstemmer_pt',
        'nl' => 'libstemmer_nl',
        'cs' => 'stem_cz',
        'tr' => 'libstemmer_tr',
        'sv' => 'libstemmer_sv',
        'fi' => 'libstemmer_fi',
        'da' => 'libstemmer_da',
        'no' => 'libstemmer_no',
        'hu' => 'libstemmer_hu',
        'ro' => 'libstemmer_ro',
        'ar' => 'stem_ar',
    ],

    /*
    |---------------------------------------------------------------------------
    | Words
    |---------------------------------------------------------------------------
    |
    | The beginning of a word is always searched — a safety net for languages
    | whose morphology is weak or missing. The weights put a match in the
    | page's own language above the same word in another language of the site.
    |
    | A code — the article number, the barcode — is also searched by any part
    | of it, `min_infix_len` characters at least. The infix is the table's, and
    | the corrections of a search that found nothing (`CALL QSUGGEST`) need it
    | as well; the names are still searched by their words and beginnings.
    |
    */

    'min_prefix_len' => 3,

    'min_infix_len' => 3,

    'weights' => [
        'own' => 10,
        'other' => 3,
    ],

    /*
    |---------------------------------------------------------------------------
    | Keyboard layouts
    |---------------------------------------------------------------------------
    |
    | A search that finds nothing is tried as if typed with another layout of
    | the site's languages — «xt[jk» is «чехол» with English on — before its
    | words are corrected. A layout is what the 47 keys of a US keyboard type,
    | row by row, then the same with Shift. A language without one is skipped.
    |
    */

    'layouts' => [
        'en' => ['`1234567890-=qwertyuiop[]\\asdfghjkl;\'zxcvbnm,./', '~!@#$%^&*()_+QWERTYUIOP{}|ASDFGHJKL:"ZXCVBNM<>?'],
        'ru' => ['ё1234567890-=йцукенгшщзхъ\\фывапролджэячсмитьбю.', 'Ё!"№;%:?*()_+ЙЦУКЕНГШЩЗХЪ/ФЫВАПРОЛДЖЭЯЧСМИТЬБЮ,'],
        'uk' => ['\'1234567890-=йцукенгшщзхїґфівапролджєячсмитьбю.', '₴!"№;%:?*()_+ЙЦУКЕНГШЩЗХЇҐФІВАПРОЛДЖЄЯЧСМИТЬБЮ,'],
        'be' => ['ё1234567890-=йцукенгшўзх\'\\фывапролджэячсмітьбю.', 'Ё!"№;%:?*()_+ЙЦУКЕНГШЎЗХ\'/ФЫВАПРОЛДЖЭЯЧСМІТЬБЮ,'],
        'de' => ['^1234567890ß´qwertzuiopü+#asdfghjklöäyxcvbnm,.-', '°!"§$%&/()=?`QWERTZUIOPÜ*\'ASDFGHJKLÖÄYXCVBNM;:_'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Facets
    |---------------------------------------------------------------------------
    |
    | The most values one facet answers, and how many found products a source
    | that picks its facets by share (the properties) is shown to judge by.
    |
    */

    'facet_values' => 5000,

    'relevance_sample' => 1000,

];
