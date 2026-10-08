<?php

return [

    /*
    | Single admin account for the web UI. Set both in .env; while either is
    | empty nobody can log in. Never commit real values.
    */
    'admin' => [
        'username' => env('ADMIN_USERNAME', ''),
        'password' => env('ADMIN_PASSWORD', ''),
    ],

    /*
    | Timestamps are stored in UTC and shown in this timezone.
    */
    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Ho_Chi_Minh'),

];
