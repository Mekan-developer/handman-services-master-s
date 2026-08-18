<?php

return [
    'title' => 'Müşderiler',

    'add' => 'Müşderi goş',
    'edit' => 'Müşderini redaktirle',
    'save' => 'Sakla',
    'cancel' => 'Ýatyr',
    'actions' => 'Hereketler',
    'empty' => 'Heniz müşderi ýok',
    'select_city' => 'Şäher saýlaň',

    'active' => 'Işjeň',
    'blocked' => 'Petiklenen',
    'block' => 'Petikle',
    'unblock' => 'Açyk et',

    'photo' => 'Surat',
    'photo_add' => 'Goş',
    'photo_change' => 'Üýtget',
    'photo_hint' => '3×4 format, JPG/PNG, 5 MB çenli. Ussa profilinde-de ulanylýar',

    'delete_confirm' => 'Müşderini pozmalymy? Onuň sargytlary, ýerine ýetirilen işleri we ähli suratlary hem pozular.',

    'errors' => [
        'delete_master_has_completed_orders' => 'Müşderini pozup bolmaýar: ol :count ýerine ýetirilen sargytly ussa. Bu taryh sargyt eden müşderilere gerek.',
        'delete_master_has_active_orders' => 'Müşderini pozup bolmaýar: ol işdäki :count sargytly ussa. Ilki olary tamamlaň ýa-da ýatyryň.',
    ],
    'block_confirm' => 'Müşderini petiklemek isleýärsiňizmi? Ol programma girip bilmez.',
    'unblock_confirm' => 'Müşderini açmak isleýärsiňizmi?',

    'filters' => [
        'all_oblasts' => 'Ähli welaýatlar',
        'all_cities' => 'Ähli şäherler',
        'by_oblast' => 'Welaýat boýunça',
        'by_city' => 'Şäher boýunça',
        'reset' => 'Arassala',
    ],

    'fields' => [
        'name' => 'Ady',
        'phone' => 'Telefon',
        'city' => 'Şäher',
        'orders_count' => 'Sargytlar',
        'status' => 'Ýagdaý',
    ],

    'notifications' => [
        'blocked' => 'Müşderi petiklendi',
        'unblocked' => 'Müşderi açyldy',
    ],
];
