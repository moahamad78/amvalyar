<?php

return [
    'admin' => [
        'username' => env('DEMO_ADMIN_USERNAME'),
        'name' => env('DEMO_ADMIN_NAME', 'AmvalYar Admin'),
        'email' => env('DEMO_ADMIN_EMAIL'),
        'password' => env('DEMO_ADMIN_PASSWORD'),
    ],
    'kimia' => [
        'password' => env('KIMIA_DEMO_PASSWORD'),
    ],
];
