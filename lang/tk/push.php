<?php

return [
    'client' => [
        'master_responded' => [
            'title' => 'Sargydyňyza täze jogap',
            'body' => 'Ussa :master №:order sargydyňyza jogap berdi',
        ],
        'master_assigned' => [
            'title' => 'Ussa bellenildi',
            'body' => 'Ussa :master №:order sargydyňyzy ýerine ýetirer',
        ],
        'order_in_progress' => [
            'title' => 'Ussa sargyda başlady',
            'body' => 'Ussa :master ýolda ýa-da eýýäm ýerinde — №:order sargyt',
        ],
        'order_completed' => [
            'title' => 'Sargyt ýerine ýetirildi',
            'body' => 'Ussa :master №:order sargydy tamamlady. Onuň işine baha beriň',
        ],
    ],

    'master' => [
        'new_order' => [
            'title' => 'Golaýda täze sargyt',
            'body' => ':category — :address',
        ],
        'response_approved' => [
            'title' => 'Müşderi sizi saýlady',
            'body' => 'Müşderi №:order sargyt boýunça jogabyňyzy kabul etdi',
        ],
        'order_cancelled' => [
            'title' => 'Sargyt ýatyryldy',
            'body' => '№:order sargyt ýatyryldy',
        ],
        'subscription_expiring' => [
            'title' => 'Abuna ýakyn wagtda gutarýar',
            'body' => '«:plan» abunasy :date çenli hereket edýär. Sargyt almagy dowam etmek üçin ony uzaldyň',
        ],
        'subscription_expired' => [
            'title' => 'Abuna gutardy',
            'body' => '«:plan» abunasy gutardy. Täzeden sargyt almak üçin ony uzaldyň',
        ],
        'subscription_request_approved' => [
            'title' => 'Nyrh birikdirildi',
            'body' => '«:plan» nyrhy resmileşdirildi, giriş :date çenli açyk',
        ],
        'subscription_request_rejected' => [
            'title' => 'Nyrh arzasy ret edildi',
            'body' => 'Administrator nyrh arzaňyzy ret etdi',
        ],
    ],
];
