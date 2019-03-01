<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bref Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your Lambda. This value is used when the
    | framework needs to generate the lambda function names.
    |
    */
    'name' => env('BREF_NAME', env('APP_NAME')),

    /*
    |--------------------------------------------------------------------------
    |Packaging
    |--------------------------------------------------------------------------
    |
    | This array configures the files that should be ignored when packaging
    | your application, as well as identifying executable files.
    |
    */
    'packaging' => [
        'ignore' => [
            // Directories & Fully Qualified Paths
            base_path('tests'),
            base_path('storage'),
            base_path('.idea'),
            base_path('.git'),
            base_path('server.php'),
            base_path('.env'),
            base_path('.env.example'),
            base_path('versions.json'),
            base_path('.php_cs.cache'),
            base_path('.stack.yaml'),
            base_path('phpunit.xml'),
            base_path('template.yaml'),
            // File Names
            '.gitignore',
            '.gitkeep',
            '.htaccess',
            'readme.md',
            'composer.json',
            'composer.lock',
            '.DS_Store',
            '.editorconfig',
            '.gitattributes',
            'package.json',
        ],
        // Any executables should be here.
        'executables' => [
            'artisan',
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Ignored .env Variables
    |--------------------------------------------------------------------------
    |
    | This value is the name of your Lambda. This value is used when the
    | framework needs to generate the lambda function names.
    |
    */
    'env' => [
        'ignore' => [
            'APP_STORAGE',      // Hardcoded to 'tmp'
            'BREF_LAMBDA_ENV',  // True
            'LOG_CHANNEL',      // stderr (ensures everything logs into cloudwatch)
            'CACHE_DRIVER',     // file (because, what else really makes sense?
            'SESSION_DRIVER',   // Array
        ],
    ],
];
