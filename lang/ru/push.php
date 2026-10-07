<?php

return [
    'client' => [
        'master_responded' => [
            'title' => 'Новый отклик на заказ',
            'body' => 'Мастер :master откликнулся на ваш заказ №:order',
        ],
        'master_assigned' => [
            'title' => 'Мастер назначен',
            'body' => 'Мастер :master выполнит ваш заказ №:order',
        ],
        'order_in_progress' => [
            'title' => 'Мастер приступил к заказу',
            'body' => 'Мастер :master в пути или уже на месте — заказ №:order',
        ],
        'order_completed' => [
            'title' => 'Заказ выполнен',
            'body' => 'Мастер :master завершил заказ №:order. Оцените его работу',
        ],
    ],

    'master' => [
        'new_order' => [
            'title' => 'Новый заказ рядом',
            'body' => ':category — :address',
        ],
        'response_approved' => [
            'title' => 'Клиент выбрал вас',
            'body' => 'Клиент принял ваш отклик на заказ №:order',
        ],
        'order_cancelled' => [
            'title' => 'Заказ отменён',
            'body' => 'Заказ №:order отменён',
        ],
        'subscription_expiring' => [
            'title' => 'Подписка скоро закончится',
            'body' => 'Подписка «:plan» действует до :date. Продлите её, чтобы продолжать получать заказы',
        ],
        'subscription_expired' => [
            'title' => 'Подписка закончилась',
            'body' => 'Подписка «:plan» закончилась. Продлите её, чтобы снова получать заказы',
        ],
    ],
];
