<?php

return [

    // Có thể để 'api' hoặc 'web' tuỳ app; với API thuần, đặt 'api' cũng được
    'defaults' => [
        'guard' => 'api',        // hoặc 'web' nếu bạn có route web
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [                // PHẢI có khối này để tránh lỗi
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'passport',   // dùng Passport
            'provider' => 'users',
            // 'hash' => false,       // tuỳ chọn
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        // Nếu bạn dùng provider 'database' thì thêm cấu hình tương ứng
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_resets',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
