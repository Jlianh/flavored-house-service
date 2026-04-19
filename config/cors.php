<?php 
    return [

        'paths' => ['api/*'],

        'allowed_methods' => ['*'],

        'allowed_origins' => [
            'http://localhost:4200',
            'https://www.lacasitadelsabor.com',
            'https://lacasitadelsabor.com'
        ],

        'allowed_headers' => ['*'],

        'supports_credentials' => true,
    ];
?>