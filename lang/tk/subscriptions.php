<?php

return [
    'title' => 'Abunalar',
    'plans_title' => 'Nyrh meýilnamalary',
    'history_title' => 'Resmileşdirilen abunalar',

    'stats' => [
        'active' => 'Işjeň abunalar',
        'expiring_soon' => '7 günde gutarýar',
        'revenue' => 'Abunalardan ýygnalan',
    ],

    'currency' => 'manat',
    'days_short' => 'gün',
    'days_left' => 'Galan günler',

    'plan' => [
        'add' => 'Nyrh goş',
        'edit' => 'Nyrhy üýtget',
        'name_ru' => 'Ady (RU)',
        'name_tk' => 'Ady (TK)',
        'description_ru' => 'Düşündiriş (RU)',
        'description_tk' => 'Düşündiriş (TK)',
        'duration_days' => 'Dowamlylygy (gün)',
        'price' => 'Bahasy',
        'sort_order' => 'Tertibi',
        'status' => 'Ýagdaýy',
        'active' => 'Işjeň',
        'inactive' => 'Öçürilen',
        'purchases' => 'Satyn alnan',
        'empty' => 'Häzirlikçe nyrh ýok',
        'delete_confirm' => 'Nyrhy öçürmelimi? Satyn alnan abunalar işlemegini dowam eder.',
    ],

    'subscription' => [
        'issue' => 'Abuna resmileşdir',
        'edit' => 'Abunany üýtget',
        'master' => 'Usta',
        'master_placeholder' => 'Ussady saýlaň',
        'plan' => 'Nyrh meýilnamasy',
        'plan_placeholder' => 'Nyrhy saýlaň',
        'price_paid' => 'Tölenen',
        'price_hint' => 'Öňünden nyrhyň bahasy. 0 — mugt giriş.',
        'note' => 'Bellik',
        'note_placeholder' => 'Meselem: nagt töleg',
        'starts_at' => 'Başlanýar',
        'expires_at' => 'Gutarýar',
        'status' => 'Ýagdaýy',
        'created_by' => 'Resmileşdiren',
        'created_at' => 'Döredilen',
        'actions' => 'Hereketler',
        'empty' => 'Häzirlikçe abuna ýok',
        'delete_confirm' => 'Abunany öçürmelimi? Ussadyň girişi täzeden hasaplanar.',
        'renewal_hint' => 'Ussadyň eýýäm işjeň abunasy bar — täzesi nobata durar we ol gutarandan soň başlar.',
    ],

    'filters' => [
        'all_masters' => 'Ähli ussatlar',
        'all_statuses' => 'Ähli ýagdaýlar',
        'reset' => 'Arassala',
    ],

    'actions' => [
        'activate' => 'Işjeňleşdir',
        'expire' => 'Tamamla',
        'cancel' => 'Ýatyr',
        'save' => 'Ýatda sakla',
        'close' => 'Ýap',
    ],

    'statuses' => [
        'pending' => 'Garaşýar',
        'active' => 'Işjeň',
        'expired' => 'Möhleti gutardy',
        'cancelled' => 'Ýatyryldy',
    ],

    'errors' => [
        'plan_not_available' => 'Nyrh öçürilen — onuň boýunça abuna resmileşdirip bolmaýar',
        'plan_deleted' => 'Nyrh öçürildi — onuň boýunça abuna resmileşdirip bolmaýar',
        'master_inactive' => 'Usta bloklanan',
        'invalid_transition' => 'Abunany «:from» ýagdaýyndan «:to» ýagdaýyna geçirip bolmaýar',
        'already_active' => 'Ussadyň eýýäm işjeň abunasy bar',
        'already_final' => 'Abuna eýýäm soňky ýagdaýynda',
    ],
];
