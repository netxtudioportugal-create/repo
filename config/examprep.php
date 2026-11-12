<?php

return [

    /*
    | The locale set, frozen at Gate 0: en-US, pt-BR, es-419, uk-UA, ru-UA.
    | These codes are used everywhere (content, learners, lang/ folders).
    | "menu" is the short label in the language menu and is display only.
    | ru-UA is Russian for Ukrainian and diaspora families (never ru-RU) and is
    | listed right after Ukrainian, per the Framing Language document.
    */
    'locales' => [
        'en-US' => ['name' => 'English', 'menu' => 'EN', 'html' => 'en-US'],
        'pt-BR' => ['name' => 'Português (Brasil)', 'menu' => 'PT', 'html' => 'pt-BR'],
        'es-419' => ['name' => 'Español', 'menu' => 'ES', 'html' => 'es-419'],
        'uk-UA' => ['name' => 'Українська', 'menu' => 'УКР', 'html' => 'uk-UA'],
        'ru-UA' => ['name' => 'Русский', 'menu' => 'РУС', 'html' => 'ru-UA'],
    ],

    'fallback_locale' => 'en-US',

    /*
    | Codes used before the locale migration. Only read to carry over a
    | language choice stored in an old browser session.
    */
    'legacy_locales' => [
        'en' => 'en-US',
        'pt' => 'pt-BR',
        'es' => 'es-419',
        'es-LATAM' => 'es-419',
        'uk' => 'uk-UA',
        'ru-RU' => 'ru-UA',
    ],

    /*
    | Gate 0. While closed, nothing can be moved to "released", so candidates
    | (anyone not signed in as staff) see an empty item pool.
    */
    'release_gate_open' => (bool) env('EXAMPREP_RELEASE_GATE_OPEN', false),

    /*
    | Timed set pace: about one minute per item plus one, similar to the real
    | exam's 90 minutes for 85 questions.
    */
    'timed' => [
        'minutes_per_item' => 1,
        'extra_minutes' => 1,
    ],

    'learner_cookie' => 'flow_learner',
    'learner_cookie_days' => (int) env('EXAMPREP_LEARNER_COOKIE_DAYS', 365),

    'publisher' => env('EXAMPREP_PUBLISHER', 'AS Behavioral and Mental Health Services'),
    'author' => [
        'name' => env('EXAMPREP_AUTHOR_NAME', 'Americo Silva, MS, BCBA, IBA'),
        'credential' => env('EXAMPREP_AUTHOR_CREDENTIAL', 'BACB Certification No. 1-21-53901'),
    ],

    /*
    | Cloudflare Turnstile on the staff sign-in form. Leave empty to disable.
    */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    /*
    | Item bank for domains A to E, imported on every deploy (idempotent).
    */
    'items_file' => database_path('data/items.json'),

    /*
    | Ethics Pilot (domain F), all locales: one file per import step. Steps
    | are run by hand, in order, with pilot:import, so the stale and version
    | evidence can be recorded between steps.
    */
    'pilot' => [
        'path' => database_path('data/ethics_pilot'),
        'steps' => [3, 4, 5, 6],
    ],

    /*
    | Controlled ABA terminology dictionary (flow_exam_prep_aba_terms), one
    | JSON file per import package. The locale set is frozen at Gate 0:
    | en-US, pt-BR, es-419, uk-UA, ru-UA (ru-RU is never valid). en-US is the
    | canonical source; other locales are "staging" until language review
    | is completed, then "approved".
    */
    'terminology' => [
        'path' => database_path('data/terminology'),

        // Allowed statuses per dictionary locale.
        'statuses' => [
            'en-US' => ['canonical'],
            'pt-BR' => ['staging', 'approved'],
            'es-419' => ['staging', 'approved'],
            'uk-UA' => ['staging', 'approved'],
            'ru-UA' => ['staging', 'approved'],
        ],

        // Keep these titles in English; a localized explanation may follow in parentheses.
        'bacb_titles' => [
            'RBT Supervisor',
            'RBT Requirements Coordinator',
            'RBT Test Content Outline',
            'RBT Initial Competency Assessment',
        ],
    ],
];
