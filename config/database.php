<?php

return [

    'default' => env('DB_CONNECTION', 'mongodb'),

    'connections' => [

        'mongodb' => [
            'driver'   => 'mongodb',
            'dsn'      => env('MONGO_URI', 'mongodb://127.0.0.1:27017'),
            'database' => env('MONGO_DB', 'spice_db'),
        ],

    ],

];
